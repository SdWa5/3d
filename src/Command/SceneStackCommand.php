<?php

declare(strict_types=1);

namespace App\Command;

use App\Process\Parallel;
use App\Scene\Alignment;
use App\Scene\CandidateCheck;
use App\Scene\Feasibility;
use App\Scene\GroupStack;
use App\Scene\Interpenetration;
use App\Scene\LayoutMode;
use App\Scene\LowEndBias;
use App\Scene\MirrorStyle;
use App\Scene\Placement;
use App\Scene\PlacementChecks;
use App\Scene\RolledBox;
use App\Scene\SceneCompiler;
use App\Scene\SceneLayout;
use App\Scene\SceneLoader;
use App\Scene\SharedTops;
use App\Scene\SplitMode;
use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\StackChecks;
use App\Scene\StackDeal;
use App\Scene\StackEntry;
use App\Scene\StackOrientation;
use App\Scene\StackSceneWriter;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\SweepAxes;
use App\Scene\SystemGrouping;
use App\Scene\SystemSplit;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Spec\RosterLoader;
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
    private const DEFAULT_MAX_SCENES = 1500;

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

    /**
     * How close two faces count as touching — the same millimetre `ShippedScenesTest` uses, so they agree.
     *
     * Taken from {@see PlacementChecks} rather than restated, because the checks that moved there use the same
     * number for the same reason and two copies of a tolerance drift the first time one of them is tuned.
     */
    private const CONTACT_TOLERANCE_M = PlacementChecks::CONTACT_TOLERANCE_M;

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
            ->addOption('low-end', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Where the lowest cabinets belong: central (on the centre line) or low (on the floor). Default: both')
            ->addOption('folders', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Axes to make directory levels instead of name fields: inventory, stacks, systems, shape, orientation, mirror-style, align, feasibility. Default: inventory')
            ->addOption('into', null, InputOption::VALUE_REQUIRED, 'Subdirectory of scenes/generated/ to write into. Default: the inventory being swept')
            ->addOption('align', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'center, block or stereo. Default: all three')
            ->addOption('shape', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'pyramid (rows narrow going up) or free (as wide as bearing allows). Default: both')
            ->addOption('mirror-style', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What a turned row does with its odd cabinet: alternate (side flips per row), centred (unrolled in the middle) or column (same side every row). Default: all three where something is rolled')
            ->addOption('orientation', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which cabinets lie on their sides: upright (none), turned (every sub) or mixed (only where it makes them wider). Tops never roll. Default: all three')
            ->addOption('roll-mirror', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids to lay on their sides, mirrored about the centre line. Repeatable')
            ->addOption('mix', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Share a row: DEVICE:OTHER[,OTHER]. Repeatable. Lowers a stack by merging tiers')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Build from these owners\' gear only. Default: sweep every combination of them')
            ->addOption('roster', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A file in rosters/ stating what a system brings to one event. Overrides the specs\' quantities. Repeatable')
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        $devices = [];
        foreach ($specs as $spec) {
            $devices[$spec->id] = $spec;
        }

        // **APPLIED BEFORE ANYTHING READS A QUANTITY, WHICH IS WHY IT IS THE FIRST THING THAT HAPPENS.** A count
        // reaches the solver through a dozen paths — the fill order, the by-type balance, the owner list, the
        // silhouette widths — and threading an override down all of them would be a dozen chances to miss one. The
        // specs are rewritten here instead, once, and everything downstream goes on reading `->quantity` in
        // ignorance. See {@see \App\Spec\Roster} for why the count lives outside the spec file at all.
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

        // **THE OUTPUT DIRECTORY IS THE RUN'S INVENTORY, AND A RUN HAS EXACTLY ONE.**
        // {@see SweepAxes::inventory} returns a single subset, so every rig in `$rigs` shares a label and
        // reading it off the first is a fact rather than a shortcut. A stated `--into` wins, which is what a replay
        // uses: the recorded line names a cabinet list rather than an owner, so it cannot re-derive its own folder.
        $into = (string) ($input->getOption('into') ?? '');
        /** @var list<string> $rosters */
        $rosters = $input->getOption('roster');
        if ('' === $into && 1 === count($rosters)) {
            // **ONE ROSTER NAMES THE FOLDER, BECAUSE THE RIG IT BUILDS IS NOT THE INVENTORY'S RIG.** Both variants of
            // Innschleife's next event are `--owner=innschleife`, so both would be filed under `innschleife/` beside
            // the rigs built from everything they own, under the same file names, and the last run would win. The
            // roster's id is the one name that tells the three apart.
            $into = $rosters[0];
        }
        if ('' === $into && [] !== $counts) {
            // **A CHANGED RIG UNDER AN UNCHANGED NAME IS THE ONE FAILURE THIS COMMAND MUST NOT HAVE.** Every other
            // axis is in the file name or in the folder, so two different rigs cannot collide; a count override is
            // in neither, and the sweep would quietly write its files over the ones a bare sweep just wrote. Two
            // rosters cannot pick between their own names either, so both cases end here.
            $this->io->error(
                '--quantity changes the rig without changing its name — say --into=NAME for the folder to write it '
                .'into, or state the counts as a single --roster, whose id names the folder',
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
        // always been nullable and {@see StackSolver::ceilingFor} has always read null as "no bound at all" — the
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
                foreach (SweepAxes::pairs($orientations, $styles, $rolled, $devices, $rig['from']) as [$orientation, $style]) {
                    foreach ($modes as $mode) {
                        foreach ($lowEnds as $lowEnd) {
                            // **EVERY AXIS IS IN THE NAME, AT A FIXED WIDTH**, in the order the sweep nests them: the rig
                            // (owners and stack count), then shape, orientation, mirror style, alignment. See
                            // {@see SweepAxes::padded} for why the widths, and {@see SweepAxes::STATED_ORIENTATION} for the one value that has
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
                                'orientation' => $orientation?->value ?? SweepAxes::STATED_ORIENTATION,
                                'mirror-style' => $style->value,
                                'align' => $mode->value,
                                'low-end' => $lowEnd->value,
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
     * @return array{yaml: string, cabinets: int, fingerprint: string, bandMiss: ?string}|string
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

        $blocks = [];
        $best = -1;
        $bestMiss = INF;
        $target = $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M;
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
            }
        }

        if ([] === $blocks) {
            return $firstProblem ?? 'no workable arrangement';
        }

        // Measured before the blocks are reordered, and **reported rather than refused whoever asked for it**. The
        // sweep used to treat a wall outside the band as a rig for a different stage and a named rig as a warning,
        // which was two answers to one question; now both are the warning, and there is no stage to move it to.
        $bandMiss = StackChecks::bandMiss($blocks);

        $blocks = StackSceneWriter::byHeight($blocks, $mode, self::statedOrder($input));

        $clearance = (float) $input->getOption('clearance');
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
        );

        // Compiled before it is written. Anything the compiler calls an error means this arrangement is not
        // one of the possibilities, whatever the solver thought of the tiers.
        $compiled = CandidateCheck::compileYaml($yaml, $devices);
        if (is_string($compiled)) {
            return $compiled;
        }

        return ['yaml' => $yaml, 'bandMiss' => $bandMiss] + $compiled;
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
    ): array|string {
        $blocks = [];
        foreach ($groups as $key => ['ids' => $ids, 'index' => $index, 'of' => $of]) {
            // Cast, because PHP turns an array key that looks like a number into one — `--stacks=2` without
            // `--per-owner` labels the groups "1" and "2", which arrive here as ints.
            $label = (string) $key;
            $block = $this->solveGroup(
                $devices,
                $ids,
                $label,
                $mode,
                $shape,
                $style,
                $orientation,
                $lowEnd,
                $maxWidthM,
                $input,
                count($groups) > 1,
                $index,
                $of,
                $evenSplit,
                $placeAll,
                $deal[$label] ?? [],
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
     * @param array<string, array{array{from: list<string>, stacks: int, inventory: string, suffix: string}, StackShape, MirrorStyle,
     *     ?StackOrientation, LayoutMode}> $tasks
     * @param array<string, DeviceSpec> $devices
     * @param list<float> $at
     *
     * @return array<string, array{yaml: string, bandMiss: ?string, cabinets: int, fingerprint: string}|string>
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
                    self::probePlacement($placementId, $stack, $mode),
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
            // The width the *caller* settled on, and **null is one of the answers** rather than a missing value:
            // an unstated `--max-width` is a stage nobody bounded, which {@see StackSolver::ceilingFor} reads as no
            // bound at all. Passed down rather than read off the option here, so there is one place that decides it.
            maxWidthM: $maxWidthM,
            minWidthM: $this->readFloat($input, 'min-width'),
            maxHeightM: $this->readFloat($input, 'max-height'),
            interfaceHeightM: (float) $input->getOption('interface-height'),
            gapM: (float) $input->getOption('gap'),
            mirror: $mirror,
            maxSubHeightM: $this->readFloat($input, 'max-sub-height'),
            targetSubHeightM: $this->readFloat($input, 'target-sub-height') ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M,
            shape: $shape,
            mirrorStyle: $style,
            lowEnd: $lowEnd,
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
     * The count every device is built with where that is not the number its spec states, or the reason the run
     * cannot start.
     *
     * **Two ways in, and they are the same fact at two levels of permanence.** `--roster` reads a file somebody
     * committed, which is where "what Innschleife brings on the 6th" belongs; `--quantity` is the same statement
     * typed at a shell, for the question nobody will ask twice. So `--quantity` wins where both name a device: the
     * typed value is the newer of the two by construction, and a file that a caller has deliberately overridden on
     * the command line is not an argument for refusing to run.
     *
     * **Two rosters naming the same device is a refusal, though**, and the difference is worth stating. Neither file
     * is newer than the other, both were written on purpose, and picking one by argument order would make the rig
     * depend on the order two options were typed in. There is nothing to prefer, so there is nothing to do but say
     * so and name both files.
     *
     * @param array<string, DeviceSpec> $devices
     *
     * @return array<string, int>|string
     */
    private function countOverrides(InputInterface $input, array $devices): array|string
    {
        $loader = new RosterLoader($this->rostersDir());
        $counts = [];
        $statedBy = [];

        /** @var list<string> $rosters */
        $rosters = $input->getOption('roster');
        foreach ($rosters as $id) {
            try {
                $roster = $loader->load($id);
            } catch (InvalidSpecException $e) {
                $available = $loader->available();

                return sprintf(
                    '--roster=%s: %s%s',
                    $id,
                    $e->getMessage(),
                    [] === $available ? '' : ' (there is '.implode(', ', $available).')',
                );
            }

            foreach ($roster->brings as $device => $count) {
                if (!isset($devices[$device])) {
                    return sprintf("%s: no device is called '%s'", $this->relative($roster->sourcePath), $device);
                }
                if (isset($statedBy[$device]) && $counts[$device] !== $count) {
                    return sprintf(
                        '--roster: %s says %d× %s and %s says %d× — the two rosters disagree and neither is newer',
                        $statedBy[$device],
                        $counts[$device],
                        $device,
                        $id,
                        $count,
                    );
                }
                $counts[$device] = $count;
                $statedBy[$device] = $id;
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

        // Sorted by device id, so the recorded command line comes out the same whichever order the options were
        // typed in — a replay that differs from its own scene only in the order of two flags is a diff nobody wants
        // to read, and {@see \App\Command\BuildAllCommand} compares those lines.
        ksort($counts);

        // A count that matches the spec is dropped rather than carried: it changes no rig, and carrying it would put
        // a `--quantity` in the recorded line that does nothing, and — worse — would trip the `--into` refusal for a
        // run that has not actually overridden anything.
        return array_filter($counts, static fn (int $count, string $id): bool => $count !== $devices[$id]->quantity, ARRAY_FILTER_USE_BOTH);
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
     * A stand-in for the placement this block will be written as, for the seating check alone.
     *
     * **Only what changes the stack's own geometry is carried**, which is its id, its `stack` and its alignment. The
     * ground position is not: {@see Interpenetration} compares cabinets against each other, so moving the whole rig
     * moves both sides of every pair and changes no answer. Standing it at the origin also keeps the check
     * independent of `--at`, which is what makes the same arrangement judged the same way wherever it is placed.
     *
     * The rest is the writer's default for a stack: no device of its own, no yaw, pitch or roll, nothing to stand on
     * and no fly. A stack that ever gains one of those has to gain it here too, and the sweep would say so
     * immediately — the command and the compiler would start disagreeing again, which is the failure this exists to
     * prevent.
     */
    private static function probePlacement(string $id, Stack $stack, ?LayoutMode $mode): Placement
    {
        return new Placement(
            id: $id,
            deviceId: null,
            at: [0.0, 0.0],
            yawDeg: 0.0,
            pitchDeg: 0.0,
            rollDeg: 0.0,
            aimAt: null,
            // **THE AIM MATTERS AND LEAVING IT OUT WAS MEASURED WRONG.** A top tier is turned towards the focus, and
            // a turned cabinet's outermost corner moves — so a probe that judged the tops firing straight ahead was
            // measuring a different rig from the one the file states. Left out, the `all` inventory's turned rigs
            // came back with 0.660 m of subs, because nearly every candidate was refused over an overlap that only
            // existed in the probe. {@see StackSceneWriter::focusPoints} is the one definition both sides read.
            aimFocus: StackSceneWriter::AIM,
            on: null,
            fly: null,
            group: new GroupStack([]),
            align: null === $mode || LayoutMode::Center === $mode ? null : new Alignment($mode),
            stack: $stack,
        );
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
