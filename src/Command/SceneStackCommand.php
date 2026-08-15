<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\Interpenetration;
use App\Scene\LayoutMode;
use App\Scene\MirrorStyle;
use App\Scene\RolledBox;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\SceneSpec;
use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\StackEntry;
use App\Scene\StackOrientation;
use App\Scene\StackSceneWriter;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\SplitMode;
use App\Spec\DeviceSpec;
use App\Spec\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes scene files from constraints instead of from a tier list somebody typed out.
 *
 * `scene:build` already solves a `stack:` block, but somebody has to write the scene first. This is the step
 * before that: give it the gear and the bounds, get one scene per arrangement that actually works — and a
 * reason for every arrangement it left out, so a constraint that rules one out is visible rather than
 * silently absent.
 *
 * Every candidate is **compiled before it is written**. A generator that emits a scene the compiler rejects
 * is worse than no generator, because the failure surfaces later and further from its cause.
 */
final class SceneStackCommand extends BaseCommand
{
    /**
     * A ceiling on accident, not on ambition.
     *
     * 24 was right while the command wrote one rig per alignment. The default is now a **sweep** — every combination of
     * owners, by one, two and three stacks, each in both shapes, all seven orientation/mirror pairs and all three
     * alignments — which tries 804 candidates on the current inventory and writes 149 of them, the rest being duplicates
     * and named refusals. So the limit has to clear that with room for the gear list to grow, while still catching the
     * case it exists for: an axis added by mistake, where the count goes to thousands rather than hundreds.
     *
     * **Raised deliberately, once per axis, and that is the point of it.** 80 fitted the 61 scenes the orientation axis
     * wrote and the owner combinations took it straight past — which is exactly what should happen, because the raise is
     * where somebody looks at the number and decides it is the output they meant. The next one is CVR-5, whose
     * `impossible` half would turn today's 655 refusals into written scenes.
     *
     * **A fuse rather than a cap**: over the limit the command writes *nothing* and says so. Truncating to the first N
     * would read as "that is every possibility" when it is not, which is the same reason every refusal is printed.
     */
    private const DEFAULT_MAX_SCENES = 200;

    /**
     * The stage the bare command solves against, and the top of the 2–3 m band a sub/top transition should sit in.
     *
     * **Defaults rather than required options, because the goal is that one command in its default settings produces
     * every sensible rig.** Without bounds the bare command had none to solve against and wrote nothing at all: an
     * unbounded stack of both systems' gear cannot stand up, and every one of the generated scenes had to spell four
     * to six flags out to get anywhere.
     *
     * 3.70 m is the stage width every hand-written scene in the repository uses. 3.0 m is the ceiling — safe to
     * default because a ceiling is monotone at the solver level (measured across six inventories and both shapes: 13
     * shorter, 11 unchanged, none taller) once the strategy tie is broken on height rather than on the order the
     * strategies happen to be tried in. A rig that cannot get under it says by how much rather than failing.
     */
    private const DEFAULT_MAX_WIDTH_M = 3.70;

    private const DEFAULT_MAX_SUB_HEIGHT_M = 3.0;

    /**
     * The stage widths per stack the sweep is allowed to try, so a rig that misses its sub height band can be moved
     * onto a stage that fits it instead of being skipped. See {@see buildInBand} for why width is the lever.
     *
     * Real widths in 0.40–0.80 m steps rather than a continuum, and deliberately coarse: a rung has to be a stage
     * somebody could actually deck, and the solve is a row count, so a finer ladder mostly returns the same
     * arrangement twice. 3.70 m — the width every hand-written scene uses — is in it so the ordinary case is a
     * rung and not a special case beside one.
     */
    private const WIDTH_LADDER_M = [2.00, 2.40, 2.80, 3.20, 3.70, 4.40, 5.20, 6.00];

    /**
     * The focus a near-field fill is turned towards — one of the two {@see StackSceneWriter} always writes.
     *
     * Named here rather than in {@see Stack} because only the writer guarantees the focus exists: a hand-written
     * scene need not define `near`, and aiming at a focus that is not there is an error.
     */
    private const NEAR_FOCUS = 'near';

    /** How close two faces count as touching — the same millimetre `ShippedScenesTest` uses, so they agree. */
    private const CONTACT_TOLERANCE_M = 0.001;

