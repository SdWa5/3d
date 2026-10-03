<?php

declare(strict_types=1);

namespace App\Command;

use App\Process\Parallel;
use App\Scene\BridgedTops;
use App\Scene\CandidateCheck;
use App\Scene\Fault;
use App\Scene\Feasibility;
use App\Scene\Interpenetration;
use App\Scene\LayoutMode;
use App\Scene\LowEndBias;
use App\Scene\MirrorStyle;
use App\Scene\MouthMode;
use App\Scene\RigAim;
use App\Scene\RolledBox;
use App\Scene\RoomBounds;
use App\Scene\SceneCompiler;
use App\Scene\SceneLayout;
use App\Scene\SceneLoader;
use App\Scene\SharedTops;
use App\Scene\SplitMode;
use App\Scene\Stack;
use App\Scene\StackBackdrop;
use App\Scene\StackBlock;
use App\Scene\StackChecks;
use App\Scene\StackDeal;
use App\Scene\StackEntry;
use App\Scene\StackOrientation;
use App\Scene\StackSceneWriter;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\StackTops;
use App\Scene\SweepAxes;
use App\Scene\SystemGrouping;
use App\Scene\SystemSplit;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
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
     * Every candidate was refused for a stated reason, so there is nothing to write and nothing went wrong.
     *
     * **Non-zero, because a human who asked for a rig and got none needs the shell to say so**, and distinct from
     * `FAILURE`, because a caller replaying a *recorded* command needs to tell "this rig is no longer one of the
     * possibilities" apart from "the command broke". Those two were the same exit code, and
     * {@see BuildAllCommand::replayRecorded} could only read the second meaning: one rig that stopped
     * solving aborted the whole regenerate stage, so `build:all` could not be run twice in a row. That is **TOOL-7**,
     * and the file that surfaced it is `stacked-all--------1-pyramid-mixed---alternate-stereo`.
     *
     * The refusals are already printed one per candidate above this, so the exit code adds a category rather than an
     * explanation.
     */
    public const NOTHING_TO_WRITE = 2;

    /**
     * A ceiling on accident, not on ambition.
     *
     * 24 was right while the command wrote one rig per alignment. The default is now a **sweep** — every combination of
     * owners, by one, two and three stacks, each in all three shapes, all seven orientation/mirror pairs and all three
     * alignments — which tries ~1200 candidates on the current inventory and writes **396** of them, the rest being
     * duplicates and named refusals. So the limit has to clear that with room for the gear list to grow, while still
     * catching the case it exists for: an axis added by mistake, where the count goes to thousands rather than hundreds.
     *
     * **396 rather than 150 because the sub height band stopped refusing**, which is where the headroom went: CVR-7
     * turned 551 refused candidates into written rigs in one change. What is left of the 600 is CVR-5's.
     *
     * **Raised deliberately, and that is the point of it.** 80 fitted the 61 scenes the orientation axis wrote and the
     * owner combinations took it straight past — which is exactly what should happen, because the raise is where
     * somebody looks at the number and decides it is the output they meant.
     *
     * **1500 because SWP-2 arrived and 800 bound, and this time the number is measured before the raise rather
     * than after.** The sweep writes **976**: the 543 `pooled` rigs that existed, plus 433 where the systems stand
     * apart. Stated by the owner, who was shown the count and asked for room for SWP-2's third value as well — a
     * `subs apart, tops shared` grouping is not buildable yet and would add roughly another 400.
     *
     * **The two raises before this one are a lesson in what a fuse is worth.** 600 went to 800 on an argument that
     * turned out to be arithmetic on the wrong quantity — 144 *refusals* read as 144 scenes, where the deduplication
     * collapsed them to 60 — so the raise was never needed and the sweep fitted 600 all along. The docblock this
     * replaces said the raise is "where somebody looks at the number and decides it is the output they meant". It
     * only works if the number is the one that gets written.
     *
     * **A fuse rather than a cap**: over the limit the command writes *nothing* and says so. Truncating to the first N
     * would read as "that is every possibility" when it is not, which is the same reason every refusal is printed.
     */
    private const DEFAULT_MAX_SCENES = 2500;

    /**
     * The top of the 2–3 m band a sub/top transition should sit in.
     *
     * **A default rather than a required option, because the goal is that one command in its default settings
     * produces every sensible rig.** 3.0 m is safe to default because a ceiling is monotone at the solver level
     * (measured across six inventories and both shapes: 13 shorter, 11 unchanged, none taller) once the strategy tie
     * is broken on height rather than on the order the strategies happen to be tried in.
     *
     * **And it is an aim rather than a gate**, which is the whole of CVR-7: a rig that cannot get under it says by
     * how much, on the file it writes, and is built anyway. See {@see bandMiss}.
     *
     * There is deliberately **no companion width default**. A stage width nobody stated used to be applied to every
     * generated scene, which is the one bound that could throw a rig away for a reason nobody had given — see
     * {@see $maxWidthM} on {@see build}.
     */
    private const DEFAULT_MAX_SUB_HEIGHT_M = 3.0;

    /**
     * The scene files this run actually wrote, absolute, in the order they were written.
     *
     * **The missing half of pruning generated scenes, and it is deliberately a fact rather than a guess.**
     * {@see BuildAllCommand::prune} explains why two attempts at deciding staleness by timestamp both
     * destroyed the scene set — `filemtime()` is whole seconds, `microtime(true)` is fractional, so a file written in
     * the same second as the run started reads as older than the run — and concludes that automating it "needs
     * `scene:stack` to report the paths it wrote, not a cleverer clock". This is that report.
     *
     * Reset at the start of every run and read by the caller afterwards, so a `build:all` that replays hundreds of
     * recorded commands accumulates the union rather than seeing only the last one. Empty after a `--dry-run`, which
     * is correct: a dry run wrote nothing, so nothing may be judged stale against it.
     *
     * @var list<string>
     */
    public array $written = [];

    /**
     * The focus a near-field fill is turned towards — one of the two {@see StackSceneWriter} always writes.
     *
     * Named here rather than in {@see Stack} because only the writer guarantees the focus exists: a hand-written
     * scene need not define `near`, and aiming at a focus that is not there is an error.
     */
    private const NEAR_FOCUS = 'near';

    protected function configure(): void
    {
        $this
            ->setName('scene:stack')
            ->setDescription('Solve a rig from constraints and write it out as scene files')
            ->addOption('from', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids, low frequency first. Default: every speaker, subs before tops')
            ->addOption('max-width', null, InputOption::VALUE_REQUIRED, 'How wide the stage or truss allows, in metres. Unstated means no limit at all')
            ->addOption('min-width', null, InputOption::VALUE_REQUIRED, 'Floor on the widest tier, in metres')
            ->addOption('max-height', null, InputOption::VALUE_REQUIRED, 'Ceiling or rigging limit, in metres')
            ->addOption('interface-height', null, InputOption::VALUE_REQUIRED, 'Height the tops must clear, in metres', (string) Stack::DEFAULT_INTERFACE_HEIGHT_M)
            ->addOption('max-sub-height', null, InputOption::VALUE_REQUIRED, 'Ceiling on the sub/top transition, in metres. Lets a row hold several device types', (string) self::DEFAULT_MAX_SUB_HEIGHT_M)
            ->addOption('target-sub-height', null, InputOption::VALUE_REQUIRED, 'The sub/top transition to aim at, in metres. A preference between the two bounds, never a refusal', (string) Stack::DEFAULT_TARGET_SUB_HEIGHT_M)
            ->addOption('split', null, InputOption::VALUE_REQUIRED, 'by-count (a share of every device to every stack) or by-type (whole types per stack, which comes out lower)', SplitMode::ByCount->value)
            ->addOption('no-asymmetry', null, InputOption::VALUE_NONE, 'Leave the odd cabinets out rather than giving one stack more than another')
            ->addOption('gap', null, InputOption::VALUE_REQUIRED, 'Working gap between neighbours, in metres', '0.02')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Where the rig is centred, as X,Y', '-0.302,0')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Base scene id', 'stacked')
            ->addOption('mouths', null, InputOption::VALUE_REQUIRED, 'paired (turn horn subs so their mouths meet, positions unchanged) or free (leave every roll as dealt)', MouthMode::Paired->value)
            ->addOption('low-end', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Where the lowest cabinets belong: central (on the centre line) or low (on the floor). Default: both')
            ->addOption('folders', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Axes to make directory levels instead of name fields: inventory, stacks, systems, shape, orientation, mirror-style, align, feasibility. Default: inventory')
            ->addOption('into', null, InputOption::VALUE_REQUIRED, 'Subdirectory of scenes/generated/ to write into. Default: the inventory being swept')
            ->addOption('align', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'center, block or stereo. Default: all three')
            ->addOption('shape', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'pyramid, free, v, tower or mixed. Default: all five')
            ->addOption('mirror-style', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What a turned row does with its odd cabinet: alternate (side flips per row), centred (unrolled in the middle) or column (same side every row). Default: all three where something is rolled')
            ->addOption('orientation', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which cabinets lie on their sides: upright (none), turned (every sub) or mixed (only where it makes them wider). Tops never roll. Default: all three')
            ->addOption('roll-mirror', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids to lay on their sides, mirrored about the centre line. Repeatable')
            ->addOption('mix', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Share a row: DEVICE:OTHER[,OTHER]. Repeatable. Lowers a stack by merging tiers')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Build from these owners\' gear only. Default: sweep every combination of them')
            ->addOption('event', null, InputOption::VALUE_REQUIRED, 'Event id from events/. Applies its hard room limits, how each system is set up and what each system brings')
            ->addOption('room-width', null, InputOption::VALUE_REQUIRED, 'Hard width limit for the whole compiled rig, in metres. A rig too wide is built again with narrower stacks first')
            ->addOption('room-height', null, InputOption::VALUE_REQUIRED, 'Hard ceiling for the whole compiled rig, in metres')
            ->addOption('system-interface', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'OWNER:METRES. Interface for walls of this owner\'s subs')
            ->addOption('system-target', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'OWNER:METRES. Sub-height target for walls of this owner\'s subs')
            ->addOption('system-orientation', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'OWNER:MODE. How this owner\'s cabinets are set up (upright, turned, mixed), whatever --orientation sweeps')
            ->addOption('system-low-end', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'OWNER:MODE. Where this owner\'s lowest cabinets belong (low, central), whatever --low-end sweeps')
            ->addOption('stand', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids that stand as measured under any orientation. Repeatable')
            ->addOption('backdrop', null, InputOption::VALUE_REQUIRED, 'TRUSS:SEGMENTS:TOWER. The truss a brought deco device hangs from, behind the rig')
            ->addOption('quantity', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'DEVICE:COUNT — build with this many of a device instead of the number its spec states. 0 leaves it at home. Repeatable')
            ->addOption('per-owner', null, InputOption::VALUE_NONE, 'One stack per system, side by side, instead of one rig from everything')
            ->addOption('order', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'System names, left to right, overriding the tallest-in-the-middle rule. Repeatable or comma-separated')
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'NAME:owner+owner — owners that are one sound system. Repeatable. Default: sdwa5 and sepp are `ours`')
            ->addOption('systems', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'How separately the systems stand: pooled, systems-apart or tops-shared (each system\'s subs, with every top dealt across those walls). Default: all three where there is more than one owner')
            ->addOption('stacks', null, InputOption::VALUE_REQUIRED, 'Split each group into this many stacks. Default: sweep 1, 2 and 3')
            ->addOption('clearance', null, InputOption::VALUE_REQUIRED, 'Air between neighbouring stacks, in metres', '0.5')
            ->addOption('max-scenes', null, InputOption::VALUE_REQUIRED, 'Refuse to write more than this many', (string) self::DEFAULT_MAX_SCENES)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the scenes instead of writing them')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing scene file')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'Processes to solve the sweep in — 1 is serial, 0 is one per core', '0');
    }

    /**
     * The counts this run overrode, device id to units, sorted by id.
     *
     * **A property rather than an argument threaded through the solve**, because the only thing that needs it is the
     * recorded command line at the far end of {@see build}, and the eight frames in between have no business
     * knowing a count was overridden at all — that is the point of rewriting the specs up front.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * Every cabinet the event's systems bring at least one of, by the system that brings it, whether or not the count
     * differs from the spec. {@see $counts} drops a count that restates the spec, so it cannot answer whether the
     * brought cabinets are in the rig at all, and that is what this is for.
     *
     * @var array<string, string>
     */
    private array $brought = [];

    /**
     * Every option's declared default, by name — what {@see RecordedCommand} compares a stated value against.
     *
     * **Read off the `InputDefinition` once, in the parent, and passed down.** The recorded line only writes a
     * value out when it differs from the default, so this map decides the contents of 2072 files; and the line is
     * assembled inside a forked child, where reaching back for `$this->getDefinition()` would work by accident of
     * the fork copying the object rather than by design.
     *
     * @var array<string, mixed>
     */
    private array $defaults = [];

    /**
     * Which owners are one sound system for this run.
     *
     * Resolved in `execute()` and therefore before {@see Parallel} forks, for the same reason
     * {@see $counts} is: {@see StackDeal::groups} runs in a child and a child cannot ask the parent anything.
     */
    private SystemGrouping $grouping;

    /** Event limits resolved before the sweep forks. */
    private SceneEventOptions $eventOptions;

    /** The deco device an event or `--quantity` brings, or null when it brings none. */
    private ?DeviceSpec $deco = null;

    /** What {@see $deco} hangs from, resolved from {@see SceneEventOptions::$backdrop} once a deco is brought. */
    private ?StackBackdrop $backdrop = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        $devices = [];
        foreach ($specs as $spec) {
            $devices[$spec->id] = $spec;
        }

        try {
            $this->eventOptions = SceneEventOptions::resolve($input, $this->projectDir().'/events', $devices);
        } catch (InvalidSpecException $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        }

        // **APPLIED BEFORE ANYTHING READS A QUANTITY, WHICH IS WHY IT IS THE FIRST THING THAT HAPPENS.** A count
        // reaches the solver through a dozen paths — the fill order, the by-type balance, the owner list, the
        // silhouette widths — and threading an override down all of them would be a dozen chances to miss one. The
        // specs are rewritten here instead, once, and everything downstream goes on reading `->quantity` in
        // ignorance. See {@see \App\Spec\Event} for why the count lives outside the spec file at all.
        $layout = SceneLayout::of((array) $input->getOption('folders'));
        if (is_string($layout)) {
            $this->io->error($layout);

            return self::FAILURE;
        }

        $grouping = SystemGrouping::of((array) $input->getOption('group'));
        if (is_string($grouping)) {
            $this->io->error($grouping);

            return self::FAILURE;
        }
        $this->grouping = $grouping;

        $counts = $this->countOverrides($input, $devices);
        if (is_string($counts)) {
            $this->io->error($counts);

            return self::FAILURE;
        }
        foreach ($counts as $id => $count) {
            $devices[$id] = $devices[$id]->withQuantity($count);
        }
        $this->counts = $counts;

        // **A DECO DEVICE IS HUNG, NOT STACKED.** It never enters the sweep, so it is taken out of the inventory
        // check below and handed to the backdrop instead. Without a truss to hang it from it is a refusal, because
        // a panel left out of the picture would look like a rig that had room for it.
        $decos = array_keys(array_filter(
            $counts,
            static fn (int $count, string $id): bool => $count > 0 && StackBackdrop::isDeco($devices[$id]),
            ARRAY_FILTER_USE_BOTH,
        ));
        $this->brought = array_diff_key($this->brought, array_flip($decos));
        if (count($decos) > 1) {
            $this->io->error(sprintf('one backdrop hangs one deco device, and this run brings %s', implode(', ', $decos)));

            return self::FAILURE;
        }
        $this->deco = [] === $decos ? null : $devices[$decos[0]];
        $this->backdrop = null;
        if (null !== $this->deco) {
            $stated = $this->eventOptions->backdrop;
            $backdrop = null === $stated
                ? sprintf('%s is brought, and nothing names a truss to hang it from. Say --backdrop=TRUSS:SEGMENTS:TOWER, or an --event with one', $this->deco->id)
                : StackBackdrop::parse($stated, $devices);
            $problem = is_string($backdrop) ? $backdrop : $backdrop->problem($this->deco, $this->eventOptions->room->heightM);
            if (null !== $problem) {
                $this->io->error($problem);

                return self::FAILURE;
            }
            /** @var StackBackdrop $backdrop */
            $this->backdrop = $backdrop;
        }
        // Only a rig that has a backdrop records one, so an event naming a truss changes no file without a panel.
        $input->setOption('backdrop', $this->backdrop?->stated());
        foreach ($this->getDefinition()->getOptions() as $option) {
            $this->defaults[$option->getName()] = $option->getDefault();
        }
        // Keyed by id in the order the specs were loaded, so this is the same list in the same order — the sort
        // that gives every command stable output is not disturbed by an override.
        $specs = array_values($devices);

        $missing = array_values(array_diff((array) $input->getOption('from'), array_keys($devices)));
        if ([] !== $missing) {
            $this->io->error(sprintf("Unknown device '%s'", $missing[0]));

            return self::FAILURE;
        }

        // Checked here rather than inside {@see SweepAxes::inventory}, because a misspelled owner has to be a refusal that
        // names the owners there are. Silently intersecting it away would leave the whole inventory built instead, which
        // is the opposite of what was asked for and looks like a working run.
        $owners = array_values(array_unique(array_map(
            static fn (DeviceSpec $spec): string => $spec->owner,
            array_filter($specs, static fn (DeviceSpec $spec): bool => 'speaker' === $spec->category->value),
        )));
        sort($owners);
        $unknown = array_values(array_diff((array) $input->getOption('owner'), $owners));
        if ([] !== $unknown) {
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
        if ([] !== $input->getOption('owner') && [] !== $input->getOption('from')) {
            $this->io->error('--owner and --from say the same thing at different resolutions — name one or the other');

            return self::FAILURE;
        }

        // **Parsed before the other axes because the rigs are built from it**, and a rig is decided one step earlier
        // than a shape or an alignment: the separation is a property of the rig, so a misspelling has to be refused
        // here rather than four lines further down with the rest of them.
        $splits = SweepAxes::systemSplits($input->getOption('systems'));
        if (is_string($splits)) {
            $this->io->error($splits);

            return self::FAILURE;
        }

        if (null === MouthMode::tryFrom((string) $input->getOption('mouths'))) {
            $this->io->error(sprintf("--mouths: unknown value '%s' (allowed: %s)", (string) $input->getOption('mouths'), implode(', ', array_column(MouthMode::cases(), 'value'))));

            return self::FAILURE;
        }

        $lowEnds = SweepAxes::lowEndBiases($input->getOption('low-end'));
        if (is_string($lowEnds)) {
            $this->io->error($lowEnds);

            return self::FAILURE;
        }

        $rigs = SweepAxes::rigsToTry(
            $specs,
            (array) $input->getOption('from'),
            null === $input->getOption('stacks') ? null : (string) $input->getOption('stacks'),
            (array) $input->getOption('owner'),
            (bool) $input->getOption('per-owner'),
            $this->grouping,
            $splits,
        );

        // **BROUGHT CABINETS THAT ARE NOT IN THE SWEEP ARE REFUSED, BECAUSE THEIR COUNTS WOULD CHANGE NOTHING.** Counts
        // rewrite the specs, and which specs a rig is built from is decided by `--owner` and `--from`, not by the
        // event. Only the systems a run sweeps have their counts applied, see {@see countOverrides}, so what is left
        // to catch is a `--from` list that names some of a system's cabinets and not the rest it brings. The roster
        // files this replaced could be named without their owner, and at 0.105.0 that filed 146 scenes of sdwa5 and
        // sepp cabinets under Innschleife's name. Named per cabinet so the fix is obvious.
        $swept = [];
        foreach ($rigs as $rig) {
            $swept = [...$swept, ...$rig['from']];
        }
        $absent = array_diff_key($this->brought, array_flip($swept));
        if ([] !== $absent) {
            $device = (string) array_key_first($absent);
            $this->io->error(sprintf(
                'the event has %s bring %s, which the swept inventory does not hold. Say --owner=%s, or name them with --from',
                $absent[$device],
                implode(', ', array_keys($absent)),
                $devices[$device]->owner,
            ));

            return self::FAILURE;
        }

        // **THE OUTPUT DIRECTORY IS THE RUN'S INVENTORY, AND A RUN HAS EXACTLY ONE.**
        // {@see SweepAxes::inventory} returns a single subset, so every rig in `$rigs` shares a label and
        // reading it off the first is a fact rather than a shortcut. A stated `--into` wins, which is what a replay
        // uses: the recorded line names a cabinet list rather than an owner, so it cannot re-derive its own folder.
        $into = (string) ($input->getOption('into') ?? '');
        /** @var list<string> $owners */
        $owners = (array) $input->getOption('owner');
        if ('' === $into && null !== $this->eventOptions->eventId && 1 === count($owners)) {
            // **ONE SYSTEM AT AN EVENT IS FILED AS `<owner>-<event>`, BECAUSE ITS RIG IS NOT THE INVENTORY'S RIG.** The
            // event's counts and its room both change it, so filed under `innschleife/` it would land beside the rigs
            // built from everything Innschleife owns, under the same file names, and the last run would win. It is the
            // name the roster file of the same system and event used to carry, so no folder moved with the merge.
            $into = $owners[0].'-'.$this->eventOptions->eventId;
        }
        if ('' === $into && [] !== $counts) {
            // **A CHANGED RIG UNDER AN UNCHANGED NAME IS THE ONE FAILURE THIS COMMAND MUST NOT HAVE.** Every other
            // axis is in the file name or in the folder, so two different rigs cannot collide; a count override is
            // in neither, and the sweep would quietly write its files over the ones a bare sweep just wrote. Several
            // systems at one event have no single name either, so both cases end here.
            $this->io->error(
                '--quantity changes the rig without changing its name — say --into=NAME for the folder to write it '
                .'into, or sweep a single --owner with --event, which files it as OWNER-EVENT',
            );

            return self::FAILURE;
        }
        if ('' === $into) {
            $into = (string) ($rigs[0]['inventory'] ?? '');
        }
        if (1 !== preg_match('/^[a-z0-9]*(?:-[a-z0-9]+)*$/', $into)) {
            $this->io->error('--into: '.$into.' is not a directory name — lowercase words separated by single dashes');

            return self::FAILURE;
        }

        $at = $this->readAt((string) $input->getOption('at'));
        if (null === $at) {
            $this->io->error('--at expects X,Y in metres');

            return self::FAILURE;
        }

        /** @var list<string> $rolled */
        $rolled = $input->getOption('roll-mirror');

        // **The axes are parsed by {@see SweepAxes} and reported here**, which is the whole of that seam: what an axis
        // covers and what a misspelled value is allowed to be are facts about the sweep, and "an error is red text on
        // stderr" is a fact about a console command.
        $modes = SweepAxes::modes($input->getOption('align'));
        $shapes = SweepAxes::shapes($input->getOption('shape'));
        $styles = SweepAxes::mirrorStyles($input->getOption('mirror-style'));
        $orientations = SweepAxes::orientations($input->getOption('orientation'), $rolled);

        foreach ([$modes, $shapes, $styles, $orientations] as $axis) {
            if (is_string($axis)) {
                $this->io->error($axis);

                return self::FAILURE;
            }
        }

        // **NULL WHEN NOBODY STATED ONE, and that is the whole of CVR-8.** It used to fall back to a 3.70 m default,
        // so every generated scene was solved against a stage nobody had asked for and a rig too wide for it was
        // refused for a reason that came from this line rather than from the request. {@see Stack::$maxWidthM} has
        // always been nullable and {@see StackMetrics::ceilingFor} has always read null as "no bound at all" — the
        // unbounded path was there the whole time and simply never reached.
        //
        // **AND THE WIDTH LADDER WENT WITH IT.** The sweep used to walk a rig up and down a list of stage widths to
        // land its sub wall inside the band, which needed both halves of a sentence that no longer has either: a
        // band that refuses, and a stated width worth deviating from. A width is now either stated, in which case
        // deviating from it is disobeying it, or absent, in which case there is nothing to deviate from. The lever
        // that remains is the solver's own row-count search, which chases `target_sub_height_m` directly rather than
        // through a proxy — see {@see StackSolver::fill}.
        $statedWidth = $this->readFloat($input, 'max-width');

        $candidates = [];
        $directories = [];
        $skipped = [];
        $noted = [];

        // **NAMED FIRST AND SOLVED AFTERWARDS**, which is what lets {@see sweep} put several processes on the list.
        // The four nested loops decide *which* rigs there are, which is arithmetic over the axes and costs nothing;
        // the solve behind each one is a minute of CPU on the big rigs. Splitting the two is the whole of the change.
        $tasks = [];
        $axisValues = [];
        $requestProblem = null;
        foreach ($rigs as $rig) {
            // **THE REQUEST-LEVEL HALF OF A REFUSAL, ASKED ONCE PER RIG AND ASKED HERE.** {@see groups} reads only
            // the device list and the stack count, so its answer is the same for every shape, orientation and
            // alignment nested below — and what it refuses is the invocation rather than the geometry. `--stacks=0`
            // is not a rig the solver could not build; it is a number nobody can act on. Keeping the two apart is
            // what lets the exit code below mean something: {@see NOTHING_TO_WRITE} is "none of these rigs stands
            // up", and a bad request is still a plain failure.
            //
            // Asked in the parent on purpose. A fork hands each child a copy of this object, so anything a child
            // records about the run dies with it, and this has to survive to decide the exit code.
            $rigProblem = StackDeal::groups(
                $devices,
                $rig['from'],
                $rig['stacks'],
                (float) $input->getOption('clearance'),
                (string) $input->getOption('split'),
                $this->grouping,
                $rig['split'],
            );
            if (is_string($rigProblem)) {
                $requestProblem ??= $rigProblem;
            }
            foreach ($shapes as $shape) {
                // A rig whose every system has a stated orientation has no orientation left to sweep, and its one
                // candidate is named `stated`, like a rig whose rolled cabinets were named outright.
                $rigOrientations = $this->eventOptions->fixesOrientation($devices, $rig['from']) ? [null] : $orientations;
                $resolve = [] === $this->eventOptions->orientations ? null
                    : fn (?StackOrientation $orientation): array => $this->eventOptions->rolls($orientation, $devices, $rig['from'], $rolled);
                // The same for the low end. The one value swept is a placeholder that every stack overrides, see
                // {@see SceneEventOptions::fixesLowEnd}, and it is the first one asked for so the recorded line says it.
                $lowEndFixed = $this->eventOptions->fixesLowEnd($devices, $rig['from']);
                $rigLowEnds = $lowEndFixed ? [$lowEnds[0]] : $lowEnds;
                foreach (SweepAxes::pairs($rigOrientations, $styles, $rolled, $devices, $rig['from'], $resolve) as [$orientation, $style]) {
                    foreach ($modes as $mode) {
                        foreach ($rigLowEnds as $lowEnd) {
                            // **EVERY AXIS IS IN THE NAME, AT A FIXED WIDTH**, in the order the sweep nests them: the rig
                            // (owners and stack count), then shape, orientation, mirror style, alignment. See
                            // {@see SweepAxes::padded} for why the widths, and {@see SweepAxes::STATED} for the one value that has
                            // no enum case behind it.
                            //
                            // Three axes used to be omitted at one value each — `pyramid`, `upright` and `alternate` — so
                            // that the ordinary rig kept a short id, and the price was a directory nobody could read: a
                            // name with a gap in it does not say which value was left out, only that one was, and the
                            // reader had to know the defaults by heart to tell `stacked-gmss-1-center` from its siblings.
                            //
                            // The mirror style is written even where nothing is rolled and it decides nothing. The sweep
                            // only pairs `upright` with `alternate`, but `--orientation=upright --mirror-style=centred` is
                            // accepted and honoured, so a name that dropped a vacuous style would give two different rigs
                            // the same file name.
                            // **THE STEM, AND THE LAST FIELD IS NO LONGER THE LAST FIELD.** Feasibility is the sixth
                            // axis and it is the solve's answer rather than the caller's request, so it cannot be
                            // known here — the name is completed after {@see sweep} returns. The alignment is padded
                            // now that something lines up behind it; whatever ends up last stays ragged, which is the
                            // rule this always followed.
                            // **THE LAYOUT DECIDES WHICH OF THESE ARE IN THE NAME AND WHICH ARE DIRECTORIES**, and at
                            // the default layout it produces exactly the string the `sprintf` here used to: the base
                            // id, then the stack count, the separation, the shape, the orientation, the mirror style
                            // and the alignment, each padded to its axis's widest value. See {@see SceneLayout}.
                            $raw = [
                                'inventory' => $into,
                                'stacks' => (string) $rig['stacks'],
                                'systems' => $rig['split']->value,
                                'shape' => $shape->value,
                                'orientation' => $orientation->value ?? SweepAxes::STATED,
                                'mirror-style' => $style->value,
                                'align' => $mode->value,
                                'low-end' => $lowEndFixed ? SweepAxes::STATED : $lowEnd->value,
                            ];
                            // Padded for the name and raw for the path — see {@see SceneLayout::pathFor} on why a
                            // directory does not carry a column's padding.
                            $values = [
                                'inventory' => $into,
                                'stacks' => $raw['stacks'],
                                'systems' => SweepAxes::padded($raw['systems'], SystemSplit::class),
                                'shape' => SweepAxes::padded($raw['shape'], StackShape::class),
                                'orientation' => SweepAxes::padded($raw['orientation'], StackOrientation::class),
                                'mirror-style' => SweepAxes::padded($raw['mirror-style'], MirrorStyle::class),
                                'align' => SweepAxes::padded($raw['align'], LayoutMode::class),
                                'low-end' => SweepAxes::padded($raw['low-end'], LowEndBias::class),
                            ];
                            $name = $layout->nameFor((string) $input->getOption('id'), $values);
                            if (is_string($rigProblem)) {
                                $skipped[$name] = $rigProblem;
                                continue;
                            }
                            $tasks[$name] = [$rig, $shape, $style, $orientation, $mode, $lowEnd];
                            $axisValues[$name] = [$values, $raw];
                        }
                    }
                }
            }
        }

        foreach ($this->sweep($tasks, $devices, $at, $statedWidth, $input, $into) as $stem => $built) {
            if (is_string($built)) {
                // No rig at all, so no side of the feasibility axis to put it on. Reported under the stem, because
                // a name for a file that was never written would be a name for nothing.
                $skipped[$stem] = $built;
                continue;
            }

            // **The axis value is appended here because here is the first place it is known.** A candidate is
            // possible or impossible — never a variant of the same rig — so this doubles the sweep rather than
            // multiplying it, and both halves are ordinary generated scenes.
            $feasibility = Feasibility::of($built['faults']);
            [$values, $raw] = $axisValues[$stem] ?? [[], []];
            $values['feasibility'] = $feasibility->value;
            $raw['feasibility'] = $feasibility->value;
            $name = $layout->nameFor((string) $input->getOption('id'), $values);
            $directories[$name] = $layout->pathFor($raw);
            if (Feasibility::Impossible === $feasibility) {
                // **The file says why it is named impossible, in its own header.** Everything else in a generated
                // scene is a constraint the compiler re-solves; this is the one line that is an *answer*, and it is
                // a comment for that reason. `scene:build` derives the same faults from the same geometry and cages
                // the cabinets in red, so nothing downstream reads this — it is for whoever opens the file and
                // wonders what is wrong with it.
                $built['yaml'] = self::withFaultNotice($built['yaml'], $built['faults']);
            }
            // **A MISSED BAND IS A NOTE, NOT A SKIP**, which is CVR-7 and is stated by the owner: the
            // sub/top interface height is an optimisation problem rather than a hard constraint, so tops
            // firing below or above head height is not a reason to refuse a rig. It used to be one, on
            // every invocation rather than only on the sweep, and it threw away more candidates than every
            // geometry rule in the repository put together.
            //
            // Noted here **and** written into the file, which are two different readers. The scene carries
            // the miss in its own header — {@see StackChecks::boundsProblems} produces it as a warning and
            // {@see StackSceneWriter::header} writes every warning out — so somebody opening the file sees
            // that the wall is knowingly short. The line below is for whoever ran the sweep and is not
            // going to open 150 files.
            if (null !== $built['bandMiss']) {
                $noted[$name] = $built['bandMiss'];
            }
            $candidates[$name] = $built;
        }

        $candidates = CandidateCheck::deduplicate($candidates, $skipped);

        foreach ($skipped as $name => $reason) {
            $this->io->text(sprintf('  <comment>skipped</comment> %s — %s', $name, $reason));
        }
        // After the deduplication rather than before it, so a note is only printed for a rig that is actually
        // written. A duplicate is reported as the duplicate it is and its miss belongs to the scene it duplicates.
        foreach ($noted as $name => $reason) {
            if (isset($candidates[$name])) {
                $this->io->text(sprintf('  <comment>noted</comment>   %s — %s', $name, $reason));
            }
        }

        if ([] === $candidates) {
            // **A BAD REQUEST IS A FAILURE AND AN UNBUILDABLE RIG IS NOT**, which is the distinction
            // {@see NOTHING_TO_WRITE} exists to draw and which it briefly erased. `--stacks=0`, an unknown
            // `--split` and "5 device types cannot fill 9 stacks" all leave the candidate list empty, and reporting
            // them as "the sweep no longer offers this rig" would have `build:all` treat a typo as a scene to
            // delete. Every one of them comes out of {@see groups}, which is why the check above it is where it is.
            if (null !== $requestProblem) {
                $this->io->error($requestProblem);

                return self::FAILURE;
            }

            $this->io->warning('No workable arrangement — nothing written');

            return self::NOTHING_TO_WRITE;
        }

        $limit = (int) $input->getOption('max-scenes');
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

        return $this->emit(
            $candidates,
            (bool) $input->getOption('dry-run'),
            (bool) $input->getOption('force'),
            $directories,
        );
    }

    /**
     * One candidate scene, or the reason there is none.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @param array{float, float} $at
     * @param ?float $maxWidthM the stage width, or **null for none at all** — how wide a generated scene comes out
     *                          does not matter unless somebody says it does, which is stated by the owner and is CVR-8
     *
     * @return array{yaml: string, cabinets: int, fingerprint: string, bandMiss: ?string, faults: list<Fault>}|string
     */
    private function build(
        array $devices,
        array $from,
        array $at,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        LowEndBias $lowEnd,
        int $stacks,
        string $baseId,
        ?float $maxWidthM,
        InputInterface $input,
        SystemSplit $split = SystemSplit::Pooled,
        string $into = '',
    ): array|string {
        $grouped = StackDeal::groups(
            $devices,
            $from,
            $stacks,
            (float) $input->getOption('clearance'),
            (string) $input->getOption('split'),
            $this->grouping,
            $split,
        );
        if (is_string($grouped)) {
            return $grouped;
        }
        ['stacks' => $groups, 'tops' => $pool] = $grouped;

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

        $target = $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M;

        // **The strategy loop at one set of width caps**, so the room search below can ask it again with narrower
        // stacks. With no caps it is the loop as it always was.
        $solveAt = function (array $caps) use (
            $strategies,
            $groups,
            $pool,
            $devices,
            $mode,
            $shape,
            $style,
            $orientation,
            $lowEnd,
            $maxWidthM,
            $input,
            $target,
        ): array {
            $blocks = [];
            $best = -1;
            $bestMiss = INF;
            $flags = [true, true];
            $firstProblem = null;

            foreach ($strategies as [$evenSplit, $placeAll]) {
                $attempt = $this->solveEach(
                    $groups,
                    [],
                    $devices,
                    $mode,
                    $shape,
                    $style,
                    $orientation,
                    $lowEnd,
                    $maxWidthM,
                    $input,
                    $evenSplit,
                    $placeAll,
                    false,
                    $caps,
                );
                if (is_string($attempt)) {
                    $firstProblem ??= $attempt;
                    continue;
                }

                // **SWP-2's SECOND PASS, and it is a second pass rather than a second list because of what it reads.**
                // A pool of tops is only non-empty under `tops-shared`, and it is dealt against the walls **as they came
                // out** — how much top face a wall offers is the solver's answer rather than the inventory's, so nothing
                // before this point could have known it. See {@see SharedTops} for the rule.
                //
                // Then every stack is solved again, from its subs plus its dealt share. The first solve is thrown away
                // apart from its geometry, which is the honest cost of dealing against a solved wall instead of a
                // guessed one: the sweep forks, so it is CPU rather than anybody's time.
                if ([] !== $pool) {
                    $attempt = $this->solveEach(
                        $groups,
                        SharedTops::deal($attempt, $pool, $devices),
                        $devices,
                        $mode,
                        $shape,
                        $style,
                        $orientation,
                        $lowEnd,
                        $maxWidthM,
                        $input,
                        $evenSplit,
                        $placeAll,
                        false,
                        $caps,
                    );
                    if (is_string($attempt)) {
                        $firstProblem ??= $attempt;
                        continue;
                    }
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
                // "shorter wins" — but a target is a distance. Ranking on the tallest let an attempt win because its tall
                // stack sat at 2.48 m while its other stack dropped to 1.773 m: measured, it cost
                // `stacked-sdwa5-2-free-turned-centred-center` the whole scene, back when a stack outside the band was a
                // refusal. Taking the worst miss keeps every stack near the aim, which is what the aim is for.
                //
                // The cost is {@see heightCost} rather than plain distance, because the band no longer refuses anything
                // and a bound that cannot refuse and cannot rank would mean nothing whatsoever.
                $placed = array_sum(array_map(static fn (StackBlock $b): int => $b->cabinets(), $attempt));
                $miss = max(array_map(
                    static fn (StackBlock $b): float => StackChecks::heightCost($b, $target),
                    $attempt,
                ));

                if ($placed > $best || ($placed === $best && $miss < $bestMiss - 1e-9)) {
                    $best = $placed;
                    $bestMiss = $miss;
                    $blocks = $attempt;
                    $flags = [$evenSplit, $placeAll];
                }
            }

            return ['blocks' => $blocks, 'best' => $best, 'flags' => $flags, 'problem' => $firstProblem];
        };

        // **SYM-3, ONE TOPS ROW ACROSS A MIRRORED PAIR**, tried after the per-wall rigs because it is judged against
        // them: it has to place at least as many cabinets as the best of them, and on a tie it wins, since symmetry
        // wins ties. A bridged rig the compiler refuses falls back to the per-wall one rather than losing the scene.
        $write = function (array $blocks, ?BridgedTops $bridge) use (
            $at,
            $devices,
            $mode,
            $shape,
            $style,
            $orientation,
            $lowEnd,
            $stacks,
            $baseId,
            $maxWidthM,
            $from,
            $split,
            $into,
            $input,
        ): array|string {
            // **Reported rather than refused whoever asked for it**. The
            // sweep used to treat a wall outside the band as a rig for a different stage and a named rig as a warning,
            // which was two answers to one question; now both are the warning, and there is no stage to move it to.
            $clearance = null === $bridge ? (float) $input->getOption('clearance') : $bridge->clearanceM;
            if (null === $bridge && !$split->isPerOwner()) {
                // Each block was solved alone, aimed from its own centre. The compiler aims a pooled stack from the
                // rig's, so the blocks are re-solved for where they now stand. A bridged pair carries no tops of its
                // own, and a stack per system aims at its own focus in the scene as well. See GEO-11.
                $blocks = RigAim::reaimed($blocks, $at[0], $clearance, $devices);
            }
            $bandMiss = StackChecks::bandMiss($blocks);
            $yaml = StackSceneWriter::yaml(
                id: 'placeholder',
                name: $this->describe($mode, count($blocks)),
                blocks: $blocks,
                at: $at,
                clearanceM: $clearance,
                command: RecordedCommand::line(
                    $input,
                    $mode,
                    $shape,
                    $style,
                    $orientation,
                    $lowEnd,
                    $stacks,
                    $baseId,
                    $maxWidthM,
                    $from,
                    $split,
                    $into,
                    $this->counts,
                    $this->defaults,
                ),
                stated: $this->counts,
                // Each block is a system exactly when the separation says so, which is what decides whether they
                // share a focus. See {@see StackSceneWriter::yaml}.
                perSystemFocus: $split->isPerOwner(),
                bridge: $bridge,
            );

            // Compiled before it is written. Anything the compiler calls an error means this arrangement is not
            // one of the possibilities, whatever the solver thought of the tiers.
            $compiled = CandidateCheck::compileYaml($yaml, $devices, $this->eventOptions->room);
            if (is_string($compiled)) {
                return $compiled;
            }

            // **THE BACKDROP GOES BEHIND THE RIG AS IT CAME OUT**, which is why it is added after the first compile: the
            // deepest back face is the solve's answer, with the aimed tops yawed and the subs rolled. Then the whole
            // scene is compiled again, so the room, the overlap and the floating checks see the truss as well.
            $backdrop = $this->backdrop;
            if (null !== $this->deco && null !== $backdrop) {
                // A blank line before the block and one newline after it, the way the writer ends a scene.
                $yaml .= "\n".implode("\n", $backdrop->yaml(
                    $this->deco,
                    $at[0],
                    $compiled['backY'],
                    $this->eventOptions->room->heightM,
                ))."\n";
                $cabinets = $compiled['cabinets'];
                $compiled = CandidateCheck::compileYaml($yaml, $devices, $this->eventOptions->room);
                if (is_string($compiled)) {
                    return $compiled;
                }
                // A tower is not a cabinet, and the count is what the run reports per rig.
                $compiled['cabinets'] = $cabinets;
            }

            return ['yaml' => $yaml, 'bandMiss' => $bandMiss] + $compiled;
        };

        $writeAt = function (array $caps) use (
            $solveAt,
            $write,
            $groups,
            $pool,
            $devices,
            $mode,
            $shape,
            $style,
            $orientation,
            $lowEnd,
            $maxWidthM,
            $input,
        ): array|string {
            ['blocks' => $blocks, 'best' => $best, 'problem' => $problem] = $solveAt($caps);
            if ([] === $blocks) {
                return $problem ?? 'no workable arrangement';
            }

            $bridged = $this->bridged($groups, $pool, $devices, $mode, $shape, $style, $orientation, $lowEnd, $maxWidthM, $input, $best, $caps);
            if (null !== $bridged) {
                $written = $write(...$bridged);
                if (!is_string($written)) {
                    return $written;
                }
            }

            return $write(StackSceneWriter::byHeight($blocks, $mode, self::statedOrder($input)), null);
        };

        $written = $writeAt([]);
        if (!is_string($written) || null === RoomBounds::widthExcessIn($written)) {
            return $written;
        }

        // One group solved on its own at one cap, with the dealing the uncapped rig settled on. The room search sizes
        // its ladders with it, see {@see fittedToRoom}.
        [$evenSplit, $placeAll] = $solveAt([])['flags'];
        $solveOne = function (string $label, ?float $cap) use (
            $groups,
            $devices,
            $mode,
            $shape,
            $style,
            $orientation,
            $lowEnd,
            $maxWidthM,
            $input,
            $evenSplit,
            $placeAll,
        ): ?StackBlock {
            $solved = $this->solveEach(
                [$label => $groups[$label]],
                [],
                $devices,
                $mode,
                $shape,
                $style,
                $orientation,
                $lowEnd,
                $maxWidthM,
                $input,
                $evenSplit,
                $placeAll,
                false,
                null === $cap ? [] : [$label => $cap],
            );

            return is_string($solved) ? null : $solved[0];
        };

        return $this->fittedToRoom($written, $groups, $solveOne, $writeAt);
    }

    /**
     * How many narrower widths one stack is offered when its rig is too wide for the room. Twelve takes our pooled
     * stack from one row of twelve Flexys down past three rows, which is further than any room so far has asked.
     */
    private const ROOM_LADDER_STEPS = 12;

    /** How many combinations of narrowed stacks are compiled before a rig is given up on. */
    private const ROOM_ATTEMPTS = 64;

    /**
     * How much the compiled rig may narrow by less than its stacks' sub rows do, before a combination is not worth
     * compiling. Aimed tops and the backdrop reach past the subs, so the two widths are not the same number.
     */
    private const ROOM_SLACK_M = 0.1;

    /**
     * The rig built again with narrower stacks until it fits the room, or the reason it cannot.
     *
     * **The room is a hard limit and the height band is not**, which the owner settled on 2026-10-03. A rig used to be
     * solved stack by stack with no idea of the room, and the room only refused the finished compile, so the
     * Achenbach event's stereo rig at 16.03 m was lost in a 13 m room although every stack in it could stand narrower.
     *
     * * **A ladder per stack.** Each stack is solved again under a cap 1 mm below its last width until it stops
     *   narrowing or places fewer cabinets. A narrower stack is a taller one, so every step costs height.
     * * **Stacks of one pool share a step**, so a mirrored pair stays a mirror image and its bridged tops still fit.
     * * **The combinations are ordered by what they cost**, the worst stack's {@see StackChecks::heightCost} first and
     *   the sum second, which is how {@see build} already ranks a rig. One that cannot narrow the sub rows by the
     *   excess is not compiled at all.
     * * **Each is written as the rig would be**, the caps going into the scene as `max_width_m`, so the compiler
     *   re-solves the file to the same rows. The first that passes the room is the answer.
     *
     * @param string $refusal the uncapped rig's room-width refusal
     * @param array<string, array{ids: list<string>, index: int, of: int}> $groups
     * @param callable(string, ?float): ?StackBlock $solveOne one group alone at one cap
     * @param callable(array<string, float>): (array<string, mixed>|string) $writeAt the whole rig at a set of caps
     *
     * @return array{yaml: string, cabinets: int, fingerprint: string, bandMiss: ?string, faults: list<Fault>}|string
     */
    private function fittedToRoom(string $refusal, array $groups, callable $solveOne, callable $writeAt): array|string
    {
        $excess = (float) RoomBounds::widthExcessIn($refusal);

        $pools = [];
        foreach ($groups as $key => $group) {
            $pools[implode(',', $group['ids'])][] = (string) $key;
        }

        $combinations = [[[], 0.0, 0.0, 0.0]];
        foreach ($pools as $labels) {
            $steps = $this->widthLadder($labels[0], $solveOne);
            $next = [];
            foreach ($combinations as [$caps, $narrowed, $worst, $total]) {
                foreach ($steps as [$cap, $width, $cost]) {
                    foreach ($labels as $label) {
                        if (null !== $cap) {
                            $caps[$label] = $cap;
                        } else {
                            unset($caps[$label]);
                        }
                    }
                    $next[] = [
                        $caps,
                        $narrowed + count($labels) * ($steps[0][1] - $width),
                        max($worst, $cost),
                        $total + count($labels) * $cost,
                    ];
                }
            }
            $combinations = $next;
        }

        $mostNarrowed = max(array_column($combinations, 1));
        $combinations = array_filter(
            $combinations,
            static fn (array $c): bool => [] !== $c[0] && $c[1] >= $excess - self::ROOM_SLACK_M,
        );
        usort($combinations, static fn (array $a, array $b): int => [$a[2], $a[3]] <=> [$b[2], $b[3]]);

        $leastExcess = null;
        $otherRefusal = null;
        foreach (array_slice($combinations, 0, self::ROOM_ATTEMPTS) as [$caps]) {
            $written = $writeAt($caps);
            if (!is_string($written)) {
                return $written;
            }
            $over = RoomBounds::widthExcessIn($written);
            if (null === $over) {
                $otherRefusal ??= $written;
            } else {
                $leastExcess = min($leastExcess ?? INF, $over);
            }
        }

        $room = (float) $this->eventOptions->room->widthM;
        if (null !== $leastExcess) {
            return sprintf(
                'the whole rig is %.3f m wide even with its stacks narrowed, and exceeds the %.3f m room width',
                $room + $leastExcess,
                $room,
            );
        }

        return $otherRefusal ?? sprintf(
            '%s, and narrowing its stacks saves at most %.3f m of sub rows',
            $refusal,
            $mostNarrowed,
        );
    }

    /**
     * One stack's narrower widths, widest first, as `[cap, width, height cost]`. The first step is the stack uncapped.
     *
     * @param callable(string, ?float): ?StackBlock $solveOne
     *
     * @return non-empty-list<array{?float, float, float}>
     */
    private function widthLadder(string $label, callable $solveOne): array
    {
        $first = $solveOne($label, null);
        if (null === $first) {
            return [[null, 0.0, 0.0]];
        }

        $steps = [[null, $first->widthM(), StackChecks::heightCost($first, $first->stack->targetSubHeightM)]];
        $width = $first->widthM();
        while (count($steps) < self::ROOM_LADDER_STEPS) {
            $cap = $width - 0.001;
            $block = $solveOne($label, $cap);
            if (null === $block || $block->cabinets() < $first->cabinets() || $block->widthM() > $cap + 1e-9) {
                break;
            }
            $width = $block->widthM();
            $steps[] = [$cap, $width, StackChecks::heightCost($block, $block->stack->targetSubHeightM)];
        }

        return $steps;
    }

    /**
     * The pair's two walls solved from their subs alone, and one tops row standing on both. SYM-3.
     *
     * Asked only of **two stacks out of one pool**, because only those can come out level. Two systems' walls are
     * drawn from two inventories and never match, which is why `tops-shared` shares the pool and not the row. The
     * walls are dealt evenly and the remainder left out, so they mirror each other, and every top of the pool goes
     * into the row, which is how a third Tecnare stops being dealt to one side.
     *
     * Null when the pair has no tops, places fewer cabinets than the per-wall rig, or carries no row. See
     * {@see BridgedTops} for the layouts and the bearing rule.
     *
     * @param array<string, array{ids: list<string>, index: int, of: int}> $groups
     * @param array<string, int> $pool the tops `tops-shared` held back, device id => cabinets
     * @param array<string, DeviceSpec> $devices
     * @param array<string, float> $caps group label => width cap, see {@see solveEach}
     *
     * @return array{list<StackBlock>, BridgedTops}|null the walls left to right, and the row
     */
    private function bridged(
        array $groups,
        array $pool,
        array $devices,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        LowEndBias $lowEnd,
        ?float $maxWidthM,
        InputInterface $input,
        int $best,
        array $caps = [],
    ): ?array {
        $lists = array_values(array_column($groups, 'ids'));
        if (2 !== count($lists) || $lists[0] !== $lists[1]) {
            return null;
        }

        $tops = [];
        foreach ($lists[0] as $id) {
            if ('sub' !== $devices[$id]->subtype && $devices[$id]->quantity > 0) {
                $tops[$id] = $devices[$id]->quantity;
            }
        }
        foreach ($pool as $id => $count) {
            $tops[$id] = ($tops[$id] ?? 0) + $count;
        }
        $subs = array_values(array_filter($lists[0], static fn (string $id): bool => 'sub' === $devices[$id]->subtype));
        if ([] === $tops || [] === $subs) {
            return null;
        }

        $walls = $this->solveEach(
            array_map(static fn (array $group): array => ['ids' => $subs] + $group, $groups),
            [],
            $devices,
            // No alignment on a wall with no tops of its own, since `stereo` would spread its top sub row apart.
            LayoutMode::Center,
            $shape,
            $style,
            $orientation,
            $lowEnd,
            $maxWidthM,
            $input,
            true,
            false,
            true,
            $caps,
        );
        if (is_string($walls)) {
            return null;
        }
        $placed = array_sum(array_map(static fn (StackBlock $b): int => $b->cabinets(), $walls)) + array_sum($tops);
        if ($placed < $best) {
            return null;
        }

        $walls = StackSceneWriter::byHeight($walls, $mode, self::statedOrder($input));
        $row = StackTops::topRow(
            array_map(static fn (string $id): array => [$devices[$id], $tops[$id]], array_keys($tops)),
            $walls[1]->stack,
            $mode,
        );
        if (null === $row) {
            return null;
        }

        $bridge = BridgedTops::solve(
            $walls[0],
            $walls[1],
            $row,
            (float) $input->getOption('clearance'),
            $this->nearFieldFills($devices, array_keys($tops)),
        );

        return null === $bridge ? null : [$walls, $bridge];
    }

    /**
     * Every group solved into a block, or the first reason one of them could not be.
     *
     * **Extracted because SWP-2 needs to run it twice**, once on the sub walls alone and once on the same walls with
     * the pooled tops dealt onto them. Inlined in the strategy loop it was one pass by construction, and the second
     * one would have been a copy — which is how the two would have drifted into solving slightly different rigs.
     *
     * `$deal` is empty for every value but `tops-shared`, so this behaves exactly as the inlined loop did for the
     * other two.
     *
     * @param array<string, array{ids: list<string>, index: int, of: int}> $groups
     * @param array<string, array<string, int>> $deal group label => device id => cabinets dealt to it
     * @param array<string, DeviceSpec> $devices
     * @param array<string, float> $caps group label => the width that group's stack is held to, on top of the stage
     *                                   width, where {@see build} narrowed it to fit the room
     *
     * @return list<StackBlock>|string
     */
    private function solveEach(
        array $groups,
        array $deal,
        array $devices,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        LowEndBias $lowEnd,
        ?float $maxWidthM,
        InputInterface $input,
        bool $evenSplit,
        bool $placeAll,
        bool $sharedTops = false,
        array $caps = [],
    ): array|string {
        $blocks = [];
        foreach ($groups as $key => ['ids' => $ids, 'index' => $index, 'of' => $of]) {
            // Cast, because PHP turns an array key that looks like a number into one — `--stacks=2` without
            // `--per-owner` labels the groups "1" and "2", which arrive here as ints.
            $label = (string) $key;
            $cap = $caps[$label] ?? null;
            $block = $this->solveGroup(
                $devices,
                $ids,
                $label,
                $mode,
                $shape,
                $style,
                $orientation,
                $lowEnd,
                null === $cap ? $maxWidthM : min($cap, $maxWidthM ?? INF),
                $input,
                count($groups) > 1,
                $index,
                $of,
                $evenSplit,
                $placeAll,
                $deal[$label] ?? [],
                $sharedTops,
            );
            if (is_string($block)) {
                return '' === $label ? $block : sprintf('%s: %s', $label, $block);
            }
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Solves every candidate in the sweep, across as many processes as the machine has cores.
     *
     * **The candidate loop is embarrassingly parallel and was costing 25 minutes of one core.** Each candidate is a
     * solve and a compile over the same immutable inventory. It reads nothing another candidate writes, it writes
     * no file — the writing happens after the deduplication, on the survivors — and what it returns is four
     * scalars. The only thing the loop ever shared was the CPU. Measured on the default sweep, 1206 candidates and
     * 100 minutes of CPU between them: **25 minutes serial against 3m37s across 28 cores**, and the printed output
     * is byte-identical, which is the property the merge below exists to guarantee.
     *
     * **Merged by task order rather than by whichever child finished first.** The results are keyed by the name the
     * caller already assigned, and this rebuilds that order from `$tasks` rather than from the children, so the
     * scene list, the skip list and therefore the deduplication all come out in the order a serial run produced.
     * Without that the output would be correct and unstable, which is worse than slow.
     *
     * @param array<string, array{array{from: list<string>, stacks: int, split: SystemSplit, inventory: string, suffix: string}, StackShape,
     *     MirrorStyle, ?StackOrientation, LayoutMode, LowEndBias}> $tasks
     * @param array<string, DeviceSpec> $devices
     * @param list<float> $at
     *
     * @return array<string, array{yaml: string, bandMiss: ?string, cabinets: int, fingerprint: string, faults: list<Fault>}|string>
     */
    private function sweep(
        array $tasks,
        array $devices,
        array $at,
        ?float $statedWidth,
        InputInterface $input,
        string $into,
    ): array {
        return Parallel::map(
            $tasks,
            fn (array $task): array|string => $this->build(
                $devices,
                $task[0]['from'],
                $at,
                $task[4],
                $task[1],
                $task[2],
                $task[3],
                $task[5],
                $task[0]['stacks'],
                (string) $input->getOption('id'),
                $statedWidth,
                $input,
                $task[0]['split'],
                $into,
            ),
            (int) $input->getOption('jobs'),
        );
    }

    /**
     * Prepends the fault notice to an impossible rig's header.
     *
     * Inserted after the generated-by line rather than at the top, so the first thing in the file is still what
     * every other generated scene starts with. A reader skimming a directory listing already knows from the name;
     * a reader inside the file wants the reason in the first screen.
     *
     * @param list<Fault> $faults
     */
    private static function withFaultNotice(string $yaml, array $faults): string
    {
        $lines = ['#', '# THIS RIG DOES NOT STAND UP. It is written anyway so the failure can be looked at rather'];
        $lines[] = '# than read about — `scene:build` cages the offending cabinets in red. Do not build it for a gig.';
        $lines[] = '#';
        foreach ($faults as $fault) {
            $lines[] = '#   * '.$fault->message;
        }

        $first = strpos($yaml, "\n");

        return false === $first
            ? implode("\n", $lines)."\n".$yaml
            : substr($yaml, 0, $first)."\n".implode("\n", $lines).substr($yaml, $first);
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
     * @param array<string, int> $tops device id => cabinets dealt to this stack out of the shared pool, `[]` for
     *                                 every value of the separation axis but `tops-shared`
     */
    private function solveGroup(
        array $devices,
        array $ids,
        string $label,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        LowEndBias $lowEnd,
        ?float $maxWidthM,
        InputInterface $input,
        bool $named,
        int $index,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
        array $tops = [],
        bool $sharedTops = false,
    ): StackBlock|string {
        // **The dealt tops join the list after the subs, which is where the fill order wants them.** Appended rather
        // than merged, because `$ids` is a fill order and not a set: {@see \App\Spec\FillOrder::everySpeaker} emits subs low-frequency
        // first and then tops, {@see groups} truncated the tops off the end for `tops-shared`, and this puts back a
        // different set of them in the same place.
        $ids = [...$ids, ...array_keys($tops)];

        // What the split does with the odd cabinets, named up front rather than left to be inferred from the tiers.
        $omitted = StackDeal::splitRemainder($devices, $ids, $of, $evenSplit, $placeAll, $tops);

        // Try the whole group, then the group with one device removed, smallest holding first — the cabinet
        // most likely to be the odd one out is the one there are fewest of.
        $candidates = [$ids];
        $bySize = $ids;
        usort($bySize, static fn (string $a, string $b): int => $devices[$a]->quantity <=> $devices[$b]->quantity);
        foreach ($bySize as $drop) {
            $rest = array_values(array_filter($ids, static fn (string $id): bool => $id !== $drop));
            if ([] !== $rest) {
                $candidates[] = $rest;
            }
        }

        $firstProblem = null;
        foreach ($candidates as $attempt) {
            $stack = $this->stackFor(
                $attempt,
                $input,
                $devices,
                $maxWidthM,
                $shape,
                $style,
                $orientation,
                $lowEnd,
                2 * $index < $of - 1,
                1 === $of,
                $sharedTops,
            );
            $problems = $stack->problems();
            if ([] !== $problems) {
                return $problems[0];
            }

            // **THE SAME QUESTION THE COMPILER WILL ASK**, and asking a different one is a defect rather than a
            // shortcut. This used to solve with no seating check and then hand the answer to {@see CandidateCheck::compileYaml},
            // which re-solves *with* one — so the command wrote the arrangement its own solve liked and the compiler
            // rebuilt a different one from the same file. See GEO-11 and {@see SceneCompiler::seatingCheck}.
            $placementId = $named ? 'main-'.$label : 'main';
            $solved = StackSolver::solve(
                StackDeal::inventoryFor($devices, $attempt, $index, $of, $evenSplit, $placeAll, $tops),
                $stack,
                // `center` is written as no alignment at all, so it arrives here as the mode rather than as null —
                // and `topRow` treats the two identically, which keeps the generated scene and its rebuild agreeing.
                $mode,
                SceneCompiler::seatingCheck(
                    $devices,
                    RigAim::probePlacement($placementId, $stack, $mode),
                    StackSceneWriter::focusPoints(),
                ),
            );
            if ([] !== $solved['problems']) {
                $firstProblem ??= $solved['problems'][0];
                continue;
            }

            foreach (array_diff($ids, $attempt) as $dropped) {
                $omitted[$dropped] = 'LEFT OUT, it cannot be carried in this stack — '
                    .($firstProblem ?? 'no supported arrangement');
            }

            return new StackBlock(
                placementId: $placementId,
                label: $label,
                stack: $stack,
                tiers: $solved['tiers'],
                from: $attempt,
                warnings: $solved['warnings'],
                omitted: $omitted,
                align: LayoutMode::Center === $mode ? null : $mode,
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
        ?float $maxWidthM,
        StackShape $shape = StackShape::Free,
        MirrorStyle $style = MirrorStyle::Alternate,
        ?StackOrientation $orientation = null,
        LowEndBias $lowEnd = LowEndBias::Low,
        bool $mirror = false,
        bool $solo = false,
        bool $sharedTops = false,
    ): Stack {
        // **WHICH CABINETS LIE DOWN, resolved here and nowhere else.** An orientation answers it from the specs — every
        // sub, or only the ones that get wider on their side ({@see StackOrientation}) — and a null orientation means
        // the caller stated the cabinets outright, which is what `--roll-mirror` is for and what every hand-written
        // invocation in this repository uses.
        //
        // The stated form stays because **no spec field says which cabinets are horn-loaded**, and adding one to drive a
        // rotation would be inventing a property to serve a layout. `subtype: sub` is a different claim, already
        // recorded and made for its own reasons, which is why an orientation may lean on it. An event's per-system
        // orientations apply first, see {@see SceneEventOptions::rolls}.
        /** @var list<string> $stated */
        $stated = $input->getOption('roll-mirror');
        $turned = $this->eventOptions->rolls($orientation, $devices, $ids, $stated);

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
            // The width the *caller* settled on, and **null is one of the answers** rather than a missing value:
            // an unstated `--max-width` is a stage nobody bounded, which {@see StackMetrics::ceilingFor} reads as no
            // bound at all. Passed down rather than read off the option here, so there is one place that decides it.
            maxWidthM: $maxWidthM,
            minWidthM: $this->readFloat($input, 'min-width'),
            maxHeightM: $this->readFloat($input, 'max-height'),
            interfaceHeightM: $this->eventOptions->interfaceFor($ids, $devices) ?? (float) $input->getOption('interface-height'),
            gapM: (float) $input->getOption('gap'),
            mirror: $mirror,
            maxSubHeightM: $this->readFloat($input, 'max-sub-height'),
            targetSubHeightM: $this->eventOptions->targetFor($ids, $devices) ?? $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M,
            shape: $shape,
            mirrorStyle: $style,
            // A system that states its low end keeps it whatever the sweep varies, see {@see SceneEventOptions::lowEndFor}.
            lowEnd: $this->eventOptions->lowEndFor($ids, $devices) ?? $lowEnd,
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
            mouths: MouthMode::from((string) $input->getOption('mouths')),
            sharedTops: $sharedTops,
        );
    }

    /**
     * The count every device is built with where that is not the number its spec states, or the reason the run
     * cannot start.
     *
     * **Two ways in, and they are the same fact at two levels of permanence.** The event's `systems.<owner>.brings`
     * is a file somebody committed, which is where "what Innschleife brings on the 6th" belongs, and `--quantity` is
     * the same statement typed at a shell, for the question nobody will ask twice. So `--quantity` wins where both name
     * a device: the typed value is the newer of the two by construction, and a file a caller has deliberately
     * overridden on the command line is not an argument for refusing to run.
     *
     * **Only the systems this run sweeps bring anything.** They are the `--owner`s, or the owners of the `--from`
     * cabinets, or every owner when neither is stated. So `--owner=innschleife --event=next-event-light` builds Innschleife's
     * rig with Innschleife's counts and leaves PSL's panel out, exactly as the roster files this replaced did when only
     * Innschleife's was named. A system brings only its own gear, which {@see SceneEventOptions::resolve} enforces, so
     * two systems can never state two counts for one device.
     *
     * @param array<string, DeviceSpec> $devices
     *
     * @return array<string, int>|string
     */
    private function countOverrides(InputInterface $input, array $devices): array|string
    {
        $counts = [];
        $this->brought = [];

        /** @var list<string> $owners */
        $owners = (array) $input->getOption('owner');
        if ([] === $owners) {
            foreach ((array) $input->getOption('from') as $id) {
                if (isset($devices[$id])) {
                    $owners[] = $devices[$id]->owner;
                }
            }
        }
        foreach ($this->eventOptions->brings as $owner => $brings) {
            // **A SYSTEM BRINGS ITS OWN GEAR**, checked for every system rather than only the swept ones, so a slip in
            // PSL's counts is caught by an Innschleife run too rather than waiting for the run that reads it.
            foreach (array_keys($brings) as $device) {
                if (!isset($devices[$device])) {
                    return sprintf("systems.%s.brings: no device is called '%s'", $owner, $device);
                }
                if ($devices[$device]->owner !== $owner) {
                    return sprintf('systems.%s.brings names %s, which belongs to %s', $owner, $device, $devices[$device]->owner);
                }
            }
            if ([] !== $owners && !in_array($owner, $owners, true)) {
                continue;
            }
            foreach ($brings as $device => $count) {
                $counts[$device] = $count;
                if ($count > 0) {
                    $this->brought[$device] = $owner;
                }
            }
        }

        /** @var list<string> $stated */
        $stated = $input->getOption('quantity');
        foreach ($stated as $pair) {
            $parts = explode(':', $pair);
            if (2 !== count($parts) || 1 !== preg_match('/^\d+$/', $parts[1])) {
                return sprintf('--quantity=%s expects DEVICE:COUNT, a whole number of units', $pair);
            }
            if (!isset($devices[$parts[0]])) {
                return sprintf("--quantity: no device is called '%s'", $parts[0]);
            }
            $counts[$parts[0]] = (int) $parts[1];
        }

        // Explicit quantities override the event. Cabinets left at home must not fail the inventory check.
        $this->brought = array_filter(
            $this->brought,
            static fn (string $id): bool => $counts[$id] > 0,
            ARRAY_FILTER_USE_KEY,
        );

        // Sorted by device id, so the recorded command line comes out the same whichever order the options were
        // typed in — a replay that differs from its own scene only in the order of two flags is a diff nobody wants
        // to read, and {@see \App\Command\BuildAllCommand} compares those lines.
        ksort($counts);

        // A count that matches the spec is dropped rather than carried: it changes no rig, and carrying it would put
        // a `--quantity` in the recorded line that does nothing, and — worse — would trip the `--into` refusal for a
        // run that has not actually overridden anything.
        //
        // **EXCEPT A DECO DEVICE THAT IS BROUGHT**, whose count is the only thing telling a replay to hang it. One panel
        // matching a spec that says one would otherwise vanish from the line, and the replay would drop the backdrop.
        return array_filter(
            $counts,
            static fn (int $count, string $id): bool => $count !== $devices[$id]->quantity
                || ($count > 0 && StackBackdrop::isDeco($devices[$id])),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The left-to-right order of the stacks, as `--order` states it.
     *
     * A comma list or a repeatable option, both flattened, because the two forms read the same and refusing one of
     * them would be a rule nobody can guess. Empty when nothing was stated, which is what leaves
     * {@see StackSceneWriter::byHeight} in charge.
     *
     * @return list<string>
     */
    private static function statedOrder(InputInterface $input): array
    {
        $order = [];
        /** @var list<string> $stated */
        $stated = $input->getOption('order');
        foreach ($stated as $group) {
            foreach (explode(',', $group) as $name) {
                $name = trim($name);
                if ('' !== $name) {
                    $order[] = $name;
                }
            }
        }

        return $order;
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
            if ('' === $device || [] === $ids) {
                continue;
            }
            $mixes[trim($device)] = [...($mixes[trim($device)] ?? []), ...$ids];
        }

        return $mixes;
    }

    /**
     * The tops that are fill rather than long throw: every one narrower than the widest top in the stack.
     *
     * Not a new idea — {@see StackTops::topRow} already centres the widest and puts "the smaller boxes, which
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
     *
     * @return list<string>
     */
    private function nearFieldFills(array $devices, array $ids): array
    {
        $tops = array_values(array_filter($ids, static fn (string $id): bool => 'sub' !== $devices[$id]->subtype));
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
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
     */
    private function emit(array $candidates, bool $dryRun, bool $force, array $directories): int
    {
        $exit = self::SUCCESS;
        // Cleared here rather than left to accumulate, so the property always describes *this* run. A caller reading
        // it after two runs would otherwise be told the first run's files are current.
        $this->written = [];

        // GENERATED SCENES GO IN THEIR OWN SUBDIRECTORY, and this is the only place that decides it — nothing reads
        // them by path, because {@see SceneLoader::files} is recursive and an id has always been the file's
        // basename. Written next to the hand-written scenes, a regenerated file silently rewrote a comment table
        // somebody had read, and there was nothing in either file saying which kind it was.
        $directory = $this->scenesDir().'/'.SceneLoader::GENERATED;
        // **ONE DIRECTORY PER INVENTORY, WHICH IS SWP-3's SUBFOLDER FOR THE FIRST AXIS.** `sdwa5-sepp` names a
        // folder rather than a dash-padded field in every file name inside it, so the system is stated once instead
        // of 271 times and what varies between siblings is all the name carries. A named rig has no inventory and
        // writes here; a replay of a swept scene *is* a named rig and is told the folder by `--into`.
        //
        // Everything downstream is indifferent to the extra level: {@see SceneLoader::files} is recursive,
        // {@see SceneLoader::isGenerated} looks for `/generated/` anywhere in the path, and a scene's id has always
        // been its basename rather than its path.
        foreach ($candidates as $name => $candidate) {
            // **PER SCENE, BECAUSE THE LAYOUT MAY PUT MORE THAN THE INVENTORY IN THE PATH.** At the default
            // layout every candidate of a run shares one directory — the inventory — and this is the same answer
            // for all of them; with `--folders=shape` two siblings of one run land in two directories.
            $relative = $directories[$name] ?? '';
            $path = $directory.('' === $relative ? '' : '/'.$relative).'/'.$name.'.yaml';
            $yaml = str_replace('id: placeholder', 'id: '.$name, $candidate['yaml']);

            if ($dryRun) {
                $this->io->section($name.'.yaml');
                $this->io->writeln($yaml);
                continue;
            }
            $target = dirname($path);
            if (!is_dir($target) && !@mkdir($target, 0o775, true) && !is_dir($target)) {
                $this->io->error('Could not create '.$this->relative($target));

                return self::FAILURE;
            }
            if (file_exists($path) && !$force) {
                $this->io->text(sprintf('  <comment>exists</comment>  %s — pass --force to overwrite', $this->relative($path)));
                $exit = self::FAILURE;
                continue;
            }
            // **Written whole or not at all**, because `build:all` now replays hundreds of these at once and two
            // recorded commands can name the same file. TOOL-15 is exactly that case: six generated scenes replay
            // from their own line onto a sibling's name, which serially meant one overwriting the other and in
            // parallel would mean two writers interleaving inside one file. A rename is atomic on the same
            // filesystem, so the loser of that race writes a whole file rather than half of each.
            $temporary = $path.'.'.getmypid().'.tmp';
            if (false === file_put_contents($temporary, $yaml) || !rename($temporary, $path)) {
                @unlink($temporary);
                $this->io->error('Could not write '.$this->relative($path));

                return self::FAILURE;
            }

            $this->written[] = $path;
            $this->io->text(sprintf('  <info>wrote</info>   %-44s %d cabinets', $this->relative($path), $candidate['cabinets']));
        }

        if (!$dryRun && self::SUCCESS === $exit) {
            $this->io->newLine();
            $this->io->text('Now: <comment>bin/console scene:build</comment>');
        }

        return $exit;
    }

    /**
     * @return array{float, float}|null
     */
    private function readAt(string $raw): ?array
    {
        $parts = array_map('trim', explode(',', $raw));
        if (2 !== count($parts) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return [(float) $parts[0], (float) $parts[1]];
    }

    private function readFloat(InputInterface $input, string $option): ?float
    {
        $value = $input->getOption($option);

        return null === $value ? null : (float) $value;
    }

    private function describe(LayoutMode $mode, int $blocks): string
    {
        return sprintf(
            'Solved rig — %s, tiers %s',
            1 === $blocks ? 'one stack' : $blocks.' stacks side by side',
            match ($mode) {
                LayoutMode::Center => 'centred',
                LayoutMode::Block => 'justified',
                LayoutMode::Stereo => 'split left and right',
            },
        );
    }
}