    protected function configure(): void
    {
        $this
            ->setName('scene:stack')
            ->setDescription('Solve a rig from constraints and write it out as scene files')
            ->addOption('from', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids, low frequency first. Default: every speaker, subs before tops')
            ->addOption('max-width', null, InputOption::VALUE_REQUIRED, 'How wide the stage or truss allows, in metres', (string)self::DEFAULT_MAX_WIDTH_M)
            ->addOption('min-width', null, InputOption::VALUE_REQUIRED, 'Floor on the widest tier, in metres')
            ->addOption('max-height', null, InputOption::VALUE_REQUIRED, 'Ceiling or rigging limit, in metres')
            ->addOption('interface-height', null, InputOption::VALUE_REQUIRED, 'Height the tops must clear, in metres', (string)Stack::DEFAULT_INTERFACE_HEIGHT_M)
            ->addOption('max-sub-height', null, InputOption::VALUE_REQUIRED, 'Ceiling on the sub/top transition, in metres. Lets a row hold several device types', (string)self::DEFAULT_MAX_SUB_HEIGHT_M)
            ->addOption('target-sub-height', null, InputOption::VALUE_REQUIRED, 'The sub/top transition to aim at, in metres. A preference between the two bounds, never a refusal', (string)Stack::DEFAULT_TARGET_SUB_HEIGHT_M)
            ->addOption('split', null, InputOption::VALUE_REQUIRED, 'by-count (a share of every device to every stack) or by-type (whole types per stack, which comes out lower)', SplitMode::ByCount->value)
            ->addOption('no-asymmetry', null, InputOption::VALUE_NONE, 'Leave the odd cabinets out rather than giving one stack more than another')
            ->addOption('gap', null, InputOption::VALUE_REQUIRED, 'Working gap between neighbours, in metres', '0.02')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Where the rig is centred, as X,Y', '-0.302,0')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Base scene id', 'stacked')
            ->addOption('align', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'center, block or stereo. Default: all three')
            ->addOption('shape', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'pyramid (rows narrow going up) or free (as wide as bearing allows). Default: both')
            ->addOption('mirror-style', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What a turned row does with its odd cabinet: alternate (side flips per row), centred (unrolled in the middle) or column (same side every row). Default: all three where something is rolled')
            ->addOption('orientation', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which cabinets lie on their sides: upright (none), turned (every sub) or mixed (only where it makes them wider). Tops never roll. Default: all three')
            ->addOption('roll-mirror', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids to lay on their sides, mirrored about the centre line. Repeatable')
            ->addOption('mix', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Share a row: DEVICE:OTHER[,OTHER]. Repeatable. Lowers a stack by merging tiers')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Build from these owners\' gear only. Default: sweep every combination of them')
            ->addOption('per-owner', null, InputOption::VALUE_NONE, 'One stack per owner, side by side, instead of one rig from everything')
            ->addOption('stacks', null, InputOption::VALUE_REQUIRED, 'Split each group into this many stacks. Default: sweep 1, 2 and 3')
            ->addOption('clearance', null, InputOption::VALUE_REQUIRED, 'Air between neighbouring stacks, in metres', '0.5')
            ->addOption('max-scenes', null, InputOption::VALUE_REQUIRED, 'Refuse to write more than this many', (string)self::DEFAULT_MAX_SCENES)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the scenes instead of writing them')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing scene file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        $devices = [];
        foreach ($specs as $spec) {
            $devices[$spec->id] = $spec;
        }

        $missing = array_values(array_diff((array)$input->getOption('from'), array_keys($devices)));
        if ($missing !== []) {
            $this->io->error(sprintf("Unknown device '%s'", $missing[0]));

            return self::FAILURE;
        }

        // Checked here rather than inside {@see ownerCombinations}, because a misspelled owner has to be a refusal that
        // names the owners there are. Silently intersecting it away would leave the whole inventory built instead, which
        // is the opposite of what was asked for and looks like a working run.
        $owners = array_values(array_unique(array_map(
            static fn (DeviceSpec $spec): string => $spec->owner,
            array_filter($specs, static fn (DeviceSpec $spec): bool => $spec->category->value === 'speaker'),
        )));
        sort($owners);
        $unknown = array_values(array_diff((array)$input->getOption('owner'), $owners));
        if ($unknown !== []) {
            $this->io->error(sprintf(
                "--owner: unknown value '%s' (allowed: %s)",
                $unknown[0],
                implode(', ', $owners),
            ));

            return self::FAILURE;
        }

        // Refused rather than resolved, because the two say the same thing at different resolutions and there is no
        // reading of both that is not a guess: `--from` names cabinets outright, so filtering that list by owner would
        // be a third meaning nobody asked for, and ignoring one of the two options silently is worse still.
        if ($input->getOption('owner') !== [] && $input->getOption('from') !== []) {
            $this->io->error('--owner and --from say the same thing at different resolutions — name one or the other');

            return self::FAILURE;
        }

        $rigs = $this->rigsToTry($specs, $input);

        $at = $this->readAt((string)$input->getOption('at'));
        if ($at === null) {
            $this->io->error('--at expects X,Y in metres');

            return self::FAILURE;
        }

        $modes = $this->readModes($input->getOption('align'));
        if ($modes === null) {
            return self::FAILURE;
        }

        $shapes = $this->readShapes($input->getOption('shape'));
        if ($shapes === null) {
            return self::FAILURE;
        }

        $styles = $this->readMirrorStyles($input->getOption('mirror-style'));
        if ($styles === null) {
            return self::FAILURE;
        }

        /** @var list<string> $rolled */
        $rolled = $input->getOption('roll-mirror');
        $orientations = $this->readOrientations($input->getOption('orientation'), $rolled);
        if ($orientations === null) {
            return self::FAILURE;
        }

        // SHAPE FIRST IN THE NAME, and only when it is not the pyramid. The pyramid is the one to reach for, so it
        // keeps the plain id every generated scene already has; putting the shape in both ids would rename all twelve
        // of them for nothing. `deduplicate()` then drops whichever shape resolves to the same rig as the other,
        // which is most of them — a stack already tapering has nothing for the pyramid rule to change.
        $sweeping = $this->isSweep($input);
        $statedWidth = $this->readFloat($input, 'max-width') ?? self::DEFAULT_MAX_WIDTH_M;

        $candidates = [];
        $skipped = [];
        foreach ($rigs as $rig) {
            foreach ($shapes as $shape) {
                foreach ($this->orientationPairs($orientations, $styles, $rolled, $devices, $rig['from']) as [$orientation, $style]) {
                    foreach ($modes as $mode) {
                        $name = sprintf(
                            '%s%s%s%s%s-%s',
                            (string)$input->getOption('id'),
                            $rig['suffix'],
                            $shape === StackShape::Pyramid ? '' : '-'.$shape->value,
                            $orientation === null || $orientation === StackOrientation::Upright
                                ? ''
                                : '-'.$orientation->value,
                            $style === MirrorStyle::Alternate ? '' : '-'.$style->value,
                            $mode->value,
                        );
                        $built = $sweeping
                            ? $this->buildInBand(
                                $devices, $rig['from'], $at, $mode, $shape, $style, $orientation, $rig['stacks'],
                                (string)$input->getOption('id').$rig['suffix'], $statedWidth, $input,
                            )
                            : $this->build(
                                $devices, $rig['from'], $at, $mode, $shape, $style, $orientation, $rig['stacks'],
                                (string)$input->getOption('id').$rig['suffix'], $statedWidth, $input,
                            );
                        if (is_string($built)) {
                            $skipped[$name] = $built;
                            continue;
                        }
                        // **THE BAND BINDS EVERY INVOCATION, not just the sweep.** It was tempting to make it the
                        // sweep's own judgement and leave a named rig to the solver's warning, and that would have
                        // left the hole this command is most often used through: `build:all` regenerates a generated
                        // scene by replaying *its* recorded line, which names `--from` and `--stacks` and so is not a
                        // sweep. Every out-of-band file in the repository would have been rewritten exactly as it was,
                        // for as long as it existed. What the sweep does differently is try to *avoid* the miss
                        // ({@see buildInBand}); what happens to a rig that misses anyway is the same either way.
                        if ($built['bandMiss'] !== null) {
                            $skipped[$name] = $built['bandMiss']['message'];
                            continue;
                        }
                        $candidates[$name] = $built;
                    }
                }
            }
        }

        $candidates = $this->deduplicate($candidates, $skipped);

        foreach ($skipped as $name => $reason) {
            $this->io->text(sprintf('  <comment>skipped</comment> %s — %s', $name, $reason));
        }

        if ($candidates === []) {
            $this->io->warning('No workable arrangement — nothing written');

            return self::FAILURE;
        }

        $limit = (int)$input->getOption('max-scenes');
        if (count($candidates) > $limit) {
            // Refused rather than truncated: a silent cap reads as "that is every possibility" when it is not.
            $this->io->error(sprintf(
                '%d arrangements is more than --max-scenes=%d — narrow it with --owner, --align, --shape or '
                .'--orientation, or raise the limit',
                count($candidates),
                $limit,
            ));

            return self::FAILURE;
        }

        return $this->emit($candidates, (bool)$input->getOption('dry-run'), (bool)$input->getOption('force'));
    }

    /**
     * The first stack whose sub/top transition falls outside the band the scene asked for, with which way it missed,
     * or null when every stack is inside it.
     *
     * **A GENERATED SCENE HAS TO MEET ITS SUB HEIGHT, NOT MERELY AIM AT IT.** The two bounds have always existed —
     * `interface_height_m` is the floor a top must clear to fire over a standing crowd and `max_sub_height_m` the
     * ceiling — and the solver treats both as preferences, reporting a miss as a warning and building the rig anyway.
     * That is right for a scene somebody wrote: they asked for this gear in this space and the near miss is theirs to
     * judge. It is wrong for the sweep, whose entire job is to produce **sensible** configurations: of 54 generated
     * scenes only 16 had every stack inside 2–3 m, and among the rest were `stacked-all-3-turned-center` at 5.73 m and
     * `stacked-sepp-2-center` at 0.60 m — a rig whose tops fire at knee height.
     *
     * **The two options are the control, so there is no third one.** Widen the band and the sweep writes more:
     * `--interface-height=0 --max-sub-height=99` accepts anything, which is what the hand-written low rigs state for
     * themselves. Narrow it and it writes less. Adding a flag to switch the check off would only be a way of asking
     * for rigs nobody would build.
     *
     * `direction` is what makes this more than a refusal: **+1 means the wall is too tall and wants a wider stage,
     * −1 that it is too short and wants a narrower one**, which is the whole mechanism {@see buildInBand} turns into a
     * scene instead of a skip. The message carries the measured height and the bound it missed, because "no workable
     * arrangement" for a rig that is merely 300 mm too tall would send somebody hunting for a geometry fault.
     *
     * @param list<StackBlock> $blocks
     * @return array{message: string, direction: int}|null
     */
    private static function bandMiss(array $blocks): ?array
    {
        foreach ($blocks as $block) {
            $height = $block->subHeightM();
            $floor = $block->stack->interfaceHeightM;
            $ceiling = $block->stack->maxSubHeightM;
            $whose = $block->label === '' ? 'stack\'s' : $block->label.' stack\'s';

            if ($ceiling !== null && $height > $ceiling + 1e-9) {
                return [
                    'direction' => 1,
                    'message' => sprintf(
                        'the %s subs reach %.3f m against the %.3f m ceiling asked for — %.0f mm too high, and a rig '
                        .'that misses its sub height is not one of the possibilities',
                        $whose,
                        $height,
                        $ceiling,
                        ($height - $ceiling) * 1000,
                    ),
                ];
            }
            if ($floor > 0.0 && $height + 1e-9 < $floor) {
                return [
                    'direction' => -1,
                    'message' => sprintf(
                        'the %s subs reach only %.3f m against the %.3f m interface asked for — %.0f mm short, so the '
                        .'tops would fire below head height',
                        $whose,
                        $height,
                        $floor,
                        ($floor - $height) * 1000,
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * The same rig on whatever stage width puts its sub wall inside the band, or the reason no width does.
     *
     * **THE STAGE WIDTH IS THE LEVER, AND IT WAS THE ONE THING THE SWEEP HELD FIXED.** A sub wall gets shorter as the
     * stage gets wider — the same twelve cabinets are five rows on 2.4 m and three on 4.4 m — so a rig that misses the
     * band is usually not an impossible rig, it is a rig on the wrong stage. Held at the default 3.70 m the band left
     * 9 of 132 candidates standing, and 88 of the refusals were a miss in a known direction with an obvious remedy.
     *
     * So the miss drives the search: too tall widens, too short narrows, and the first width that lands inside the
     * band wins. Directional rather than a scan of every width, because it is monotone in the useful sense and the
     * default is the stage most of this gear actually plays on — deviating as little as possible from it keeps a rig
     * that already worked byte-identical, and keeps the width in a written scene meaning "this is the stage it needs"
     * rather than "this is what the search happened to try first".
     *
     * The ladder is {@see WIDTH_LADDER_M} rather than a bisection, and it stops at its ends rather than running away:
     * 6.00 m is already a wide wall per stack and 2.00 m is a pillar, so a rig needing a stage outside those is
     * reported with the miss it had at the nearest width tried instead of being widened until the arithmetic works.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @param array{float, float} $at
     * @return array{yaml: string, cabinets: int, fingerprint: string, bandMiss: array{message: string, direction: int}|null}|string
     */
    private function buildInBand(
        array $devices,
        array $from,
        array $at,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        int $stacks,
        string $baseId,
        float $stated,
        InputInterface $input,
    ): array|string {
        $ladder = self::WIDTH_LADDER_M;
        // The stated width first, wherever it sits in the ladder, so the default stage is what a rig is tried on.
        $wider = array_values(array_filter($ladder, static fn (float $w): bool => $w > $stated + 1e-9));
        $narrower = array_reverse(array_values(array_filter($ladder, static fn (float $w): bool => $w < $stated - 1e-9)));

        $width = $stated;
        $queue = null;
        $last = null;
        $widenedBlind = false;

        // At most one pass per ladder rung, plus the stated width: the direction cannot flip without the band having
        // been cleared on the way, and a rig that oscillates would otherwise loop between two rungs forever.
        for ($step = 0; $step <= count($ladder); ++$step) {
            $built = $this->build(
                $devices, $from, $at, $mode, $shape, $style, $orientation, $stacks, $baseId, $width, $input,
            );
            if (is_string($built)) {
                // **A refusal that is not a height miss gets one rung, not the ladder.** It is worth a rung: both
                // systems across two stacks refuses at 3.70 m with two runs 127 mm inside each other and solves at
                // 4.40 m, 2.033 and 2.833 m of subs — a rig somebody would build, recovered. It is not worth the
                // ladder: walking all of it retried every one of the 122 refused candidates at four widths and tripled
                // the sweep to recover that same one scene. A geometry fault that a rung does not fix is a fault at
                // every width, so one try and then the reason stands.
                $last = $built;
                if ($widenedBlind || $wider === []) {
                    return $last;
                }
                $widenedBlind = true;
                $width = $wider[0];
                continue;
            }

            if ($built['bandMiss'] === null) {
                return $built;
            }

            $last = $built['bandMiss']['message'];
            $queue ??= $built['bandMiss']['direction'] > 0 ? $wider : $narrower;
            $next = array_shift($queue);
            if ($next === null) {
                return sprintf('%s (tried stages %.2f–%.2f m)', $last, min($ladder), max($ladder));
            }
            $width = $next;
        }

        return $last ?? 'no workable arrangement';
    }

    /**
     * The stacks laid out so the tall ones end up where the alignment wants them.
     *
     * **The same rule as the tops row, one level up.** {@see StackSolver::topRow} centres the long throw for mono and
     * {@see StackSolver::stereoTopRow} pushes it to the ends for stereo; a rig of several stacks is the same question
     * asked of whole stacks. Until now they came out in *solve* order — owner alphabetical, or the order the split
     * dealt them — and nothing ever looked at their heights, so `both-systems-per-owner` read `3.34 | 3.20 | 1.80`
     * with the tallest hard left. Seven of thirty multi-stack scenes were wrong that way.
     *
     * * **mono** — tallest in the middle, the rest alternating outward. `3.34 | 3.20 | 1.80` becomes
     *   `3.20 | 3.34 | 1.80`: the biggest pile carries the room from the centre and the small ones widen the coverage.
     * * **stereo** — tallest at the outer ends, working inward. The mirror, because the point of a stereo rig is the
     *   width of its image and the main clusters belong as far apart as the stage allows.
     *
     * **This improves symmetry and does not deliver it**, which is worth being plain about. Ordering can place the
     * tall stacks; it cannot make the two flanks *equal*, because that depends on the split giving each stack similar
     * contents. `--per-owner` puts three different systems side by side and no ordering makes those the same height.
     *
     * @param list<StackBlock> $blocks
     * @return list<StackBlock>
     */
    private static function byHeight(array $blocks, LayoutMode $mode): array
    {
        if (count($blocks) < 3 && $mode !== LayoutMode::Stereo) {
            // Two stacks have no middle to be in, and no mono ordering can tell them apart.
            return $blocks;
        }

        usort(
            $blocks,
            static fn (StackBlock $a, StackBlock $b): int => $b->subHeightM() <=> $a->subHeightM(),
        );

        // Tallest first in `$blocks`. Deal them alternately to build the shape the mode asks for: for mono the
        // tallest takes the middle and each next one goes to the shorter side, which comes out as a list read from
        // the centre outward and then flattened; for stereo the same deal read from the ends inward.
        $left = [];
        $right = [];
        foreach ($blocks as $position => $block) {
            $position % 2 === 0 ? $left[] = $block : $right[] = $block;
        }

        return $mode === LayoutMode::Stereo
            // Tallest at the ends: the tall half outward on the left, the rest inward, mirrored on the right.
            ? [...$left, ...array_reverse($right)]
            // Tallest central: shorter ones outboard on the left, tallest in the middle, the rest to the right.
            : [...array_reverse($right), ...$left];
    }

    /**
     * The mirror styles somebody named, or `[]` for "sweep whatever the orientation gives them to differ on".
     *
     * All three by default, like `--align` and `--shape`, and for the same reason: an odd cabinet in a turned row has no
     * arrangement that is both symmetric and flat, so the choice is a trade rather than an answer. See
     * {@see MirrorStyle}.
     *
     * **THE DEFAULT IS DECIDED PER ORIENTATION RATHER THAN HERE**, which is why this returns an empty list instead of
     * every case. {@see Tier::mirrored} acts only on segments lying on a quarter turn, so the styles are *vacuous*
     * wherever nothing is rolled and come out byte-identical, and the measurement was unambiguous: sweeping the axis
     * unconditionally gave **66 `centred` candidates, 0 written** — 18 caught afterwards by `deduplicate()` and the
     * other 48 refused identically to their `alternate` twin. `deduplicate()` catching them is not good enough, because
     * the cost is not a file, it is *every candidate solved twice*, and a candidate is a full solve plus a compile plus
     * an interpenetration sweep.
     *
     * {@see orientationPairs} is where that judgement now lives, since which cabinets roll is exactly what the
     * orientation axis decides.
     *
     * @param list<string> $raw
     * @return list<MirrorStyle>|null null on a misspelling, `[]` when none was named
     */
    private function readMirrorStyles(array $raw): ?array
    {
        if ($raw === []) {
            return [];
        }

        $styles = [];
        foreach ($raw as $value) {
            $style = MirrorStyle::tryFrom($value);
            if ($style === null) {
                $this->io->error(sprintf(
                    "--mirror-style: unknown value '%s' (allowed: %s)",
                    $value,
                    implode(', ', array_column(MirrorStyle::cases(), 'value')),
                ));

                return null;
            }
            $styles[] = $style;
        }

        return $styles;
    }

    /**
     * The orientations to write a scene for, or null when one is misspelled.
     *
     * All three by default, and this is the axis that pays best of any in the sweep: **the default sweep writes 61 scenes
     * where upright alone writes 11**, three-stack rigs among them for the first time, because a rolled sub is wider and
     * shorter and both of those help a wall land inside the sub height band. See {@see StackOrientation}.
     *
     * **`--roll-mirror` turns the axis off**, which keeps every hand invocation that names cabinets working exactly as
     * it did. The two options answer the same question at different resolutions — which cabinets lie down — so a line
     * naming `--roll-mirror=skram` means *those* and not "sweep three modes and ignore what I said". A `null`
     * orientation is how that is carried: it says "the rolled set is stated on the command line", and
     * {@see stackFor} reads `--roll-mirror` in that case.
     *
     * @param list<string> $raw
     * @param list<string> $rolled the device ids `--roll-mirror` named
     * @return list<StackOrientation|null>|null
     */
    private function readOrientations(array $raw, array $rolled): ?array
    {
        if ($raw === []) {
            return $rolled === [] ? StackOrientation::cases() : [null];
        }

        $orientations = [];
        foreach ($raw as $value) {
            $orientation = StackOrientation::tryFrom($value);
            if ($orientation === null) {
                $this->io->error(sprintf(
                    "--orientation: unknown value '%s' (allowed: %s)",
                    $value,
                    implode(', ', array_column(StackOrientation::cases(), 'value')),
                ));

                return null;
            }
            $orientations[] = $orientation;
        }

        return $orientations;
    }

    /**
     * The orientation and mirror style **as pairs**, because the two are not independent axes.
     *
     * Orientation says which cabinets lie down; mirror style says what a rolled row does with the odd cabinet it cannot
     * split in half. Different questions, and a turned rig genuinely has three different forms — but the style is
     * *vacuous* when nothing is rolled, since {@see Tier::mirrored} only ever acts on a segment lying on a quarter turn.
     * Multiplied out as two axes, one third of every candidate would be a duplicate of another by construction. Paired,
     * the vacuous combinations are **unrepresentable** rather than guarded:
     *
     * | # | pair |
     * | --- | --- |
     * | 1 | `upright` — nothing rolled, so no odd cabinet to place |
     * | 2–4 | `turned` × (`alternate`, `centred`, `column`) |
     * | 5–7 | `mixed` × (`alternate`, `centred`, `column`) |
     *
     * **A mode that rolls nothing in *this* rig is dropped as well**, and that is not the same rule. `mixed` rolls only
     * the cabinets that get wider on their side, so an inventory of a cube and a top has nothing for it to turn — and
     * the candidate it would produce is `upright` under another name. Dropping it here rather than letting
     * `deduplicate()` find it afterwards is what keeps the names honest: a `-turned-` file always has something turned
     * in it.
     *
     * Asked per rig rather than once, because the rigs are different inventories. Asked on the rig's whole device list
     * rather than per stack, because a split can hand one stack no rollable cabinet while the rig plainly has one, and
     * the scene is named for the rig.
     *
     * @param list<StackOrientation|null> $orientations
     * @param list<MirrorStyle> $stated the styles somebody named, `[]` to sweep as each orientation allows
     * @param list<string> $rolled the device ids `--roll-mirror` named, which is what a null orientation defers to
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @return list<array{StackOrientation|null, MirrorStyle}>
     */
    private function orientationPairs(
        array $orientations,
        array $stated,
        array $rolled,
        array $devices,
        array $from,
    ): array {
        $pairs = [];
        foreach ($orientations as $orientation) {
            $rolls = $orientation?->rolls($devices, $from) ?? array_values(array_intersect($from, $rolled));
            if ($rolls === [] && $orientation !== null && $orientation !== StackOrientation::Upright) {
                continue;
            }

            $styles = $stated !== [] ? $stated : ($rolls === [] ? [MirrorStyle::Alternate] : MirrorStyle::cases());
            foreach ($styles as $style) {
                $pairs[] = [$orientation, $style];
            }
        }

        return $pairs;
    }

    /**
     * Every rig worth trying — which gear, and how many stacks to split it into.
     *
     * **THIS IS THE PROJECT'S GOAL EXPRESSED AS A DEFAULT.** As many *sensible* configurations as possible out of one
     * command in its default settings. Before this, the bare command wrote **nothing at all**: `--from` defaulted to
     * every speaker in the repository, which since the GMSS cabinets arrived means two sound systems in one stack —
     * a rig nobody would build, whose eight tops alone are 3.921 m and refuse on any stage we own. Meanwhile every
     * generated scene had to spell four to six flags out to get anywhere.
     *
     * So absence now means *sweep*, the way it already does for `--align` and `--shape`:
     *
     * * **one rig per non-empty combination of owners** ({@see ownerCombinations}) — each owner alone, each pair, and
     *   everything. `owner` is the only discriminator the specs carry, and it is admittedly not quite the right one —
     *   the repository deliberately supports borrowing gear between owners, so "owner" and "system" are not the same
     *   question. It is what exists, it separates the two systems in practice, and inventing a `system:` field to serve
     *   a sweep would be inventing a property to serve a layout. The *pairs* are what that borrowing looks like as a
     *   rig, and they were the gap: the sweep used to jump from one owner straight to all of them.
     * * **one, two and three stacks** — the counts somebody actually varies when planning a gig.
     *
     * Naming each rig into the scene id is what keeps the files apart, and it reads as what it is:
     * `stacked-sdwa5-2-free-stereo` is our gear, two stacks, free shape, stereo.
     *
     * **Naming any of `--from`, `--stacks` or `--per-owner` collapses the sweep to that single point**, exactly as
     * naming `--align` collapses it to one mode. Nothing that worked before works differently; the only change is what
     * *silence* means. `--owner` is the exception and narrows one axis instead of collapsing the sweep, since it says
     * whose gear to build from and nothing about the rig — see {@see ownerCombinations}.
     *
     * @param list<DeviceSpec> $specs
     * @return list<array{from: list<string>, stacks: int, suffix: string}>
     */
    private function rigsToTry(array $specs, InputInterface $input): array
    {
        /** @var list<string> $stated */
        $stated = $input->getOption('from');
        $statedStacks = $input->getOption('stacks');

        /** @var list<string> $owners */
        $owners = $input->getOption('owner');

        // Any of the three narrowing options means the caller has a specific rig in mind.
        if (!$this->isSweep($input)) {
            // **`--owner` still binds on this path**, which is what stops it being silently ignored the moment somebody
            // writes `--owner=gmss --stacks=2`. `--from` names the cabinets outright and wins, and naming both is
            // refused in `execute()` rather than resolved here.
            $narrowed = $owners === []
                ? $specs
                : array_values(array_filter(
                    $specs,
                    static fn (DeviceSpec $spec): bool => in_array($spec->owner, $owners, true),
                ));

            return [[
                'from' => $stated !== [] ? $stated : $this->everySpeaker($narrowed),
                // NOT clamped to 1: an explicit `--stacks=0` is a mistake worth refusing, and {@see groups} is where
                // that refusal lives. Clamping it here silently solved a one-stack rig instead.
                'stacks' => (int)($statedStacks ?? 1),
                'suffix' => '',
            ]];
        }

        $byOwner = [];
        foreach ($specs as $spec) {
            if ($spec->category->value === 'speaker' && $spec->quantity > 0) {
                $byOwner[$spec->owner][] = $spec;
            }
        }
        ksort($byOwner);

        $combinations = self::ownerCombinations(array_keys($byOwner), $owners);

        $groups = [];
        foreach ($combinations as $subset) {
            $owned = [];
            foreach ($subset as $owner) {
                $owned = [...$owned, ...$byOwner[$owner]];
            }
            $groups[self::labelFor($subset, count($byOwner))] = $this->everySpeaker($owned);
        }

        $rigs = [];
        foreach ($groups as $label => $from) {
            foreach ([1, 2, 3] as $stacks) {
                // A stack cannot hold fewer than one device type, so asking for more stacks than types is not a rig
                // worth reporting a refusal for — it is arithmetic. Skipped silently, unlike a solve that fails.
                if (count($from) < $stacks) {
                    continue;
                }
                $rigs[] = ['from' => $from, 'stacks' => $stacks, 'suffix' => sprintf('-%s-%d', $label, $stacks)];
            }
        }

        return $rigs;
    }

    /**
     * Every non-empty combination of owners, smallest first — the inventory axis.
     *
     * **Combinations rather than the three fixed groups it used to be**, which is SWP-1's step 4. Before this the sweep
     * offered each owner alone and then everything at once, and the gap in the middle is a rig people actually build:
     * borrowing one system's subs to stand under another's tops is the normal shape of a shared gig, and the repository
     * supports lending gear on purpose. `sdwa5 + gmss` was simply not offered.
     *
     * Smallest first, so a reader sees the single-system rigs before the borrowed ones and the everything rig last —
     * the same ordering the old fixed list had, for the same reason: the single-system rigs are the ones most often
     * built.
     *
     * **`--owner` narrows the axis without collapsing the sweep**, exactly as `--align` narrows the alignment. It is the
     * one narrowing option here that is *not* a rig somebody named: `--from` and `--stacks` mean "this rig, at this
     * width", where `--owner=gmss --owner=sepp` still asks the sweep to walk the stack counts, the shapes, the
     * orientations and the width ladder. So it deliberately does not appear in {@see isSweep}.
     *
     * @param list<string> $owners every owner with speakers, already sorted
     * @param list<string> $stated what `--owner` named, validated by the caller
     * @return list<list<string>>
     */
    private static function ownerCombinations(array $owners, array $stated): array
    {
        if ($stated !== []) {
            // Intersected in the specs' own order rather than in the order they were typed, so `--owner=sepp
            // --owner=gmss` and the reverse name the same rig and write the same file.
            return [array_values(array_intersect($owners, $stated))];
        }

        $subsets = [];
        for ($mask = 1, $end = 1 << count($owners); $mask < $end; ++$mask) {
            $subset = [];
            foreach ($owners as $bit => $owner) {
                if (($mask & (1 << $bit)) !== 0) {
                    $subset[] = $owner;
                }
            }
            $subsets[] = $subset;
        }

        // Stable within a size, because the bitmask order is not the reading order: masks 1, 2, 4 are the singles but
        // 3 sits between 2 and 4.
        usort($subsets, static fn (array $a, array $b): int => count($a) <=> count($b));

        return $subsets;
    }

    /**
     * What this combination is called in a scene id.
     *
     * `all` for the whole inventory, the owner's own name for one owner, and the owners joined for anything between —
     * `stacked-gmss-sdwa5-2-turned-center` is both systems' gear, two stacks, subs on their sides.
     *
     * **`all` is only used where there is more than one owner to be all of**, which is not pedantry: in a repository
     * with a single owner that owner's subset *is* the whole inventory, and labelling it `all` would rename every
     * generated scene for a distinction that does not exist there.
     *
     * @param list<string> $subset
     */
    private static function labelFor(array $subset, int $owners): string
    {
        return $owners > 1 && count($subset) === $owners ? 'all' : implode('-', $subset);
    }

    /**
     * Whether this invocation is the sweep or one rig somebody named.
     *
     * Asked in two places — which rigs to try, and whether a wall outside the sub height band is a skip or a rig for
     * a different stage — and they have to agree, because the band and the width ladder are the sweep's judgement
     * about what is worth *shipping* and not a rule about how a stack may be built. Name a rig and you get it built
     * and warned about, at the width you asked for.
     */
    private function isSweep(InputInterface $input): bool
    {
        return $input->getOption('from') === []
            && $input->getOption('stacks') === null
            && !$input->getOption('per-owner');
    }

    /**
     * One candidate scene, or the reason there is none.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @param array{float, float} $at
     * @return array{yaml: string, cabinets: int, fingerprint: string, bandMiss: array{message: string, direction: int}|null}|string
     */
    private function build(
        array $devices,
        array $from,
        array $at,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        int $stacks,
        string $baseId,
        float $maxWidthM,
        InputInterface $input,
    ): array|string {
        $groups = $this->groups($devices, $from, $stacks, $input);
        if (is_string($groups)) {
            return $groups;
        }

        // **Mirror if possible, and do not lose gear to get it.** Two ways to deal the inventory out: split every
        // device evenly, which makes the stacks identical, or keep a device whole in the middle stack when there
        // are too few of it to go round. Both are tried and the one that stands up more cabinets wins — and on equal
        // cabinets, the shorter rig, which is what the loop below resolves rather than leaving it to strategy order.
        //
        // Scoring by cabinets rather than by "did it solve" is the whole trick. An even split that cannot be
        // carried does not fail — {@see solveGroup} drops the offending device and returns a perfectly good rig
        // without it, so a naive attempt-and-fall-back would take the mirrored 20-cabinet rig over the
        // 22-cabinet one every time. Two upright SKRAMs cannot be split, one each, and are kept together; the
        // same pair *turned* can, so it is, and both stacks come out the same rig.
        // **AND THE REMAINDER IS DEALT, NOT DROPPED.** An even share leaves whatever does not divide out of the rig
        // entirely — three Tecnares across two stacks came out one each with the third unplaced — so a third and
        // fourth strategy give the leftovers away outermost-first, through the same {@see outerShare} that keeps a
        // split as symmetric as the counts allow. The stacks stop being identical, which is the honest price of
        // putting every cabinet in the rig, and `--no-asymmetry` is how you decline to pay it.
        //
        // Dealing strategies come first so that a tie in cabinets placed resolves in their favour.
        $strategies = $input->getOption('no-asymmetry')
            ? [[true, false], [false, false]]
            : [[true, true], [false, true], [true, false], [false, false]];

        $blocks = [];
        $best = -1;
        $bestMiss = INF;
        $target = $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M;
        $firstProblem = null;

        foreach ($strategies as [$evenSplit, $placeAll]) {
            $attempt = [];
            $problem = null;

            foreach ($groups as $key => ['ids' => $ids, 'index' => $index, 'of' => $of]) {
                // Cast, because PHP turns an array key that looks like a number into one — `--stacks=2` without
                // `--per-owner` labels the groups "1" and "2", which arrive here as ints.
                $label = (string)$key;
                $block = $this->solveGroup(
                    $devices, $ids, $label, $mode, $shape, $style, $orientation, $maxWidthM, $input,
                    count($groups) > 1, $index, $of, $evenSplit, $placeAll,
                );
                if (is_string($block)) {
                    $problem = $label === '' ? $block : sprintf('%s: %s', $label, $block);
                    break;
                }
                $attempt[] = $block;
            }

            if ($problem !== null) {
                $firstProblem ??= $problem;
                continue;
            }

            // **CABINETS FIRST, THEN THE TARGET.** More cabinets always wins, because a cabinet in no rig at all is the
            // worse failure and that ordering is what the four strategies exist to exploit. But between two attempts
            // that place the *same* number there was nothing to choose, and the first one tried simply won — which is
            // how stating a `max_sub_height_m` could make a rig come out taller than not stating one. The extra height
            // was never the solve; it was this tie, resolved by strategy order rather than by the thing the ceiling was
            // asked about.
            //
            // **The tie was then broken by "shorter wins", and that is now `target_sub_height_m`.** Shorter was a
            // stand-in for a preference nobody had stated, and it is the wrong one: between a rig at 2.05 m and one at
            // 2.48 m out of the same cabinets, the second is the rig to build. Closest to the target wins in either
            // direction.
            //
            // **THE WORST STACK'S MISS, NOT THE TALLEST STACK'S HEIGHT**, and the difference is not academic. Scoring
            // the tallest stack alone is what "too high" means about a rig, so it was right while the tie-break was
            // "shorter wins" — but a target is a distance, and {@see bandMiss} refuses a rig when **any** stack falls
            // outside the band. Ranking on the tallest let an attempt win because its tall stack sat at 2.48 m while
            // its other stack dropped to 1.773 m and the whole rig was then refused: measured, it cost
            // `stacked-sdwa5-2-free-turned-centred-center`. Taking the worst miss keeps every stack near the aim,
            // which is what the aim is for.
            $placed = array_sum(array_map(static fn (StackBlock $b): int => $b->cabinets(), $attempt));
            $miss = max(array_map(
                static fn (StackBlock $b): float => abs($b->subHeightM() - $target),
                $attempt,
            ));

            if ($placed > $best || ($placed === $best && $miss < $bestMiss - 1e-9)) {
                $best = $placed;
                $bestMiss = $miss;
                $blocks = $attempt;
            }
        }

        if ($blocks === []) {
            return $firstProblem ?? 'no workable arrangement';
        }

        // Measured before the blocks are reordered, and reported rather than refused: the *sweep* decides whether a
        // wall outside the band is a skip or a rig for a different stage ({@see buildInBand}), while a scene somebody
        // asked for by name is built and warned about, which is what `StackChecks` has always done.
        $bandMiss = self::bandMiss($blocks);

        $blocks = self::byHeight($blocks, $mode);

        $clearance = (float)$input->getOption('clearance');
        $yaml = StackSceneWriter::yaml(
            id: 'placeholder',
            name: $this->describe($mode, count($blocks)),
            blocks: $blocks,
            at: $at,
            clearanceM: $clearance,
            command: $this->commandLine(
                $input, $mode, $shape, $style, $orientation, $stacks, $baseId, $maxWidthM, $from,
            ),
        );

        // Compiled before it is written. Anything the compiler calls an error means this arrangement is not
        // one of the possibilities, whatever the solver thought of the tiers.
        $compiled = $this->compileYaml($yaml, $devices);
        if (is_string($compiled)) {
            return $compiled;
        }

        return ['yaml' => $yaml, 'bandMiss' => $bandMiss] + $compiled;
    }

    /**
     * The device ids to build each stack from, in order.
     *
     * `--per-owner` groups by {@see DeviceSpec::$owner} and adds **no new concept**: who owns a cabinet is
     * already recorded, and for this collective it is exactly the split between the rigs — `sdwa5` runs the
     * Flexys, SKRAMs and M2122s, `sepp` the Achenbachs and 2-ways. A `system:` field would have duplicated it
     * value for value.
     *
     * `--stacks=N` then splits each group into that many, evenly, which is how a stereo pair is asked for.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @return array<string, array{ids: list<string>, index: int, of: int}>|string
     */
    private function groups(array $devices, array $from, int $stacks, InputInterface $input): array|string
    {
        if ($stacks < 1) {
            return '--stacks must be at least 1';
        }
        if ((float)$input->getOption('clearance') < 0.0) {
            return '--clearance must not be negative';
        }

        $split = SplitMode::tryFrom((string)$input->getOption('split'));
        if ($split === null) {
            return sprintf(
                "--split: unknown value '%s' (allowed: %s)",
                $input->getOption('split'),
                implode(', ', array_column(SplitMode::cases(), 'value')),
            );
        }

        $groups = [];
        if ($input->getOption('per-owner')) {
            foreach ($from as $id) {
                $groups[$devices[$id]->owner][] = $id;
            }
        } else {
            $groups[''] = $from;
        }

        // Splitting shares each device out rather than each *group*, so both halves of a stereo pair get some
        // of every cabinet instead of one taking the subs and the other the tops. The index and count travel
        // with the group so the share can be worked out **without losing the remainder**: three M2122s over two
        // stacks is 2 + 1, not one each with the third quietly unplaced.
        $dealt = [];
        foreach ($groups as $label => $ids) {
            // By type, the stack's own list *is* its share, so `of` is 1 and nothing is divided further. That is
            // what makes a by-type stack low: it holds two or three types where a by-count stack holds all nine.
            $perStack = $split === SplitMode::ByType && $stacks > 1
                ? $this->byType($devices, $ids, $stacks)
                : null;
            if (is_string($perStack)) {
                return $perStack;
            }

            for ($stack = 0; $stack < $stacks; ++$stack) {
                $key = $stacks === 1
                    ? (string)$label
                    : ($label === '' ? (string)($stack + 1) : $label.'-'.($stack + 1));
                $dealt[$key] = $perStack === null
                    ? ['ids' => $ids, 'index' => $stack, 'of' => $stacks]
                    : ['ids' => $perStack[$stack], 'index' => $stack, 'of' => 1];
            }
        }

        return $dealt;
    }

    /**
     * Whole device types dealt one stack each, balanced by how much **row** each type is.
     *
     * Balanced on `quantity × width` — the linear metres a type needs — and not on cabinet count, because that is
     * what decides how many rows a stack ends up with and so how tall it is. It is also the measure
     * {@see byFillOrder} already breaks its own ties on, so nothing new is being invented to rank cabinets.
     *
     * Longest-processing-time greedy: the biggest type goes to the emptiest stack, repeatedly. It is the standard
     * answer to this shape of problem and within a third of optimal for it, which is far inside the accuracy of
     * the cabinet dimensions themselves.
     *
     * **A stack of tops alone is folded away**, because nothing stands on the floor to hold them up: the tops go
     * to whichever stack carries the most sub, which is the one best able to take them. A stack of subs and no
     * tops is left alone — that is a sub wing, and a perfectly ordinary thing to build.
     *
     * Each stack's list is then put back into the fill order it arrived in, so the deepest cabinets still end up on
     * the floor and the tops still come last. `by-type` decides *which* stack a type is in; it never reorders one.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @return list<list<string>>|string one list per stack, or why the split cannot be made
     */
    private function byType(array $devices, array $ids, int $stacks): array|string
    {
        if (count($ids) < $stacks) {
            return sprintf(
                '--split=by-type: %d device types cannot fill %d stacks — each stack gets whole types, so there '
                .'has to be at least one each. Use --split=by-count, or fewer stacks',
                count($ids),
                $stacks,
            );
        }

        $bySize = $ids;
        usort(
            $bySize,
            static fn (string $a, string $b): int => $devices[$b]->quantity * $devices[$b]->dimensions->width
                <=> $devices[$a]->quantity * $devices[$a]->dimensions->width,
        );

        $assigned = array_fill(0, $stacks, []);
        $load = array_fill(0, $stacks, 0.0);
        foreach ($bySize as $id) {
            $target = (int)array_search(min($load), $load, true);
            $assigned[$target][] = $id;
            $load[$target] += $devices[$id]->quantity * $devices[$id]->dimensions->width;
        }

        $subLoad = [];
        foreach ($assigned as $stack => $own) {
            $subLoad[$stack] = 0.0;
            foreach ($own as $id) {
                if ($devices[$id]->subtype === 'sub') {
                    $subLoad[$stack] += $devices[$id]->quantity * $devices[$id]->dimensions->width;
                }
            }
        }
        $carries = (int)array_search(max($subLoad), $subLoad, true);
        foreach ($assigned as $stack => $own) {
            if ($subLoad[$stack] > 0.0 || $stack === $carries) {
                continue;
            }
            $assigned[$carries] = [...$assigned[$carries], ...$own];
            $assigned[$stack] = [];
        }

        $ordered = [];
        foreach ($assigned as $own) {
            $ordered[] = array_values(array_filter($ids, static fn (string $id): bool => in_array($id, $own, true)));
        }

        foreach ($ordered as $stack => $own) {
            if ($own === []) {
                return sprintf(
                    '--split=by-type: stack %d ends up empty — its only types were tops, which have nothing to '
                    .'stand on, and they went to the stack carrying the most sub. Use fewer stacks',
                    $stack + 1,
                );
            }
        }

        return $ordered;
    }

    /**
     * One group solved into a block, dropping whatever cannot be carried and saying so.
     *
     * The dropping is the judgement call worth naming. `--per-owner` puts the two SKRAMs in the `sdwa5` group,
     * and they cannot be in a stack at all: nothing shares their height so they cannot be mixed into a row,
     * and a row of the two of them is 1.240 m and carries nothing above it. Refusing the whole seventeen-cabinet
     * rig over that would be far less useful than building it and saying plainly what was left out — which is
     * also what the solver's own error already advises.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @return StackBlock|string
     */
    private function solveGroup(
        array $devices,
        array $ids,
        string $label,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        float $maxWidthM,
        InputInterface $input,
        bool $named,
        int $index,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
    ): StackBlock|string {
        // What the split does with the odd cabinets, named up front rather than left to be inferred from the tiers.
        $omitted = $this->splitRemainder($devices, $ids, $of, $evenSplit, $placeAll);

        // Try the whole group, then the group with one device removed, smallest holding first — the cabinet
        // most likely to be the odd one out is the one there are fewest of.
        $candidates = [$ids];
        $bySize = $ids;
        usort($bySize, static fn (string $a, string $b): int => $devices[$a]->quantity <=> $devices[$b]->quantity);
        foreach ($bySize as $drop) {
            $rest = array_values(array_filter($ids, static fn (string $id): bool => $id !== $drop));
            if ($rest !== []) {
                $candidates[] = $rest;
            }
        }

        $firstProblem = null;
        foreach ($candidates as $attempt) {
            $stack = $this->stackFor(
                $attempt, $input, $devices, $maxWidthM, $shape, $style, $orientation,
                2 * $index < $of - 1, $of === 1,
            );
            $problems = $stack->problems();
            if ($problems !== []) {
                return $problems[0];
            }

            $solved = StackSolver::solve(
                $this->inventoryFor($devices, $attempt, $index, $of, $evenSplit, $placeAll),
                $stack,
                // `center` is written as no alignment at all, so it arrives here as the mode rather than as null —
                // and `topRow` treats the two identically, which keeps the generated scene and its rebuild agreeing.
                $mode,
            );
            if ($solved['problems'] !== []) {
                $firstProblem ??= $solved['problems'][0];
                continue;
            }

            foreach (array_diff($ids, $attempt) as $dropped) {
                $omitted[$dropped] = 'LEFT OUT, it cannot be carried in this stack — '
                    .($firstProblem ?? 'no supported arrangement');
            }

            return new StackBlock(
                placementId: $named ? 'main-'.$label : 'main',
                label: $label,
                stack: $stack,
                tiers: $solved['tiers'],
                from: $attempt,
                warnings: $solved['warnings'],
                omitted: $omitted,
                align: $mode === LayoutMode::Center ? null : $mode,
            );
        }

        return $firstProblem ?? 'no workable arrangement';
    }

    /**
     * **One stack of each pair is mirrored**, and that is not a style choice: an unmirrored pair is the same rig
     * built twice, with both SKRAM mouths facing the same way and both tops rows in the same order. The rule
     * `2 * $index < $of - 1` pairs stack `i` with `of - 1 - i` and flips only the earlier of each pair, so the
     * later one — and the middle stack of an odd-numbered rig — solve exactly as they do without it.
     *
     * @param list<string> $ids
     * @param array<string, DeviceSpec> $devices
     */
    private function stackFor(
        array $ids,
        InputInterface $input,
        array $devices,
        float $maxWidthM,
        StackShape $shape = StackShape::Free,
        MirrorStyle $style = MirrorStyle::Alternate,
        ?StackOrientation $orientation = null,
        bool $mirror = false,
        bool $solo = false,
    ): Stack {
        // **WHICH CABINETS LIE DOWN, resolved here and nowhere else.** An orientation answers it from the specs — every
        // sub, or only the ones that get wider on their side ({@see StackOrientation}) — and a null orientation means
        // the caller stated the cabinets outright, which is what `--roll-mirror` is for and what every hand-written
        // invocation in this repository uses.
        //
        // The stated form stays because **no spec field says which cabinets are horn-loaded**, and adding one to drive a
        // rotation would be inventing a property to serve a layout. `subtype: sub` is a different claim, already
        // recorded and made for its own reasons, which is why an orientation may lean on it.
        /** @var list<string> $turned */
        $turned = $orientation?->rolls($devices, $ids) ?? $input->getOption('roll-mirror');

        // The widest top is the long throw; every narrower one is fill and is aimed at the near focus.
        $fills = $this->nearFieldFills($devices, $ids);

        $mixes = $this->readMixes($input);

        return new Stack(
            // Otherwise the shorthand form — one device id per entry. Anything wanting `count` or `align` is edited
            // into the written file afterwards; `mix_with` used to be too, and `--mix` exists because a hand edit to
            // a generated scene is undone the next time this command writes it.
            from: array_map(
                static fn (string $id): StackEntry => new StackEntry(
                    $id,
                    rollMirror: in_array($id, $turned, true) ? 90.0 : null,
                    mixWith: $mixes[$id] ?? [],
                    aim: in_array($id, $fills, true) ? self::NEAR_FOCUS : null,
                ),
                $ids,
            ),
            // The width the *caller* settled on, not the option: the sweep moves a rig up and down
            // {@see WIDTH_LADDER_M} to land its wall in the band, and reading the option here would silently
            // undo every rung of that.
            maxWidthM: $maxWidthM,
            minWidthM: $this->readFloat($input, 'min-width'),
            maxHeightM: $this->readFloat($input, 'max-height'),
            interfaceHeightM: (float)$input->getOption('interface-height'),
            gapM: (float)$input->getOption('gap'),
            mirror: $mirror,
            maxSubHeightM: $this->readFloat($input, 'max-sub-height'),
            targetSubHeightM: $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M,
            shape: $shape,
            mirrorStyle: $style,
            // **STILL ONLY A SOLO STACK, AND IT IS NOT FOR WANT OF THE BOUND.** The bound a multi-stack rig needs is
            // `max(0.0, clearance / 2 - gap)`, since {@see StackSceneWriter::centres} leaves exactly `--clearance`
            // between two envelopes and half of it each, less a working gap, keeps two rows sliding towards each other
            // apart. It is written out here rather than hidden, it is safe — no interpenetration appears anywhere with
            // it — and it is **still not an improvement**.
            //
            // Measured twice, the second time after GEO-2 closed, on the theory that a tops row which could no longer
            // be left hanging would change the answer. It did not. Switching it on takes the sweep from 11 scenes to
            // 10: it gains `stacked-gmss-2-center` and clears both `LEFT OUT` cabinets, and it loses **both**
            // `stacked-all-2-center` and `stacked-all-2-stereo` while doubling "nothing under it at all" from 3 to 6.
            //
            // The reason is not the one this comment used to give. It is not that the tops row cannot find its
            // support's plateau — {@see Gravity::reseat} settled that — it is that **a slide is bounded by its
            // neighbouring stack and by nothing above it**. A sub row slides for its own bearing and walks out from
            // under the tier it carries, which no bound expressed in stack clearance can see. The bound has to include
            // what stands on the row, and that is the missing piece rather than this line.
            slideSlackM: $solo ? INF : null,
        );
    }

    /**
     * The command that produced this scene, written out so the file can be regenerated without being read first.
     *
     * Reconstructed from what the command actually **used**, not echoed from the command line: `--from` is
     * expanded to the resolved device list rather than left implicit, and `--align` names the one mode this file
     * is, not the three that were tried. So the line reproduces this scene specifically, which is the only useful
     * thing it could say — and a default that changes later shows up here instead of being silently inherited.
     *
     * Value options are only emitted when they differ from their default, so an ordinary rig's line stays short
     * enough to read.
     *
     * **`--id` is emitted even though the file's own `id:` is right below it**, because the line has to be runnable
     * rather than merely informative: `build:all` replays it, and without the prefix every scene would regenerate as
     * `stacked-<mode>` and overwrite one file. The prefix is the id less the alignment suffix.
     *
     * @param list<string> $from
     */
    private function commandLine(
        InputInterface $input,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        int $stacks,
        string $baseId,
        float $maxWidthM,
        array $from,
    ): string {
        $parts = ['bin/console scene:stack'];

        // **THE SOLVED WIDTH, WRITTEN OUT WHATEVER IT IS.** `--max-width` is not in the loop below with the other
        // value options, because it is no longer merely echoed: the sweep chooses the stage a rig needs, and a replay
        // that inherited the default instead would rebuild a different rig — the one that missed the band.
        $parts[] = sprintf('--max-width=%s', rtrim(rtrim(sprintf('%.2f', $maxWidthM), '0'), '.'));

        foreach ([
            'min-width', 'max-height', 'interface-height', 'max-sub-height', 'target-sub-height', 'gap', 'at', 'split',
            'clearance',
        ] as $option) {
            $value = $input->getOption($option);
            if ($value !== null && (string)$value !== (string)$this->getDefinition()->getOption($option)->getDefault()) {
                $parts[] = sprintf('--%s=%s', $option, $value);
            }
        }
        // **THE MODE, NOT THE CABINETS IT RESOLVED TO.** `--orientation=turned` means "every sub", and writing the
        // resolved list out instead would freeze today's inventory into the file: measure a new sub, or correct one whose
        // height turns out to be under its width, and the replay would rebuild the rig the mode no longer asks for. The
        // stated form is only recorded where it is what the caller actually said.
        if ($orientation !== null) {
            $parts[] = '--orientation='.$orientation->value;
        } else {
            /** @var list<string> $turned */
            $turned = $input->getOption('roll-mirror');
            foreach ($turned as $id) {
                $parts[] = '--roll-mirror='.$id;
            }
        }
        /** @var list<string> $mixes */
        $mixes = $input->getOption('mix');
        foreach ($mixes as $mix) {
            $parts[] = '--mix='.$mix;
        }
        foreach (['per-owner', 'no-asymmetry'] as $flag) {
            if ($input->getOption($flag)) {
                $parts[] = '--'.$flag;
            }
        }
        // The swept axes are written out explicitly, because the whole point of the recorded line is that it
        // reproduces THIS scene rather than the sweep it came from.
        foreach ($from as $id) {
            $parts[] = '--from='.$id;
        }
        $parts[] = '--stacks='.$stacks;
        $parts[] = '--align='.$mode->value;
        $parts[] = '--shape='.$shape->value;
        $parts[] = '--mirror-style='.$style->value;
        // **THE SWEPT NAME, not the base `--id`.** The sweep builds a scene's name from the base plus which rig it is
        // — `stacked` + `-gmss-1` — and a replay runs narrowed, so it contributes no suffix of its own. Recording the
        // bare base made every replay write `stacked-center` over the top of one file while the 37 real ones went
        // stale, and the prune then removed them: 742 files, because the derived artifacts went with them.
        $parts[] = '--id='.$baseId;

        return implode(' ', $parts);
    }

    /**
     * `--mix=DEVICE:OTHER[,OTHER]` as a map of device id to the ids it shares a row with.
     *
     * The point of stating it on the command line rather than editing the written file is that a generated scene is
     * re-solved on every build and rewritten whenever this command runs — a `mix_with` edited in by hand survives
     * neither. And it is the one lever that lowers a stack: a mixed row is as tall as its tallest member, so merging
     * a short device into a tall device's row removes the short one's row from the sum.
     *
     * @return array<string, list<string>>
     */
    private function readMixes(InputInterface $input): array
    {
        $mixes = [];
        /** @var list<string> $stated */
        $stated = $input->getOption('mix');
        foreach ($stated as $pair) {
            [$device, $others] = array_pad(explode(':', $pair, 2), 2, '');
            $ids = array_values(array_filter(array_map('trim', explode(',', $others))));
            if ($device === '' || $ids === []) {
                continue;
            }
            $mixes[trim($device)] = [...($mixes[trim($device)] ?? []), ...$ids];
        }

        return $mixes;
    }

    /**
     * This stack's share of each device — **symmetric, and never split below what a row needs**.
     *
     * Two rules, both learned from what the even split produced.
     *
     * **A device too small to split is not split.** Fewer than two per stack cannot flank a mixed row
     * ({@see StackSolver} needs two to make a pair) and cannot be flanked into one either, so one SKRAM per
     * half left the row above it standing on 49.9 % of its own width and the solver dropped the pair
     * altogether — 180 kg of sub in no rig at all. The pair goes whole to the **middle** stack instead:
     * `intdiv($of, 2)`, which is the middle of three and the right-hand one of two.
     *
     * **The rest is shared evenly, and `$placeAll` decides what happens to the remainder.** Three M2122s over two
     * stacks split evenly are 1 + 1 with the third out of the rig: symmetric, and a stereo pair that really is a
     * pair — one side would otherwise get a wider top row, a different interface height and a different rig. But
     * a cabinet in no rig at all is its own kind of wrong, so `$placeAll` deals the remainder instead
     * ({@see dealAll}) and the rig comes out 1 + 2. Both are offered, the one that stands up more cabinets wins,
     * and `--no-asymmetry` withdraws the offer. Either way the odd cabinet is **named** by
     * {@see splitRemainder} rather than silently dropped or silently lopsided.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @return list<array{DeviceSpec, int}>
     */
    private function inventoryFor(
        array $devices,
        array $ids,
        int $index,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
    ): array {
        $middle = intdiv($of, 2);

        return array_map(
            static function (string $id) use ($devices, $index, $of, $middle, $evenSplit, $placeAll): array {
                $quantity = $devices[$id]->quantity;
                $share = intdiv($quantity, $of);

                // Two cases keep a device whole. **Fewer than one per stack** — there are simply not enough to
                // go round, and splitting three stacks' worth out of two 2-ways leaves every one of them out.
                // **Fewer than two per stack, for a sub** — a sub has to flank a mixed row or be flanked into
                // one, and neither works with one cabinet. Tops are exempt from the second: nothing stands on a
                // top, so one Tecnare per stack is a perfectly good top row, and applying the rule to them made
                // the middle stack hoard every one and left the outer stacks a row of subs with nothing above.
                if ($share < 1 || (!$evenSplit && $share < 2 && $devices[$id]->subtype === 'sub')) {
                    // A **sub** goes to the middle: weight belongs low and central, and a sub has to be part of a
                    // row that carries something. A **top** goes to the outermost stacks instead, because the tops
                    // too few to give every stack one are the small boxes — near-field fill, which belongs at the
                    // edges of the rig rather than stacked in its centre.
                    return $devices[$id]->subtype === 'sub'
                        ? [$devices[$id], $index === $middle ? $quantity : 0]
                        : [$devices[$id], self::outerShare($quantity, $index, $of)];
                }

                return [$devices[$id], $placeAll ? self::dealAll($quantity, $index, $of) : $share];
            },
            $ids,
        );
    }

    /**
     * How many of `$quantity` this stack gets when they are dealt **outermost first, in pairs**.
     *
     * Pairs, so the rig stays symmetric: `(0, of-1)`, then `(1, of-2)`, and so on. An odd one left at the end goes
     * to the middle stack of an odd-numbered rig rather than to one side, since a lone fill on the left is worse
     * than a lone fill in the centre. Two 2-ways across three stacks come out one, none, one.
     */
    private static function outerShare(int $quantity, int $index, int $of): int
    {
        for ($pair = 0; $quantity >= 2 && $pair < intdiv($of, 2); ++$pair) {
            if ($index === $pair || $index === $of - 1 - $pair) {
                return 1;
            }
            $quantity -= 2;
        }

        return $quantity > 0 && $of % 2 === 1 && $index === intdiv($of, 2) ? $quantity : 0;
    }

    /**
     * This stack's share when **every cabinet is placed** — the even share, plus its part of the remainder.
     *
     * The remainder goes through {@see outerShare}, so the leftovers land outermost-first in pairs and the split
     * stays as symmetric as the counts allow: three Tecnares across two stacks come out one and two rather than
     * one each with the third unplaced.
     *
     * `outerShare` leaves a single cabinet out when the rig has an **even** number of stacks, because there is no
     * middle stack to give it to. Placing everything means it has to go somewhere, and it goes to the stack just
     * right of the centre line — the same side {@see \App\Scene\Tier::mirrored} and
     * {@see \App\Scene\StackSolver::centred} put an odd cabinet, so a rig is asymmetric the same way throughout
     * rather than one way per rule.
     */
    private static function dealAll(int $quantity, int $index, int $of): int
    {
        $share = intdiv($quantity, $of);
        $rest = $quantity - $share * $of;
        $odd = $rest % 2 === 1 && $of % 2 === 0 && $index === intdiv($of, 2) ? 1 : 0;

        return $share + self::outerShare($rest, $index, $of) + $odd;
    }

    /**
     * The tops that are fill rather than long throw: every one narrower than the widest top in the stack.
     *
     * Not a new idea — {@see StackSolver::topRow} already centres the widest and puts "the smaller boxes, which
     * are fills, outboard of it". This gives them the *aim* to match, which a stack could not express before: one
     * `aim` covered every top it carried, so a 2-way beside an M2122 was thrown at the same far focus as the long
     * throw instead of at the front row.
     *
     * Measured on the rolled box like everything else, so a turned cabinet is judged as it will stand. A stack
     * with one kind of top has no fills — there is nothing for it to be narrower than, and calling the only top a
     * fill would aim the whole rig at the crowd two metres away.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @return list<string>
     */
    private function nearFieldFills(array $devices, array $ids): array
    {
        $tops = array_values(array_filter($ids, static fn (string $id): bool => $devices[$id]->subtype !== 'sub'));
        if (count($tops) < 2) {
            return [];
        }

        $widest = 0.0;
        foreach ($tops as $id) {
            $widest = max($widest, RolledBox::widthOf($devices[$id], 0.0));
        }

        return array_values(array_filter(
            $tops,
            static fn (string $id): bool => RolledBox::widthOf($devices[$id], 0.0) < $widest - 1e-9,
        ));
    }

    /**
     * What an even split leaves over, per device, so the report can name it instead of it just being absent.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @return array<string, string> device id => why some are not in any stack
     */
    private function splitRemainder(
        array $devices,
        array $ids,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
    ): array {
        if ($of < 2) {
            return [];
        }

        $left = [];
        foreach ($ids as $id) {
            $quantity = $devices[$id]->quantity;
            $share = intdiv($quantity, $of);

            // The same condition {@see inventoryFor} keeps a device whole on: those are all placed, in one
            // stack, so there is no remainder to report. Guarding on the share alone skipped the tops with one
            // per stack, which is exactly the case this exists for — the odd third M2122.
            if ($share < 1) {
                continue;
            }

            // A sub kept whole because it could not be split. Said out loud, because the header otherwise shows
            // both SKRAMs in one stack and gives no hint that the other arrangement was tried and refused — which
            // is the single thing about a split rig people ask about.
            if (!$evenSplit && $share < 2 && $devices[$id]->subtype === 'sub') {
                $left[$id] = sprintf(
                    'KEPT TOGETHER, all %d in one stack — %d stacks would take one each, and one on its own cannot be '
                    .'flanked into a row that carries anything: the row above it ends up half off its support. '
                    .'Turning them makes the split work, which is what the -turned rig does',
                    $quantity,
                    $of,
                );
                continue;
            }

            $over = $quantity - $share * $of;
            if ($over < 1) {
                continue;
            }

            // Placed but unevenly, which is worth saying for the same reason leaving it out was: a reader comparing
            // two stacks needs to know the difference is the remainder rather than a solve that went differently.
            $left[$id] = $placeAll
                ? sprintf(
                    'SPLIT UNEVENLY, %d of %d over %d stacks — %d each and the remaining %d dealt outermost first, '
                    .'so the stacks are not identical. --no-asymmetry leaves them out instead',
                    $over,
                    $quantity,
                    $of,
                    $share,
                    $over,
                )
                : sprintf(
                    'LEFT OUT, %d of %d — %d stacks take %d each, and an odd cabinet would make one stack '
                    .'a different rig from the others',
                    $over,
                    $quantity,
                    $of,
                    $share,
                );
        }

        return $left;
    }

    /**
     * What the scene actually resolves to — how many cabinets, and a fingerprint of where they all end up —
     * or the first error it produces.
     *
     * The fingerprint is the **solved geometry**, not the file, and that distinction is the whole point of
     * it: two arrangements can differ in what they *say* and still be the same rig. Once every top shares one
     * row, that row is mixed, `align` has nothing left to distribute, and `center`/`block`/`stereo` all come
     * out identical — three files implying a choice that does not exist.
     *
     * @param array<string, DeviceSpec> $devices
     * @return array{cabinets: int, fingerprint: string}|string
     */
    private function compileYaml(string $yaml, array $devices): array|string
    {
        try {
            /** @var array<string, mixed> $data */
            $data = Yaml::parse($yaml);
            $scene = SceneSpec::fromArray($data, 'generated');
        } catch (\Throwable $e) {
            return 'the generated scene does not parse: '.$e->getMessage();
        }

        $result = (new SceneCompiler($devices))->compile($scene);
        $errors = Violation::errorsIn($result['violations']);
        if ($errors !== []) {
            return $errors[0]->message;
        }

        $floating = self::floating($result['placed']);
        if ($floating !== null) {
            return $floating;
        }

        // **AND NOTHING INSIDE ANYTHING ELSE.** Same reasoning as the floating check above and the same source of
        // truth: `ShippedScenesTest` sweeps every scene for interpenetration, so a candidate that fails it is not one
        // of the possibilities — writing it only moves the failure one command later. The compiler cannot see this on
        // its own: `on:` reads a top face and nothing downstream compares two finished placements.
        ['separation' => $separation, 'pair' => $pair] = Interpenetration::worst($result['placed']);
        if ($separation < -self::CONTACT_TOLERANCE_M) {
            return sprintf(
                '%s would be %.4f m inside each other — the compiler allows it and the shipped-scene sweep does not',
                $pair,
                -$separation,
            );
        }

        $marks = [];
        foreach ($result['placed'] as $entry) {
            $position = $entry->liftedPosition();
            $marks[] = sprintf(
                '%s@%.6F,%.6F,%.6F/%.4F',
                $entry->device->id,
                $position[0],
                $position[1],
                $position[2],
                $entry->yawDeg(),
            );
        }
        sort($marks);

        return ['cabinets' => count($result['placed']), 'fingerprint' => implode('|', $marks)];
    }

    /**
     * The first cabinet standing on nothing, or null when every one of them is over something.
     *
     * **The compiler does not catch this and the shipped-scene sweep does**, which is exactly the gap this closes.
     * A tops row narrower than the tier below it can still land off the end of it once the row is split into runs by
     * a stepped support — `all-speakers-three-turned` came out with a top 1.261 m up over open air, compiling
     * cleanly and failing the sweep. This command's whole promise is that "a generator that emits a scene the
     * compiler rejects is worse than no generator", and a scene the *sweep* rejects is no better: the failure just
     * surfaces one command later.
     *
     * Deliberately the narrow question — is there something under it, in both plan axes — and not how far it would
     * tilt. Tilt is the solver's question, enforced by {@see \App\Scene\Stability} against the tier it knows is
     * below; re-deriving it from world boxes alone needs "the tier immediately below", which they do not tell you.
     * The same reasoning is written out at length in `ShippedScenesTest`, and this is the same check from the same
     * data so the two cannot drift into disagreeing.
     *
     * @param list<\App\Scene\PlacedDevice> $placed
     */
    private static function floating(array $placed): ?string
    {
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            if ($box['min'][2] < self::CONTACT_TOLERANCE_M || $entry->flyPoint !== null) {
                continue;
            }

            foreach ([0, 1] as $axis) {
                if (self::coveredFraction($entry, $placed, $axis) > 0.0) {
                    continue;
                }

                return sprintf(
                    'a %s would stand at %.3f m with nothing under it across %s — the compiler allows it and the '
                    .'shipped-scene sweep does not, so it is not one of the possibilities',
                    $entry->device->id,
                    $box['min'][2],
                    $axis === 0 ? 'x' : 'y',
                );
            }
        }

        return null;
    }

    /**
     * How much of a cabinet's extent along `$axis` has something level underneath it, as a fraction.
     *
     * Overlapping supports are merged rather than summed, so two neighbours a cabinet bridges count the span once.
     *
     * @param list<\App\Scene\PlacedDevice> $placed
     */
    private static function coveredFraction(\App\Scene\PlacedDevice $entry, array $placed, int $axis): float
    {
        $box = $entry->worldBox();
        $extent = $box['max'][$axis] - $box['min'][$axis];
        if ($extent <= 0.0) {
            return 0.0;
        }

        $spans = [];
        foreach ($placed as $other) {
            if ($other === $entry) {
                continue;
            }
            $under = $other->worldBox();
            if (abs($under['max'][2] - $box['min'][2]) > self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $overlap = [];
            foreach ([0, 1] as $plan) {
                $overlap[$plan] = min($box['max'][$plan], $under['max'][$plan])
                    - max($box['min'][$plan], $under['min'][$plan]);
            }
            if ($overlap[0] <= self::CONTACT_TOLERANCE_M || $overlap[1] <= self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $spans[] = [
                max($box['min'][$axis], $under['min'][$axis]),
                min($box['max'][$axis], $under['max'][$axis]),
            ];
        }

        usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $covered = 0.0;
        $reach = -INF;
        foreach ($spans as [$lo, $hi]) {
            $lo = max($lo, $reach);
            if ($hi > $lo) {
                $covered += $hi - $lo;
                $reach = $hi;
            }
        }

        return $covered / $extent;
    }

    /**
     * Drops arrangements that place their cabinets in exactly the same spots as an earlier one.
     *
     * `stereo` on an odd tier of three resolves identically to `block` — the leftover cabinet centres on
     * `at` and the outer two land on the envelope edges — and writing that rig twice under two names would
     * suggest a choice that does not exist.
     *
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
     * @param array<string, string> $skipped
     * @return array<string, array{yaml: string, cabinets: int, fingerprint: string}>
     */
    private function deduplicate(array $candidates, array &$skipped): array
    {
        $kept = [];
        $seen = [];

        foreach ($candidates as $name => $candidate) {
            $fingerprint = $candidate['fingerprint'];
            $existing = array_search($fingerprint, $seen, true);
            if ($existing !== false) {
                $skipped[$name] = sprintf('the same rig as %s', $existing);
                continue;
            }

            $seen[$name] = $fingerprint;
            $kept[$name] = $candidate;
        }

        return $kept;
    }

    /**
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
     */
    private function emit(array $candidates, bool $dryRun, bool $force): int
    {
        $exit = self::SUCCESS;

        // GENERATED SCENES GO IN THEIR OWN SUBDIRECTORY, and this is the only place that decides it — nothing reads
        // them by path, because {@see SceneLoader::files} is recursive and an id has always been the file's
        // basename. Written next to the hand-written scenes, a regenerated file silently rewrote a comment table
        // somebody had read, and there was nothing in either file saying which kind it was.
        $directory = $this->scenesDir().'/'.SceneLoader::GENERATED;

        foreach ($candidates as $name => $candidate) {
            $path = $directory.'/'.$name.'.yaml';
            $yaml = str_replace('id: placeholder', 'id: '.$name, $candidate['yaml']);

            if ($dryRun) {
                $this->io->section($name.'.yaml');
                $this->io->writeln($yaml);
                continue;
            }
            if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
                $this->io->error('Could not create '.$this->relative($directory));

                return self::FAILURE;
            }
            if (file_exists($path) && !$force) {
                $this->io->text(sprintf('  <comment>exists</comment>  %s — pass --force to overwrite', $this->relative($path)));
                $exit = self::FAILURE;
                continue;
            }
            if (file_put_contents($path, $yaml) === false) {
                $this->io->error('Could not write '.$this->relative($path));

                return self::FAILURE;
            }

            $this->io->text(sprintf('  <info>wrote</info>   %-44s %d cabinets', $this->relative($path), $candidate['cabinets']));
        }

        if (!$dryRun && $exit === self::SUCCESS) {
            $this->io->newLine();
            $this->io->text('Now: <comment>bin/console scene:build</comment>');
        }

        return $exit;
    }

    /**
     * Every speaker in the library, subs before tops — the order the fill needs and the one nobody should
     * have to type out.
     *
     * @param list<DeviceSpec> $specs
     * @return list<string>
     */
    private function everySpeaker(array $specs): array
    {
        $subs = [];
        $tops = [];
        foreach ($specs as $spec) {
            if ($spec->category->value !== 'speaker' || $spec->quantity < 1) {
                continue;
            }
            $spec->subtype === 'sub' ? $subs[] = $spec : $tops[] = $spec;
        }

        usort($subs, self::byFillOrder());
        usort($tops, self::byFillOrder());

        return array_map(static fn (DeviceSpec $s): string => $s->id, [...$subs, ...$tops]);
    }


    /**
     * Heaviest first, so the deepest cabinets end up on the floor carrying everything.
     *
     * **THE KEY IS WEIGHT, AND THE REASON IS THAT FREQUENCY IS NOT ALWAYS KNOWN.** This used to sort on the driven
     * low corner of `audio.passband_hz` and fall back to `quantity × width` when a spec had none — which reads as
     * "the most numerous cabinet on the floor" and put the 40 kg IQ subs under the 220 kg wall basses, with all four
     * GMSS subs above six Achenbachs. Nine of our ten speakers have no passband at all, so the fallback was doing
     * almost all of the work and it was doing it on a row-making heuristic rather than on anything physical.
     *
     * Weight is not a guess dressed up as data. It is stated for every cabinet, it is what
     * {@see \App\Scene\Gravity} and three separate comments in {@see \App\Scene\StackSolver} already appeal to —
     * "weight belongs low and central" — and it agrees with the frequency order everywhere both are known:
     *
     * * **our measured gear**: SKRAM 90 kg, Flexy 85, Achenbach 50 — the same order as SKRAM 15 Hz, Flexy 38-200,
     *   Achenbach 38-1500, including the Flexy-versus-Achenbach case the old high-corner tiebreak existed for
     * * **GMSS's own rig**: wall bass 220, mid bass 120, nuke 58, IQ sub 40 — which is exactly how the builder
     *   stacks them, wall basses on the ground with the mid bass across them, a nuke on the ground with the IQ subs
     *   on it. That arrangement was described to us, not derived, so it is a real check rather than a circular one
     *
     * Two independent agreements is why this is the key rather than a fallback. **The passband is still consulted**,
     * as the tiebreak, so a stated frequency decides between cabinets of equal mass — and if a light, deep sub ever
     * turns up and the two rules disagree, the frequency is the one to believe and this is the comment that has to
     * change.
     *
     * @return callable(DeviceSpec, DeviceSpec): int
     */
    private static function byFillOrder(): callable
    {
        return static function (DeviceSpec $a, DeviceSpec $b): int {
            $mass = ($b->weightKg ?? 0.0) <=> ($a->weightKg ?? 0.0);
            if ($mass !== 0) {
                return $mass;
            }

            $low = ($a->passband?->orderingLowHz() ?? INF) <=> ($b->passband?->orderingLowHz() ?? INF);
            if ($low !== 0) {
                return $low;
            }

            $high = ($a->passband?->highHz ?? INF) <=> ($b->passband?->highHz ?? INF);
            if ($high !== 0) {
                return $high;
            }

            return $b->quantity * $b->dimensions->width <=> $a->quantity * $a->dimensions->width;
        };
    }

    /**
     * The shapes to write a scene for, or null when one is misspelled.
     *
     * Both by default, the same way `--align` defaults to all three modes: the two shapes answer opposite questions
     * — {@see StackShape::Pyramid} takes the silhouette and the height, {@see StackShape::Free} keeps the deepest and
     * heaviest cabinets on the floor — and which matters more is the sort of thing to decide by looking at two
     * renders rather than by reading a docblock.
     *
     * @param list<string> $raw
     * @return list<StackShape>|null
     */
    private function readShapes(array $raw): ?array
    {
        if ($raw === []) {
            return StackShape::cases();
        }

        $shapes = [];
        foreach ($raw as $value) {
            $shape = StackShape::tryFrom($value);
            if ($shape === null) {
                $this->io->error(sprintf(
                    "--shape: unknown value '%s' (allowed: %s)",
                    $value,
                    implode(', ', array_column(StackShape::cases(), 'value')),
                ));

                return null;
            }
            $shapes[] = $shape;
        }

        return $shapes;
    }

    /**
     * @param list<string> $raw
     * @return list<LayoutMode>|null
     */
    private function readModes(array $raw): ?array
    {
        if ($raw === []) {
            return LayoutMode::cases();
        }

        $modes = [];
        foreach ($raw as $value) {
            $mode = LayoutMode::tryFrom($value);
            if ($mode === null) {
                $this->io->error(sprintf(
                    "--align: unknown value '%s' (allowed: %s)",
                    $value,
                    implode(', ', array_column(LayoutMode::cases(), 'value')),
                ));

                return null;
            }
            $modes[] = $mode;
        }

        return $modes;
    }

    /**
     * @return array{float, float}|null
     */
    private function readAt(string $raw): ?array
    {
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return [(float)$parts[0], (float)$parts[1]];
    }

    private function readFloat(InputInterface $input, string $option): ?float
    {
        $value = $input->getOption($option);

        return $value === null ? null : (float)$value;
    }


    private function describe(LayoutMode $mode, int $blocks): string
    {
        return sprintf(
            'Solved rig — %s, tiers %s',
            $blocks === 1 ? 'one stack' : $blocks.' stacks side by side',
            match ($mode) {
                LayoutMode::Center => 'centred',
                LayoutMode::Block => 'justified',
                LayoutMode::Stereo => 'split left and right',
            },
        );
    }
}
