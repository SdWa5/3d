# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.133.0] - 2026-10-01

### Changed

- In `next-event-light` ours and Sepp's take a 1.6 m interface aimed at 1.75 m. Our stack goes from three rows of
  Flexy at 3.551 m to two rows of [3 Flexy | SKRAM | 3 Flexy] at 2.788 m, next to PSL's 2.55 m and Innschleife's
  2.85 m. The folder goes from 20 scenes to 18. Four `v` stereo layouts no longer fit the room and two `free` ones
  are added.
- PSL's deco panel is 9.00 x 1.80 m instead of 10.00 x 3.03 m, renamed from `deco-panel-10x3-03` to
  `deco-panel-9x1-8`. Its print is stretched from 3.3:1 to 5:1 on purpose, and its estimated weight goes from 45.45 kg
  to 24.3 kg, so each tower carries 35.4 kg. Under the 4 m ceiling its bottom edge is at 2.2 m.
- Every generated folder is regenerated with both changes and the fix below.

  | folder | before | after | rewritten | removed | added |
  | --- | --- | --- | --- | --- | --- |
  | `sdwa5-sepp` | 132 | 132 | 0 | 0 | 0 |
  | `gmss` | 112 | 114 | 4 | 0 | 2 |
  | `gmss-sepp` | 367 | 371 | 16 | 4 | 8 |
  | `gmss-sdwa5` | 456 | 464 | 25 | 5 | 13 |
  | `gmss-sdwa5-sepp` | 496 | 497 | 29 | 14 | 15 |
  | `sdwa5` | 64 | 68 | 0 | 0 | 4 |
  | `sepp` | 8 | 8 | 0 | 0 | 0 |
  | `innschleife-psl-sdwa5-sepp` | 446 | 443 | 12 | 24 | 21 |
  | `innschleife-next-event` | 25 | 25 | 0 | 0 | 0 |
  | `psl-next-event` | 11 | 11 | 11 | 0 | 0 |
  | `psl-next-event-light` | 13 | 13 | 13 | 0 | 0 |
  | `next-event` | 8 | 8 | 8 | 0 | 0 |
  | `next-event-light` | 20 | 18 | 16 | 4 | 2 |

- `docs/scenes.md` carries the new counts, 2172 scenes in all.

### Fixed

- A tops row over a sub row with a raised middle is seated around the middle instead of slid sideways. Over
  [3 Flexy | SKRAM | 3 Flexy] the SKRAM stands 0.302 m above the Flexy, the outboard repair gave up on a raised
  middle, and the slide pushed the whole row 0.77 m off centre. Gravity now also tries `raisedMiddleSeats`, which
  puts the widest centred run on the raised middle and mirrors the rest outwards from its edges.

## [0.132.0] - 2026-10-01

### Added

- `events/next-event-light.yaml`, the next event without any Achenbach and with nine ESX instead of twelve, generated
  into `next-event-light` with 20 scenes and `psl-next-event-light` with 13.

### Changed

- Innschleife bring two TMS-2 and one TMS-4 to the next event, so their stereo tops row is [TMS-2 | TMS-4 | TMS-2].
- PSL bring four EF 6 to both next events instead of five.
- Every generated folder is regenerated. `sdwa5-sepp` was last swept at 0.122.0 and the others at 0.120.0, so these
  counts also carry the commits since then. Swept on the 0.131.0 code without this release, `sdwa5-sepp` comes out
  byte-identical to what this release writes, so its change is that drift alone. The other folders were not split by
  cause.

  | folder | before | after | rewritten | removed | added |
  | --- | --- | --- | --- | --- | --- |
  | `sdwa5-sepp` | 166 | 132 | 0 | 38 | 4 |
  | `gmss` | 111 | 112 | 0 | 8 | 9 |
  | `gmss-sepp` | 353 | 367 | 0 | 23 | 37 |
  | `gmss-sdwa5` | 482 | 456 | 7 | 92 | 66 |
  | `gmss-sdwa5-sepp` | 477 | 496 | 6 | 63 | 82 |
  | `sdwa5` | 66 | 64 | 1 | 12 | 10 |
  | `sepp` | 7 | 8 | 0 | 0 | 1 |
  | `innschleife-psl-sdwa5-sepp` | 422 | 446 | 15 | 47 | 71 |
  | `innschleife-next-event` | 25 | 25 | 25 | 0 | 0 |
  | `psl-next-event` | 10 | 11 | 10 | 0 | 1 |
  | `next-event` | 6 | 8 | 6 | 0 | 2 |

- `docs/scenes.md`, `docs/catalog.md` and the README carry the new counts. The library is five systems, 39 devices
  and 102 units.

### Fixed

- A stereo tops row is spread by its own groups instead of by gravity's runs. Gravity merged PSL's five EF 6, built as
  `2× | 1× | 2×`, into runs of 2, 2 and 1 over three ESX columns, so the odd top sat 0.31 m right of the centre line.
  Now each group moves as one, and the row is mirrored to the millimetre.
- The same fix makes `sdwa5`'s three stereo `central` rigs stand up, which 0.131.0 still writes as impossible. The
  feasibility test takes its impossible rig from a pinned `gmss` and Sepp sweep instead.

### Removed

- `top-70x93`, the black Innschleife top estimated off their photo, and every reference to it.

## [0.131.0] - 2026-10-01

### Added

- An event states where each system wants its lowest cabinets, as `systems.<owner>.low_end`, and
  `--system-low-end=OWNER:MODE` states the same directly. A stack follows the value its subs' systems agree on, and
  the recorded line carries it.
- The next event puts ours and Sepp's low end `central` and Innschleife's `low`, so the combined rig carries two rows
  of [3 Flexy | SKRAM | 3 Flexy] and Innschleife's photo rows at once. PSL states none.

### Changed

- A rig whose every system states the same low end is swept once, named `stated`, instead of once per low end.
- `SweepAxes::STATED_ORIENTATION` is `SweepAxes::STATED`, since the low-end axis uses the value too.
- `innschleife-next-event` keeps its 25 `low` scenes, renamed `stated`, and loses the 18 `central` ones.
- `next-event` goes from 26 scenes to 6, all `pyramid` and all carrying Innschleife's photo rows. Its four
  `systems-apart` rigs now stack ours as two rows of [3 Flexy | SKRAM | 3 Flexy] over six Achenbach.
- `psl-next-event` changes only in its recorded lines.

## [0.130.0] - 2026-10-01

### Added

- An event states what each system brings, as `systems.<owner>.brings`, counts that override the specs for the swept
  systems. A negative count and an empty map are refused, and a system may name only its own devices.
- `events/mark-salzburg-2026-09-19.yaml`, the counts sdwa5 took to Salzburg.

### Changed

- An event's `room` is optional, and an event without one sets no room limit.
- One `--owner` swept with an event is filed as `<owner>-<event>`, the folder name the roster files carried.
- `events/next-event.yaml` holds PSL's and Innschleife's counts. The generated scenes are byte-identical.

### Removed

- `--roster`, `Roster`, `RosterLoader` and `rosters/`, folded into the event files.

## [0.129.1] - 2026-10-01

### Added

- `TODO.md` rows GEO-15, TOOL-23 and SCN-12, filed as proposals, and the `proposal` state that marks them.

## [0.129.0] - 2026-10-01

### Added

- An event may state `stack_clearance_m`, the air between neighbouring stacks for its runs. An explicit `--clearance`
  replaces it, and the recorded line carries the number.
- The next event leaves 0.24 m between stacks. The combined rig with Innschleife's photo layout is 12.995 m wide then,
  where the default 0.5 m made it 13.515 m and the 13 m room refused it.

### Changed

- `innschleife-next-event`, `psl-next-event` and `next-event` regenerated with the narrower gaps. `next-event` now
  holds the photo rig in four combined `systems-apart` rigs, all `pyramid` with the low end `low`, and grows from
  20 scenes to 26.

## [0.128.0] - 2026-10-01

### Added

- An event states how each system is set up, as `systems.<owner>.orientation`, and which cabinets stand as measured
  anyway, as `systems.<owner>.stand`. `--system-orientation=OWNER:MODE` and `--stand=ID` state the same directly, and
  the recorded line carries both.
- The next event sets ours and Sepp's gear upright and PSL's and Innschleife's turned, with Innschleife's kickers
  standing as on their photo.

### Changed

- A rig whose every system has a stated orientation is swept once, named `stated`, instead of once per orientation.
  With only some systems stated, an orientation that rolls the same cabinets as an earlier one is dropped.
- An event's system may state an orientation without an interface and a target. Those two still come as a pair.
- `innschleife-next-event`, `psl-next-event` and `next-event` regenerated under the stated orientations.

## [0.127.0] - 2026-10-01

### Changed

- A deco panel's top goes up to the room's ceiling, but at most half the panel stands above the truss it hangs from.
  Under the next event's 4 m ceiling the truss already touches it, so no generated scene changes. With no ceiling the
  10 × 3.03 m panel's top rises from 4.258 m to 5.773 m. The floor refusal reads the panel's new top.

## [0.126.0] - 2026-10-01

### Added

- PSL's deco panel carries their print as its `front_image`, mapped at 1.978 px/cm from the 1978 × 600 px file. The
  file lives at `meshes/psl/deco-panel-front.jpg`, which is gitignored like every front image, and `docs/sources.md`
  records where it came from.

### Changed

- The deco panel is 10 × 3.03 m instead of 10 × 2.5 m, and its id is now `deco-panel-10x3-03`. The estimated weight
  follows at 1.5 kg/m², so it is 45.45 kg and each tower carries 45.975 kg of its 85 kg rating. Under the 4 m ceiling
  the panel's bottom is at 0.97 m.
- `psl-next-event` and `next-event` regenerated with the new panel. Both keep their scene names, and only the backdrop
  lines changed.

## [0.125.1] - 2026-10-01

### Changed

- **`TODO.md` compacted from 1062 lines to 568.** Every open row is kept, and the history the CHANGELOG already holds is
  cut from the detail blocks. The order list no longer names SWP-3, which closed in 0.100.0, and the SWP section and
  CVR-3's block are gone with it. Where two texts disagreed the newer one is kept, so GEO-14 is P2 and GEO-9 is P1.
  Tables and sections are sorted by priority as the file's own rules ask, and the GEO-14 row no longer breaks its table
  on an unescaped pipe.
- CVR-6 no longer waits on CVR-5, which shipped. TOOL-21 waits on TOOL-20 alone, since the repository went public and
  billing is gone. TOOL-16 states the command's current 1571 lines, and TOOL-20 the local suite at 11 min 25 s.
- The negative result on splitting a tops row moved from GEO-2's block to INFO-3. SPEC-3 states the Varytec's 1.6 m
  outrigger spread and that we own two stands.

## [0.125.0] - 2026-10-01

### Added

- `.github/phpunit-shards.json`, which maps each CI shard of the suite to a PHPUnit `--filter`.
- A `static` step that fails unless the shards together list every test exactly once.

### Changed

- The `phpunit` job runs as three parallel shards, `scene-stack-command`, `other-commands` and `rest`, built from the
  shard file by a new `shards` job. `timeout-minutes: 90` now applies per shard.
- `bin/console specs:validate` runs in `static` instead of `phpunit`, so it runs once rather than once per shard.
- TOOL-20 and TOOL-21 in `TODO.md` record the public four-core runner and the shards instead of the billing block.

### Fixed

- `README.md` no longer calls the `docs` repository private or the SdWa5 repositories about to go public.

## [0.124.0] - 2026-10-01

### Added

- **A deco backdrop behind a generated rig.** A roster that brings a `deco` device gets a truss standing behind the
  rig on two towers, with the panel hung flush on its front. `StackBackdrop` derives every position. The truss tops out
  at the room ceiling or at the towers' full extension, and the towers stand 0.8 m behind the deepest back face so
  their unmodelled outriggers clear the cabinets. The whole scene is compiled again with it, so the room, overlap and
  floating checks see it.
- `deco-panel-10x2-5`, PSL's 10 × 2.5 m panel, with an estimated 37.5 kg. `rosters/psl-next-event.yaml` brings it.
- `events/next-event.yaml` names the backdrop as five `truss-f33-2m` on two `truss-tower-4m`. Under the 4 m ceiling
  the truss rests at 3.742 m and each tower carries 42 kg.
- `scene:stack --backdrop=TRUSS:SEGMENTS:TOWER`, recorded in the replay line only when a deco device is hung.
  A deco device with no truss named, a panel wider than the truss, more segments than are owned and a tower load over
  its rating are refused.
- `extend_to_m` on a placement, the height a truss tower is cranked to. The compiler places a shortened copy of the
  spec and Blender scales the model along its height. Any other device, and any height above the spec's, is refused.
- `physical.max_load_kg` in the spec format. `truss-tower-4m` states its published 85 kg.
- `StackBackdropTest`, `ExtendToTest`, and backdrop cases in `SceneStackEventTest` and `EventTest`.
- The panel's unknown weight, depth and print in `docs/requests.md`.

### Changed

- A brought deco device keeps its count in the recorded line even when it matches the spec, so a replay keeps the
  backdrop.
- Regenerated `psl-next-event` and `next-event` with the backdrop. Every rig kept its feasibility. The committed tree
  has 2,219 scenes.
- `docs/catalog.md` regenerated. It was missing `top-70x93` since 0.122.0.
- `SceneStackRosterTest` leaves the panel at home where it runs without an event.

### Removed

- `psl-next-event/stacked-2-pooled--------pyramid-upright-alternate-stereo-low-----possible.yaml`. It is the same rig
  as its `block` sibling, and only floating-point noise of half a micrometre kept the two apart in 0.123.0.

### Fixed

- Two `StackSolverTest` cases that had failed since 0.122.0. The full pooled inventory now builds repeated flanked
  rows instead of a gapped row, so the gap-out case uses one SKRAM, and the refused-gap case no longer expects a
  refused rig. A new case pins the flanked rig.

## [0.123.0] - 2026-10-01

### Added

- `events/next-event.yaml` saves the 13 m room width and 4 m ceiling.
- `scene:stack --event`, `--room-width` and `--room-height` refuse rigs outside their compiled world bounds.
  An explicit room option can tighten a saved event limit but cannot loosen it.
- Owner-specific interface and target preferences follow each system's subs, including walls with borrowed tops.
  Innschleife uses a 1.6 m interface and a 1.75 m target. Pooled sub walls keep the ordinary defaults.
- Recorded commands save resolved room limits and system preferences instead of an editable event id.
- Focused room, event parsing, generation and owner-preference tests.

### Changed

- Regenerated the three next-event inventories with their saved room limits and the photo-top roster.
  Innschleife has 87 scenes, PSL has 16, and the joint event has 33. The committed tree has 2,220 scenes.

## [0.122.0] - 2026-10-01

### Added

- **Repeated flanked rows, the rig on Innschleife's photo.** `StackMix::flankedRows()` splits the widest sub
  evenly over two or more rows and flanks each row with the same pairs of a sub type no more than 30 mm taller or
  shorter, which builds two rows of [WSX | SBH SBH | WSX]. `StackMix::levelFlanks()` names the types that qualify.
  `StackSolver::fill()` offers one candidate per qualifying flank and row budget, ranked like every other.
- **`top-70x93`, Innschleife's black middle top**, estimated off the photo against the TMS-2 beside it.
- **`scene:stack` refuses a roster whose cabinets the swept inventory does not hold**, naming them and the owner
  to state.
- `StackMixFlankedRowsTest`, and refusal and zero-quantity override cases in `SceneStackRosterTest`.
- A GitLab test configuration adapted from the shared Composer PHPUnit job.

### Changed

- **`rosters/innschleife-next-event-tms4.yaml` is `rosters/innschleife-next-event.yaml`** and brings the photo's
  tops, two TMS-2 and the `top-70x93`, with the TMS-4 at zero.
- **`scenes/generated/innschleife-next-event/` replaces `innschleife-next-event-tms4/`.** It is swept with
  `--owner=innschleife` at a 1.6 m interface aimed at 1.75 m and holds 95 scenes, four of them the photo row for
  row.

- Replayed all 2,675 generated scene commands. Removed ten obsolete feasibility counterparts. Six next-event
  scenes become possible; the joint Innschleife inventory has two changes in each direction.

### Removed

- `scenes/generated/innschleife-next-event-tms4/`. Its 146 scenes were swept without `--owner=innschleife` and held
  no Innschleife cabinet.

## [0.121.0] - 2026-10-01

### Added

- **`scene:build` and `scene:render` take a folder.** A folder relative to `scenes/` or as a path selects every
  scene below it, so `scene:render scenes/generated/innschleife-psl-sdwa5-sepp` renders one event's inventory on
  its own. A folder with no scene in it is refused. `SceneLoader::filesUnder()` is new.
- `SceneLoaderFolderTest`, and folder cases in `SceneBuildCommandTest`.

### Changed

- The scene selection of both commands is one `BaseCommand::selectScenes()`, and an ambiguous basename now
  suggests naming its folder.

## [0.120.0] - 2026-10-01

### Added

- **The solver gaps a row out to its shape (GEO-13).** Where a packed arrangement breaks the width rule of
  `pyramid` or `v`, the fill also offers it with the offending row gapped out, one even gap across the row.
  A pyramid widens the row under a too-wide row, and a V widens a sub row to the full width of the one below.
  The candidate is ranked like any other, and a row that packs to its shape is never gapped.
- **`Tier` carries its own gap.** `withGap()`, `gapFor()` and `cabinetWidthM()` are new, and `widthM()`,
  `seats()`, `mirrored()`, `flipped()` and `label()` honour the gap. A gapped row is labelled
  `… at 148 mm gaps`, which also keeps the seating memo from sharing a verdict between two gaps.
- `StabilityTest`, and GEO-13 cases in `TierTest` and `StackSolverTest`.

### Changed

- **A gapped row is placed at its own gap.** `Gravity::resolve()` and its repairs, the lattice in
  `Stack::expand()`, the alignment inset and the `LowEndCost` pitch all read the row's gap.
- **Each cabinet of a gapped row is its own run and its own body.** `Gravity::runs()` merges no two of them,
  so the row above never gets a top face over the air between them. `Stability::tips()` weighs each one
  against the supports it touches, and a centre over the gap between two supports it rests on still stands.
- **`SceneCompiler::stackSurvives()` refuses a gapped arrangement with a floating cabinet.** A gapped top row
  can pass the bearing rules and still stand over air once placed, so the seating predicate also asks
  `PlacementChecks::floatingFaults()` whenever a row is gapped.
- `SceneStackFeasibilityTest` shows its impossible rig on `sdwa5` pulled onto the centre line, because every
  `gmss` rig stands up now.
- **Every generated scene is replayed from its recorded command.** 968 changed, 112 were renamed and 20 are
  new. The 20 new ones are "central" variants in `sdwa5-sepp` that used to dedupe into their "low" sibling,
  so that folder holds 166. 89 rigs stand up now that did not, and 23 flipped the other way. All 23 were
  already refused on main, whose last replay of those folders was 0.104.0. Gaps run from 26 mm to 1200 mm.
  The tree holds 2726 scenes, 171 of them impossible against 237 before.

## [0.119.0] - 2026-09-29

### Added

- **What the 8x8 did on the bench, set against the MARK Salzburg rig.** `docs/signal-chain.md` gains a
  section on the loopback runs of 2026-09-29. It covers the ceiling on the scale the thresholds use,
  ratio 1:1.0, the image a working limiter leaves on the mix, and where the output gain sits against
  the limiter. The runs themselves are in trackdsp's `docs/measurements.md`.
- **`SIG-7`, pinning the 8x8's threshold scale to dBu.** The 8x8 clips at about +12.5 on that scale
  for a 100 Hz signal, where the limiter table assumes 18 dBu. A voltmeter on a 100 Hz sine decides
  whether the headroom column or the sub thresholds are wrong.
- **Two open rows for the rig.** One is the threshold scale against dBu, and the other is a broken
  OUT8 connection on the output panel of the 8x8's rack.

### Changed

- **`SIG-6` knows what the gain in question does on the 8x8.** The output gain acts before the
  limiter, so a gain equal to the threshold drives the programme harder into the limiter without
  moving the limit. What the sheet meant by the column is still open.

### Fixed

- **The gain sweep no longer takes the 8x8's 18 dBu on trust.** 32 dB is the lowest MM14K step with
  6 dB left only if the 8x8 reaches 18 dBu. If its threshold scale is dBu it reaches about 12.5, 32 dB
  leaves 0.70 dB and the step is 38 dB. The sweep section now says so.
- **`1:1 off` holds only at 0 dB of output gain.** At ratio 1:1.0 the 8x8 applies any other gain more
  strongly than set and mirrors the signal at 24 kHz. `docs/signal-chain.md` names Limit or 1:1.1 with
  the threshold at +20 dB as the setting that is really off.

## [0.118.0] - 2026-09-19

### Added

- **The limiter arithmetic is PHP with tests instead of formulas in a spreadsheet.**
  `src/Signal/LimiterSetting.php` computes a DSP output's limiter threshold from one cabinet's
  impedance, its RMS rating and the amplifier's gain, and reports the headroom left to the DSP's own
  ceiling. Every expected value in `tests/Signal/LimiterSettingTest.php` is one the `AmpLimiterCalc`
  sheet in Drive already produced, so the suite is a port check as much as a unit test.
- **`lowestUsableGain()` picks a DIP switch setting from the bottom rather than the top.** Gain moves
  a limiter threshold down one for one, so every step up throws away that much of the DSP's usable
  output range. The method returns the lowest step that still clears a stated margin, and returns
  nothing rather than a least-bad step when none does.
- **`docs/signal-chain.md`, and the MARK Salzburg rig of 2026-09-19 as its first entry.** Twelve
  Flexy, two SKRAM, two Tecnare, four MM14K, one TIP10000q and both DSPs, with the patch, the
  thresholds, the gain sweep behind the two settings chosen, the cable list, the phase split, and the
  eight things left open on site.
- **The cable list and the mains split for that rig.** Nineteen XLR runs, sixteen Speakon runs, and
  three phases balanced on the amplifiers rather than on how the racks are packed. The balanced split
  holds 13.8 A on its heaviest phase where the rack-shaped split would draw 18.4 A on a 16 A breaker,
  both at the same assumed load fraction.
- **`rosters/sdwa5-mark-salzburg-2026-09-19.yaml`.** Two of the three Tecnare travelled, which is the
  only count that differs from a spec. The Flexy and the SKRAM are restated at their spec figures
  anyway, so the rig on that stage is readable from the one file.

### Changed

- **How a rig gets written down is settled: master data, one patch per event, table derived.** The
  alternatives were a single reusable table edited per event, which holds exactly one rig and loses
  the last one, and a template copied per event, which the Drive has already demonstrated — four
  files named `AmpLimiterCalc.csv` in one folder with three distinct sizes and no way to say which
  one a rig is set to. `SIG-1`'s remaining effort drops from 12h to 9h, because the arithmetic half
  of it is now done.
- **`SIG-3` no longer infers its schema from a spreadsheet alone**, since one real patch is written
  down in prose beside it.

### Fixed

- **The DCX's outputs are patched out of channel order, so each top takes one cable instead of two.**
  A four-channel amplifier pairs its NL4 sockets 1 with 2 and 3 with 4, so ordering the tops LF L,
  HF L, LF R, HF R puts one whole Tecnare on each socket. The obvious ordering splits each cabinet
  across both sockets and needs either two cables per top or a hand-made one.
- **Two figures the Drive recommends were wrong for this rig, and the sweep says why.**
  `Amp_GainSelector.csv` asks for 41 dB on the TIP10000q and 44 dB on the MM14K. At 44 dB the subs'
  threshold lands at −0.20 dBu, which gives away 18 dB of the 8x8's output range. The settings
  recorded are 32 dB and 26 dB, each the lowest step on the same switch that still clears 6 dB.

## [0.117.4] - 2026-09-15

### Security

- **The authorship trailers are out of every commit message.** 122 of this repository's 166 commits
  carried one and 122 carried a session link. They came out through a `git-filter-repo` message
  callback rather than a text replacement, because a replacement only empties the text and leaves the
  blank line behind, and the callback is scoped to Claude and Anthropic so an attribution line naming
  a human would survive. There was none.
- **The content is provably untouched.** 166 commits before and after, and the tree at `HEAD` is
  `c586aec3` before and after, so only commit objects changed.
- **Republished rather than force-pushed**, which leaves no pre-rewrite objects in GitHub's cache.
  Verified: every probe term returns zero over every blob and every commit message, a pre-rewrite SHA
  answers `not our ref`, and a control fetch of `main` succeeds. This repository was audited on
  2026-09-14 and came back clean again on 2026-09-15, this time with the scanner genuinely run without
  its allowlist, which the earlier pass had not managed.

## [0.117.3] - 2026-09-15

### Fixed

- **Two places quoted a commit hash that the rewrite of the night before had already invalidated.**
  `CHANGELOG.md` and `TODO.md` both named the commit `TOOL-20`'s newest data point was measured on.
  They now describe it instead, because every rewrite of this history invalidates a hash and it has
  had five. Run numbers, dates and versions survive and are kept.

## [0.117.2] - 2026-09-15

### Security

- **The history was rewritten a fourth time, so that the PSL decision is real rather than cosmetic.**
  0.117.0 and 0.117.1 took the two statements out of the working tree, and a working tree is not the
  repository. Both stood in 28 of this repository's 162 commits and one of them in two commit
  messages, which would all have become public with the repository. `git-filter-repo` ran over file
  contents and commit messages together, with 13 replacements.
- **The content is provably untouched.** The commit count is 162 before and after and the tree at
  `HEAD` is `d440b3e8` before and after, so only commit objects changed. Re-measured afterwards over
  every blob and every message: seven probe phrases, zero matches each.
- **Republished rather than force-pushed**, by renaming the repository, creating it empty, pushing,
  confirming the tip and then deleting the old one. A force-push leaves the pre-rewrite commits
  reachable by SHA in GitHub's cache; recreating leaves no cache. Verified afterwards by fetching a
  pre-rewrite SHA, which answers `not our ref`, against a control fetch of `main` that succeeds.

## [0.117.1] - 2026-09-14

### Fixed

- **0.117.0 took the two PSL statements out of two files and left them standing in two others.** It
  cleaned `rosters/psl-next-event.yaml` and `docs/requests.md`, which are the files it named, and did
  not re-run the search afterwards. What was left: this file's 0.109.0 entry asserted outright what PSL
  own, in stronger words than the roster ever used; `docs/sources.md` carried the same verdict about
  their published page that `docs/requests.md` had just lost, in the very file the third-party
  clearance was written about; and the 0.117.0 entry itself reproduced both statements while
  describing their removal. All four are corrected here.
- **That last one is the failure the association's `going-public.md` already records twice**, namely
  that a note documenting a redaction tends to quote the thing it redacted. The check that catches it
  is to re-run the original search over the whole tree after editing, rather than to re-read the files
  that were edited. Editing is not the end of a redaction, and the diff is not the evidence.

## [0.117.0] - 2026-09-14

### Security

- **`.gitleaks.toml` was exempting tracked files, and its own comment said the opposite.** The
  allowlist matched `^\.ddev/` while four files under `.ddev/` are committed, namely `config.yaml`,
  `php/opcache-jit.ini`, `web-build/Dockerfile` and `commands/host/mesh-convert`. Every scan since the
  file was written, in the working tree and over the history alike, skipped them, and the comment above
  the list claimed that none of these paths is committed. The pattern is now `^\.ddev/traefik/`, which
  is the one path that actually needs it: the TLS key ddev generates per project, measured as
  `.ddev/traefik/certs/sdwa5-3d.key` under rule `private-key`, untracked and covered by ddev's own
  `.ddev/.gitignore`. The four files were read by hand and are clean, and they are in scope from now
  on. Re-measured after the change: no findings in the tree, no findings over 148 commits.
- **The workflow declares `permissions: contents: read` instead of inheriting a default.** No job
  writes to the repository, none calls the API and no step reads `secrets`, so read access is the whole
  requirement. A default is not a statement, and this repository is meant to become public.

### Fixed

- **Three links pointed at a personal GitHub account that was deleted on 2026-09-12.** `README.md` and
  `docs/inventory.md` twice referenced `github.com/bestcodename/…`, so all three answered 404, and they
  republished the account name that both history rewrites were run to decouple from these repositories.
  They now name the `SdWa5` organization and say that the target repository is still private, which is
  why the link does not open for everyone. Nothing in this repository checks links in CI, which is how
  three dead ones survived.

### Changed

- **What PSL own is no longer inferred from what they bring.** `rosters/psl-next-event.yaml` and
  `docs/requests.md` turned a statement about one event into a floor on a rental company's stock. The
  counts stay, because they are what the roster is for, and the inference is gone. GMSS and Innschleife cleared their figures for publication and PSL
  were never asked, so their inventory is theirs to state. `docs/requests.md` keeps the open question
  of asking them outright.
- **A note about a third party's published data reads as a sourcing decision rather than a verdict.**
  The `hk-linear5-112x` coverage row characterised PSL's published page rather than citing it. It now
  says the listing carries two figures, which they are, and that the data table is the one taken
  because it is a measurement under an EN 60268-5 note. Every technical fact in the row is unchanged,
  in `docs/sources.md` as well as in `docs/requests.md`.

## [0.116.1] - 2026-09-14

### Changed

- **`TOOL-20` records the first push that ever executed on GitHub, which is now its newest data
  point.** Run `34759228276`, 2026-09-13: `phpunit` was cancelled at its own
  `timeout-minutes: 90` after 90 m 16 s, while `static` finished in 44 s and `secrets` in 14 s. The
  commit already carried 0.114.0's parallel `ShippedScenesTest` and the JIT, so neither is the
  missing lever, and the runner was a private one at two cores where a public repository gets four.
  The row had stood on the three six-hour cancellations of 5 to 7 September and on local timings
  alone. No code changed.

## [0.116.0] - 2026-09-13

### Security

- **0.115.0 claimed this repository needed no history rewrite, and an audit on 2026-09-13 measured the
  opposite.** Two things were here the whole time. `tests/Spec/SpecValidatorTest.php` used a board
  member's full name as the fixture for "an owner key may not contain spaces", in **153 of this
  repository's 154 commits**. And the 0.115.0 changelog entry asserting the three names appear nowhere
  named all three of them while doing so, in the tree and in that release's own commit message. The
  fixture is now `Wall Bass`, which is a cabinet in this library and exercises the same rule.
- **The history was rewritten over file contents and commit messages together.** The earlier passes in
  the sibling repositories used `git-filter-repo --replace-text`, which reaches blobs only; messages
  need `--replace-message`, and that is why every redaction had survived in the commit that performed
  it.
- **`sepp` is deliberately untouched**, decided 2026-09-13. It is a nickname rather than a name, it is
  an owner key throughout `specs/`, `rosters/`, `scenes/generated/` and `src/Scene/SweepAxes.php`, and
  what actually created an exposure was the root repository's mailbox table pairing it with a board
  role. That pairing is what came out instead, in `sdwa5` 0.9.0.

## [0.115.0] - 2026-09-12

### Added

- **A licence.** MIT in `LICENSE` for `src/`, `tests/`, `bin/`, `blender/`, `.github/`, `.ddev/` and
  the build configuration. CC BY-SA 4.0 in `LICENSE-docs` for `docs/`, `README.md`, `CHANGELOG.md`,
  `TODO.md`, `specs/`, `rosters/` and `scenes/`. Until now the root repository's `README.md` said "No
  license specified — all rights reserved", which published unchanged would have meant a reader may
  read the specs and reuse none of them.
- **The `mesh_override` meshes are carved out and stated as such.** They are third-party CAD, they are
  deliberately not committed, and `docs/sources.md` records where each came from and under what terms.
  That carve-out matters more here than the licence does: the dimensional figures in `specs/` are
  facts and carry no copyright, while somebody else's CAD does.

### Measured, and worth stating plainly

- **The manufacturer specifications are not a publishing risk, checked rather than assumed.**
  Dimensions, weights and performance figures are facts. What a datasheet protects is its prose, its
  drawings and its photographs, and this repository tracks **zero PDFs, zero images, zero CAD and zero
  meshes**. The longest quoted string anywhere in `specs/` is one line of figures. The real licence
  questions attach to the *open* designs rather than to the commercial ones, because those are the
  ones whose CAD was written by somebody else.
- This repository needs no history rewrite, because none of the three board members named in the root
  repository's redaction appears in any of its 152 commits. **That claim was wrong and 0.116.0
  corrects it.**

## [0.114.0] - 2026-09-12

`composer test` is 9 min 04 s, down from about 18 minutes, because the one test that holds every shipped scene to
standing up now runs across cores instead of down one. And the JIT that 0.113.0 measured at exactly nothing on this
suite is worth 1.53x on it. That measurement was an artefact of how the JIT was switched off, and the four files
that repeated it are corrected.

### Changed

- **`ShippedScenesTest` solves its library across cores, and a case is now an inventory rather than a scene.**
  The class was **600.3 s** of a suite that was about 18 minutes, and every one of its 2489 scenes was compiled on
  one core while twenty-seven sat idle. Nothing in the class writes, so no scene can see what another is doing, and
  the repository already forks two pipeline stages through `Parallel`. It is now **74.8 s**, the coverage guard below
  included. Measured over the whole 2489 on the bare work alone: **422.57 s in one process, 219.42 s on two cores,
  109.69 s on four, 67.04 s on eight and 40.71 s on twenty-eight**, the four-core figure repeated at 110.02 s. Two and
  four are the numbers that matter, because that is what a private and a public GitHub runner have.
- **Chunked by inventory directory rather than collapsed into one case.** One case for the whole library would hand
  PHPUnit a single name and a single dot of progress for ten minutes. The directory is the chunk because it already
  means something and because it stays stable as the sweep grows: a new inventory adds a case instead of renumbering
  every existing one. Thirteen chunks hold the same 2489 scenes the old provider yielded, from 7 scenes to 435, and
  work is stolen inside a chunk so the unevenness costs nothing.
- **The case carries the directory name and nothing else.** Handing PHPUnit the scene list works and reads terribly,
  because a failed case prints its own arguments — one broken scene in `next-event` would head its report with 434
  truncated paths before saying what went wrong.
- **Faults are collected and asserted once per inventory instead of asserted per cabinet per axis**, which took the
  class from **137 879 assertions to 62** and is a real part of the win rather than only bookkeeping: PHPUnit charges
  for every assertion it counts. A failure is no longer the first bad scene but all of them, with each message
  carrying the scene's own path and the cabinet's placement id, which is what separates "I broke a rig" from "I broke
  the solver". Verified by copying an impossible scene into an inventory under a `-possible` name: the chunk fails and
  names the file and the cabinet.
- **`testEveryImpossibleSceneReallyFailsACheck` goes the same way**, which paratest could not have done — it was one
  test over 237 scenes, 180.9 s of a junit-logged run, and no test-level splitter can divide a single test. Its own
  check set is unchanged and deliberately different from the one above: it asks the two questions `scene:stack`
  refuses a candidate on, because that is the promise being held to.
- Nothing is asserted inside a forked child anywhere in the class. An assertion that fails in a child dies with it and
  surfaces as "a worker produced nothing" rather than as the message it was written to give, so every answer is
  carried home as data and judged in the parent.
- `compile()` resolves a bare id and only a bare id now. The path branch it also carried was dead the moment the
  library sweep stopped going through it.

### Added

- **A test that the chunks hold every scene on disk.** This repository has already shipped exactly the failure it
  guards against: before 0.99.0 the provider was keyed on the scene id, ten inventories held the same id, and the
  class checked **nine scenes while reporting that it checked every one**. A case was one scene then, so a dropped
  scene was at least a missing name in the report. A case is an inventory now, so a scene dropped by the chunking
  would shorten one array nobody prints. The count is asserted against the files on disk rather than against a
  literal, because a literal would have to be edited by the same person who would have to notice the problem.

### Fixed

- **0.113.0's "the JIT is worth 1.57x on the commands and exactly nothing on this suite" was an artefact of how the
  JIT was turned off.** That release recorded 22:06.248 against 22:06.927 and read it as the same number twice.
  `phpunit.xml` sets `opcache.jit=tracing` in its `<php>` block, which is an `ini_set` that runs before any test does,
  so a run started with `php -d opcache.jit=off` has the JIT switched **back on** and both halves of that comparison
  were traced. Measured three ways on the same 107 cases: **4.93 s as configured, 5.36 s with `-d opcache.jit=off`,
  and 6.92 s with `-d opcache.jit_buffer_size=0`** — the one form `ini_set` cannot undo, because the buffer is
  `PHP_INI_SYSTEM`. Only the third is a genuinely interpreted run. On that figure the suite gains what the commands
  gain: the 434 cases of `generated/next-event` are **0.2154 s each traced against 0.3304 s interpreted, which is
  1.53x**, and the bare equivalent of the same work is 0.2000 s against 0.3308 s.
- The claim is corrected in all four places that carried it: `phpunit.xml`, `.ddev/php/opcache-jit.ini`,
  `.github/workflows/tests.yml` and `README.md`. Each of them now also names `-d opcache.jit_buffer_size=0` as the
  only way to time an interpreted run, which is the part that was missing rather than wrong.
- **The `phpunit` job's `timeout-minutes: 90` depends on the JIT, and now says so.** `composer test` was 108.98 min
  on run 33846381809 with the JIT off. At the measured ratio that is roughly 71 min, so the guard step that fails the
  build when the JIT is off is what stands between the budget and an overrun.

### Measured, and worth stating plainly

- **`--log-junit` costs about 0.05 s per test and far more on an assertion-heavy one, so a logged run is not a
  timing.** The same 434 cases are 91.82 s and 91.95 s without it against 113.44 s and 113.98 s with it. A junit run
  of the old suite came to 26:45 where the plain one was about 18 minutes, and it put `ShippedScenesTest` at 870.0 s
  where the plain class measures 600.3 s.
- **PHPUnit itself is not where the time went.** Its overhead is 0.0154 s per case, measured as the difference
  between a bare loop and the same scenes run through the suite, and the compile is 92 % to 96 % of every case.
- **The suite total before this change is composed rather than measured.** The suite after is 544.6 s; taking the
  class out at 74.8 s and putting the old one back at 600.3 s gives about **1070 s**, which is 17 min 50 s. The
  composition is sound because nothing else in the suite changed, but no single run of the old suite was timed plain
  on this machine tonight.
- **What this does to CI is an extrapolation and cannot currently be checked.** No run has started in any of the three
  repositories since the account's Actions minutes ran out; every one fails in 3 to 5 seconds. A runner has two cores
  where this machine has twenty-eight, so the class should fall to roughly half rather than to an eighth there, and
  four cores after the repositories go public.

## [0.113.0] - 2026-09-11

Every push cost 135 minutes of runner time and 26 of them were spent compiling scenes the suite had already
compiled. That step is gone. The JIT two config files have asked for since this repository had a solver turned
out not to be on at all, which is now fixed and guarded — worth 1.57x on the solver commands and, measured
back to back, not one second on the test suite.

### Changed

- **The `bin/console scene:build --dry-run` step is gone from CI, which is 26.12 minutes of every push.**
  Measured on run 33846381809, where `composer test` was 108.98 min and that one step was 26.12 min out of a
  135-minute job. It compiled all 2706 scenes, which is work `ShippedScenesTest` does on every run to a stricter
  standard — it checks that the scenes *stand up*, where the command only checks that they *compile*. The
  reasoning was already written down in `SceneBuildCommandTest`, which skips the identical whole-tree compile
  for exactly that reason and has done for releases. It had simply never been applied to the workflow.
  What goes with it is the command's own no-argument path, now covered by the `full` job and by
  `SDWA5_FULL_REPLAY=1` rather than on every push.
- **Every job in the workflow states a `timeout-minutes`, and the workflow states a `concurrency` group.**
  GitHub's default timeout is 360 minutes, and that default is what let `full` burn roughly 1644 minutes across
  three nightly runs in September before anybody saw a log, because a job cancelled at the ceiling reports only
  that it was cancelled. The concurrency group cancels a superseded push, which on 8 September would have saved
  four of five overlapping 135-minute jobs. It is deliberately **not** applied on `main`, because a merge
  commit's green run is what a release is judged by.
- `bin/console specs:validate` stays in CI despite duplicating `SpecsValidateCommandTest`. It is inside the
  "under 0.2 min combined" bucket and it is the only thing that runs the command's real exit-code path through
  the console application.

### Fixed

- **`ShippedScenesTest` rebuilt both of its loaders on every one of 2485 data-provider cases.** `compile()`
  opened a fresh `SpecLoader` and re-parsed all 38 spec YAMLs per case, then opened a fresh `SceneLoader` and
  called `find()` — which walks and sorts all 2727 scene files — to locate a path the provider had just handed
  it. Measured per call: `loadAll()` 6.3 ms, `find()` 3.7 ms, `load()` on a path you already have 0.5 ms. The
  spec library and the file list are now built once per class, and the path form is loaded rather than searched
  for. Measured across the suite: **1347.6 s to 1322.9 s, so 24.6 s**, against 25.0 s predicted from the
  per-call numbers. The bare-id form still goes through `find()`, because that is the case that needs its
  ambiguity check.
- **The opcache JIT was not on, in CI or locally.** `phpunit.xml` and `bin/console` both set
  `opcache.jit=tracing` at runtime on the stated premise that the image ships a 64 MB JIT buffer. Measured in
  the project container: `opcache.jit_buffer_size => 0` and `opcache_get_status()['jit']['enabled'] === false`.
  `opcache.jit_buffer_size` is `PHP_INI_SYSTEM`, so it can only be set at startup and `ini_set()` cannot raise
  it — which means both of those calls were selecting a mode for a compiler with nowhere to emit code, and both
  kept succeeding while doing nothing. It is now set at startup in `.ddev/php/opcache-jit.ini` and in
  `ini-values` on setup-php, and **those two have to stay in step**.
- The comments in `bin/console` and `phpunit.xml` that asserted the buffer was already there are corrected.
  Whether it was ever there is not knowable now: the image may have shipped one when 0.85.0 measured a fifth
  off the sweep and lost it in a later bump, which is a silent regression nothing would report.

### Added

- **A CI step that fails the build when the JIT is off**, before `composer test` in both `phpunit` and `full`.
  This is the guard the finding above argues for: every runtime way of asking for the JIT succeeds whether or
  not there is a buffer, so the failure is silent by construction and cost this repository an unknown number of
  releases. Verified three ways — JIT on exits 0 and prints the mode and buffer, a zero buffer exits 1 with
  `opcache JIT is OFF (mode=(none), buffer=0)`, and opcache missing entirely exits 1 with its own message.
- `.ddev/php/opcache-jit.ini`, so a local timing measures what CI measures.

### Measured, and worth stating plainly

- **The JIT is worth 1.57x on the solver commands and nothing at all on the test suite. Both are measured.**
  A bare `scene:stack` sweep in one process is 18.1 s with it and 28.5 s without, three repeats each with a
  spread under 0.2 s. An evenly spread 40-scene compile loop is 0.1449 s/scene against 0.2327 s/scene. The full
  suite is **22:06.248 with the JIT against 22:06.927 without it**, same code, same machine, back to back — the
  same number twice, and identical at 3266 tests and 163592 assertions either way.
- **Why the suite does not benefit is not established**, and two plausible explanations were tested and ruled
  out. It is not buffer exhaustion: `opcache_get_status()` at the end of a PHPUnit run reports 1.52% of the
  64 MB used, with `enabled=true on=true` and 677 cached scripts. It is not `phpunit.xml` setting the mode a
  second time at runtime: on a quiet machine that costs 0.1450 s/scene against 0.1451 s/scene without it. An
  earlier measurement that appeared to show the opposite was taken while a second suite run was loading the
  machine to a load average of 26, and is withdrawn. Filed as TOOL-22.
- The JIT is kept on all three jobs regardless, because the commands are where the minutes are, because
  `SDWA5_FULL_REPLAY=1` turns the suite into something command shaped, and because it costs nothing.
- The suite is green throughout: 3266 tests, 163592 assertions, 2 skipped.

## [0.112.0] - 2026-09-11

A tops row that lands in more than one run had its outer run dealt out to both ends of the rig instead of
moved to one side. One psl top ended a whole row width out of place and inside the stack standing next to it.

### Fixed

- **A stated `align.side` now moves the whole run instead of splitting it down the middle.** A clearance
  solve with a side is a group sitting entirely on one side of its reference, so it translates. Reading each
  copy's own offset as its column, which is right for a pair straddling its reference, sent the inner half of
  the run straight through the run it had been told to clear, and the further the solve pushed the further
  through it went.
- **This was not a corner case, because `Stack::throwFirst` states a side for every chained run of a tops
  row.** A tops row wider than what carries it lands in several runs, so any run of two or more cabinets met
  it. Measured on `stacked-1-systems-apart-free----mixed---centred---center-low-----impossible`, whose psl
  tops row is five EF6s landing as three and two: the pair was dealt ±2.7735 m about its own centre rather
  than translated 55 mm, so one of them stood at +3.6435 m instead of +0.6192 m and 0.2020 m inside a cabinet
  of the innschleife stack. With the run moved rather than split, that row comes out at a regular 0.6346,
  0.6346, 0.6267 and 0.6250 m pitch, every top is carried, and the scene has no faults at all.
- The four hand-written scenes that write `outside: tops` state no side, their two fills genuinely straddle
  the tops, and they are unchanged. A lone cabinet still needs a side and still gets one.

### Changed

- **121 generated rigs stop being impossible**, from 358 down to 237 across the 2706 committed scenes. The
  solver asks `SceneCompiler::stackSurvives` whether a candidate arrangement stands up, and that check
  compiles the candidate, so a fill run flung apart made good arrangements look like interpenetrating ones
  and the search fell back on worse rigs. The psl stack in
  `stacked-1-tops-shared---free----mixed---alternate-center-low-----possible` is the shape of it: six tiers of
  two ESXs reaching 3.540 m against the 3.000 m ceiling asked for, where the arrangement that now wins is
  four tiers of three reaching 2.360 m and inside the band.
- 137 generated scene files are renamed, 129 from `-impossible` to `-possible` and 8 the other way, and 87
  more change content under the same name.

### Added

- `AlignmentTest` covers both readings of `side`, the translation with one stated and the column split
  without. `StackTest` pins the psl tops row as its four pitches, because the pitches are what "one row"
  means.

## [0.111.0] - 2026-09-11

`SCN-10`. A cabinet with no interior can wear a photograph of its own front, which is the difference between a
render that is correct and one that is recognisable.

### Added

- **`front_image` in a spec puts a photograph on the generated block's front face.** Five Innschleife cabinets
  currently differ only in their bounding box and every borrowed cabinet is one nobody here has seen, so the
  front is where their identity lives. Accepts a bare path or the spelled-out form with `rotate_deg`,
  `px_per_cm` and `tolerance`, exactly as `mesh_override` does.
- **Off by default and switched on per run**, which the owner asked for. `models:build --front-images` applies
  them and without it every cabinet is built plain. The switch is also a `ModelBuilder` constructor argument
  defaulting to `false`, so the plain library is what a caller gets without asking, and `scene:build`,
  `scene:render` and `library:build` all keep it off.
- Flipping the switch rebuilds, because nothing on disk moves when a setting does. That reuses
  `Staleness::settingsChanged` and a `built-with.json` per output tree, the same mechanism `scene:render`
  already uses for its lighting and resolution. Verified both ways: with the switch the `.glb` carries one PNG,
  one texture and a second material, and without it zero of each, and the second build reported "1 model built"
  rather than "up to date".
- **Only a cabinet with no interior may have one**, which the owner asked for and the geometry agrees with. A
  spec with an `audio.layout` has real openings cut into its baffle, and one with a `mesh_override` has no
  front plane this builder knows. Both are **refused by the validator** rather than skipped, because a spec
  that asks for an image and silently does not get one is the worse failure.
- **The image's size is checked against the cabinet.** PSL's front drawings are 1 px = 1 cm, so a 50 × 114 px
  image implies a 0.50 × 1.14 m front, and both axes are compared against `geometry.dimensions_m`. Both axes
  rather than the aspect ratio, because an aspect check passes a photograph of a cabinet twice the size, which
  is the mix-up worth catching in a fleet where borrowed subs share a shape and differ in how big they are.
- `rotate_deg` is limited to the four right angles and describes **the file, not the cabinet**. A rolled
  cabinet's front is still its front and the texture turns with the mesh; what needs saying is which way up the
  photograph was taken. A quarter turn swaps the axes before the size check, so the rotation and the check
  agree, and that is pinned by a test rather than by an eye.
- 11 tests. Five rejection cases, a missing-file warning, an accepted plain cabinet, the size check both ways,
  the quarter-turn case, and three on the build plan.

### Changed

- `plan_version` is 5. The bpy side branches on `front_image` to load and pack an image and map it onto the
  front.
- **The polygons are picked by facing rather than by index.** The front is `faces[2]` when the block is built,
  but `cut_handles` runs a boolean and `add_chamfer` runs a bevel, and both reindex the mesh and split the
  front. Measured on `tms4`: 162 polygons afterwards, of which 3 are flat front, and the chamfer's own faces
  correctly keep the cabinet material. The UV range came out 0.009 to 0.991 against a z range of 0.010 to 1.130
  on a 1.140 m cabinet, so the 12 mm chamfer is the whole difference.
- The image is **packed into the `.blend`**. Without that the file references a path outside the repository and
  opening it elsewhere shows a pink cabinet, which is breakage that only appears on somebody else's machine.

### Removed

- `TODO.md` `SCN-10` and its prose block. **The schema landed in a different place than that block proposed**,
  and deliberately: it suggested `appearance.front_image` beside `appearance.color`, and it is a top-level
  `front_image` beside `mesh_override` instead, because it behaves like an external file with a fallback rather
  than like a colour. The scale field is `px_per_cm` rather than `cm_per_px`, which is the direction the source
  drawings are actually described in.

## [0.110.1] - 2026-09-09

A resolved row was still sitting in the list, and deleting it would have lost the one decision it carried.

### Removed

- **The `SCN-11` row, which had been marked "Done in 0.104.2" rather than deleted.** This file's own
  convention is that resolved rows are deleted and never ticked, so it was a leftover. Its two bug
  descriptions already live in the 0.104.2 entry below.

### Added

- **`INFO-2` records the one thing that row held and nothing else did.** The gmss inventories are
  deliberately left on the height rule while `ours`, `psl` and `innschleife` are ordered by `--order`,
  because no order was ever stated for gmss and inventing one would put cabinets somewhere nobody
  asked for. That is a standing decision rather than a finished task, so it belongs in the INFO group,
  and deleting the row without moving it first would have lost it.

## [0.110.0] - 2026-09-08

A measurement in 0.108.0 was overstated, and correcting it makes the same argument stronger.

### Fixed

- **"Four separate copies of `AmpLimiterCalc.csv`" was wrong**, in the `SIG-1` block of `TODO.md` and in
  `docs/sources.md`. Re-measured 2026-09-08: the four files share a name but carry **three distinct sizes**, 7700,
  7718 and 7432 bytes with the last appearing twice at the same timestamp. So three are hand-kept versions and only
  one pair is a true duplicate.
- That strengthens rather than weakens the case for the repository being master, because versioning by duplicating a
  filename is a worse kind of drift than redundant copies. It also changes what an import has to do: **which of the
  three versions the figures in `docs/sources.md` came from is not determined**, so an import picks a version
  deliberately rather than taking whatever the name resolves to.
- Dropped the claim that both `Drivers.csv` and `drivers.csv` sit in that folder. Both were listed earlier in the day
  and only `drivers.csv` is present now, at 554 bytes, so the pair is not something to assert.

## [0.109.0] - 2026-09-08

`StackSolver` split into five layered classes, the command's test suite split by axis, and a nightly job that
could never finish taken off its schedule.

### Changed

- **`StackSolver` went from 1995 lines to 979**, and the four classes beside it are a strict layering rather
  than four buckets of related names. `StackMetrics` calls nothing; `StackMix` and `StackLifts` read only it;
  `StackTops` reads those three; `StackSolver` reads all four and nothing reads it. **The alternative was
  measured before it was rejected**: grouping by subject alone put `widthAbove` and `liftAbove` in classes that
  call each other, and `lastRowWidth` and `liftPairs` likewise, so `lastRowWidth` went into `StackLifts` and
  `widthAbove` into `StackTops` and both cycles are gone
- **`StackMetrics` is 12 of the 38 methods, chosen for calling nothing at all.** Three of them carry the
  solve: `rollFor` from sixteen call sites, `perTier` from eight, `share` from five. `EPSILON_M` and
  `OVERHANG_PER_SIDE` moved with them and are `public` now, since four classes read them
- **What stayed is the search, deliberately.** `fill`, `fillWith`, `packedRows` and `packTo` are 564 of the
  remaining lines and are exactly where GEO-11, GEO-13 and GEO-14 land, so this took the stable part out of the
  way of the volatile part rather than pretending to simplify the volatile part
- **`SceneStackCommandTest` went from 1956 lines to 663**, split by the axis each suite varies:
  `SceneStackMirrorTest` (226), `SceneStackSystemsTest` (478), `SceneStackFeasibilityTest` (423) and
  `SceneStackRosterTest` (151), with the fixture and the cleanup in `SceneStackTestCase` (134). **Low-end did
  not become a file**: it is two tests of about 60 lines, so it stayed with the sweep. The roster tests are the
  fourth suite instead, being nine tests with one subject
- `actions/checkout@v4` to `@v5` in all four jobs. GitHub forces v4 onto Node 24 and annotates every run

### Fixed

- **The nightly schedule is gone, because the job it existed for cannot succeed.** `full` ran on 5, 6 and 7
  September and was cancelled all three times with "the job has exceeded the maximum execution time of
  6h0m0s", a hard GitHub ceiling rather than a setting. Measured on run 34099956636: `phpunit` 3 h 07 m 52 s
  and `full` 6 h 01 m 17 s, so about 548 minutes a night that bought nothing and roughly 1644 across the three.
  The nightly `phpunit` was the same sampled run a push already does, so the trigger's removal loses only the
  unattended repetition. `full` is `workflow_dispatch` only until it is made to fit, which is TOOL-20
- A line reference in `TOOL-16` that the test split invalidated. It names the test rather than line 1613 now

### Added

- `docs/pipeline.md` has a **The solver's five classes** section with the layering, why the cycles were
  avoided, and the golden-master argument that makes a move refactor provable here
- `TODO.md` gains **TOOL-20**, the `full` job against the 6-hour ceiling, and **TOOL-21**, the 2 h 15 m every
  push costs on a runner and why `paths-ignore` is not free while the `secrets` job shares the trigger

## [0.108.0] - 2026-09-08

The Audio Routing sheet was read, which unblocked SIG-1, answered its schema question and turned up four
contradictions worth keeping rather than resolving.

### Added

- **The Audio Routing sheet has been read, so `SIG-1` is no longer blocked on reading it.** Its prose block used to
  open by telling the next person to go and look; it now records what the six sheets hold, in a table, with the
  hardware they describe: two DSPs, an 8×8 and a Behringer DCX2496, and four amplifiers, a Tulun/Play/Prokustk
  TIP10000q, a GISEN MM14K, a GISEN M60D and a Behringer EP4000.
- **The schema decision `SIG-1` waited on is answered: the repository becomes master and the workbook becomes
  generated output.** Stated by the owner. A two-way sync is the wrong tool anyway, and the reasons are recorded: a
  spreadsheet has no merge, so a conflict is lost rather than resolved; the `SdWa5:` remote is
  `scope = drive.readonly`; `Vspk_RMS_V` is a formula that a round trip would drop; and the Drive already shows the
  drift, with four copies of `AmpLimiterCalc.csv` in one folder beside both `Drivers.csv` and `drivers.csv`.
- Three new rows in the SIG group, for the parts the reading separated out. `SIG-2` reads the Drive from the repository
  instead of transcribing it, and records both measured gotchas: the `SdWa5:` remote is scoped to
  `team_drive 0AFDifygC0zQZUk9PVA`, and the workbook is not in that shared drive but in the account's My Drive, so it
  needs `--drive-team-drive ""`. `SIG-3` is the signal-chain schema block, which has no home today. `SIG-4` is the
  export back to Drive, deferred because it needs a new OAuth scope.
- `docs/sources.md` gained a section on the workbook and **the four places it contradicts what the repository already
  believes**, each with both claims and both sources. The Achenbach 18's driver is B&C 18TBW100 here and RCF L18P300
  there; its passband is 35–1000 Hz here and 35–1500 Hz there; the Tecnare's drivers differ entirely and the sheet
  splits the cabinet into LF and HF channels at ~6.5 kHz, which is not recorded here at all; and the sheet's live gain
  figures disagree with `Amp_GainSelector.csv` sitting in the same Drive folder, which recommends 41 dB and 44 dB and
  says a lower setting cannot reach the RMS limit. **Neither side is promoted.** It is a working sheet rather than a
  datasheet, so settling these is a front-panel or tape-measure job.
- A `secrets` job in `.github/workflows/tests.yml`, plus `.gitleaks.toml`. The same job runs in all three SdWa5
  repositories, because they are going public and a public repository publishes every past commit at once. This one is
  clean in tree and history, measured 2026-09-08, so the gate exists to stop the next secret rather than to find a
  current one. Runtime is not a concern despite the 2707 committed scene files: 1.5 s for 137 commits and 38 MB.

### Changed

- `SPEC-8` is **partial** rather than open. The sheet answers what "gisen md60" is, namely **GISEN M60D**, and names
  the amplifier complement. What is left is which rack holds the fourth amp, since the sheet names an EP4000 without
  saying where, and EP4000 2U/16.6 kg against Proline 3000 3U/37 kg is 69 kg versus 79 per rack.
- `SPEC-13`'s wattages are no longer merely missing but **sourced**. The sheet carries an RMS wattage, a nominal
  impedance and a passband for all six speaker groups. Two figures match `Hardware/Hardware Overview.xlsx` exactly,
  `Top 15 2-way` at 550 W and `Sub FH` at 1800 W, which is worth stating because the disagreements elsewhere are
  specific rather than wholesale.

## [0.107.0] - 2026-09-08

The static checks turned into a gate, and the 55 things they found on the way there.

### Added

- **A `static` job in `.github/workflows/tests.yml`**, running PHPStan, the Symfony coding standards and Ruff. It
  sits beside `phpunit` rather than ahead of it, because a style violation and a failing solve are both worth
  knowing about on the same push. Ruff is pinned to `0.16.6`, since `pipx run ruff` takes whatever is newest and a
  release that adds a rule would fail a push that changed no Python
- `docs/pipeline.md` has a **Static checks** section with the three commands, the measured level counts and what
  the first pass found

### Fixed

- **`Stack.php` documented twelve array shapes against a class that does not exist.** `DeviceSpec` was never
  imported, so in namespace `App\Scene` it resolved to `App\Scene\DeviceSpec` and every `@param` and `@return`
  built on it described nothing. `SceneStackCommand` did the same with `Fault`, which lives in `App\Scene`
- **Three array shapes had gone stale against their own data**, each one a key the code was already reading.
  `SweepAxes::rigsToTry()` lost `split` when SWP-2 added it, the sweep's task tuple never gained the `LowEndBias`
  that `sweep()` reads as `$task[5]`, and the built-scene shape never gained the `faults` that `Feasibility::of()`
  reads. `Stack.php`'s run shape was missing `settle`, which `Gravity::reseat()` requires
- **`Stack::nearest()` ended in a fallback that could never run and would have thrown if it had.** `$references`
  is a `non-empty-list`, so the first iteration always sets `$best` — and the fallback indexed `$references[0]`
  on the array it was guarding against being empty
- **`assertLessThan()` was called with four arguments** in `StackSolverTest`. PHP drops a surplus argument to a
  userland function silently, so the warnings the author passed for a failing run were never in the message. They
  are now part of it
- **`ShapeTest` called `isCabinet()` and dropped the result**, which is the one thing that test exists to check,
  and one array literal in `SceneStackCommandTest` named `--low-end` twice with the same value
- **A dead guard in `BuildAllCommand`**: `$aimModes` cannot hold null, since the stated branch is guarded and the
  other two are constants, so `--aim-lines` was always passed and the `if` around it was decoration
- **A dead `along = 1 - axis` in the Blender flare solver**, an unused `import bpy` in `bay.py`, and a redundant
  `"r"` open mode
- `Arc::problems()` defaulted `$cellBox` to `[]` against an interface shape that has no empty case. An arc ignores
  the box — its spacing comes from the cabinet's own plan outline — and the annotation now says so
- **An unused `CONTACT_TOLERANCE_M` alias** in `SceneStackCommand`, whose own docblock argued against having two
  copies of a tolerance. `CandidateCheck` reads `PlacementChecks::CONTACT_TOLERANCE_M` directly

### Changed

- `FillOrder` no longer coalesces `weightKg`, which is a non-nullable float, and four `array_values()` calls on
  values that were already lists are gone. Twenty `?->` accesses on receivers that cannot be null are plain `->`
- **`README.md` claimed nothing generated is committed, which was true of `build/` and false of 2707 scenes.** It
  now says what is actually true: no binary artefact is committed, and `scenes/generated/` is committed on purpose
  so any scene can be read on the web without a checkout and a solve
- `TODO.md` gains **TOOL-19**, the regeneration diff — the last three commits touched 1208, 937 and 4167 files, so
  a code change is unreviewable inside one — and **TOOL-18**, the 167 further errors between level 5 and level 8

## [0.106.0] - 2026-09-08

Static analysis and a style gate, and the formatting pass they needed first.

### Added

- **PHP-CS-Fixer with the Symfony ruleset**, `@Symfony` plus `@PHP83Migration` plus `declare_strict_types`, in
  `.php-cs-fixer.dist.php`. `composer cs` checks and `composer cs-fix` applies. `@Symfony:risky` is **off** on
  purpose: its two loudest rules wanted 132 changes across the tree to write `\count()` and `\PHP_INT_MAX`, which
  buys an opcode-level lookup in a project whose TODO states runtime is not a constraint. `phpdoc_to_comment` is
  off because it turns an inline `@var` hint into a plain comment and PHPStan then stops reading it
- **PHPStan in `phpstan.neon`**, at **level 5** over `src` and `tests`, reachable as `composer stan`. The level is
  a measured choice rather than a default: 1 error at level 0, 18 at 2, 51 at 4, **64 at 5**, 231 at 8 and 522 at
  max. Everything at level 5 is fixed rather than baselined, so the file carries no ignores and a new error fails
- **Ruff for the Python side** in `pyproject.toml`, covering the 2703 lines under `blender/` and `tools/` that no
  tool has ever read. `bpy` is only importable inside Blender, so lint rules are what is checkable from outside
- `composer static` runs PHPStan and the style check together

### Changed

- **127 of 171 PHP files reformatted to the Symfony standard**, in this commit alone so that the fixes that
  follow stay readable. The pass is mechanical and changes no behaviour. What it touched, by rule: 105 files
  `yoda_style`, 57 `phpdoc_separation`, 37 `cast_spaces`, 21 `fully_qualified_strict_types`, 20 `no_unused_imports`,
  19 `method_argument_space`, 17 `single_line_throw`, 16 `global_namespace_import`, 16 `phpdoc_summary`
- **The formatting pass alone removed 9 PHPStan errors**, 64 down to 55, mostly through `no_unused_imports` and
  `fully_qualified_strict_types` resolving names PHPStan could not
- `.php-cs-fixer.cache` is gitignored beside the PHPUnit one

## [0.105.0] - 2026-09-05

Every Innschleife cabinet identified. Four estimates became datasheet figures, and none of it came from asking.

### Changed

- **Both purple tops identified from published figures, and Innschleife had both names on the wrong box.** The
  big top's drawn 0.500 × 1.140 m front is the published **Turbosound TMS-4**'s 502 × 1143 mm; the small top's
  0.430 × 0.870 m is the **TMS-2**'s 432 × 865 mm. Both inside the drawing's own 1 px = 1 cm resolution. The
  THL-4 they named fits neither at 1007 × 574 × 718, and the TMS-3 misses too at 1019 × 844 × 578 — one model in
  the series matches and its neighbours miss by a lot, which is what separates an identification from a
  resemblance. `thl4` → `tms4`, and the old `tms4` → `tms2`
- **Four estimates became `provenance: datasheet`, every one of them optimistic.** `tms4` depth **0.520 → 0.730 m**
  and weight **59 → 74.8 kg**; `tms2` depth **0.430 → 0.578 m** and weight **32 → 48 kg**. That is **358 mm of
  depth and 31.8 kg** the rigs did not know about, on the axis this repository has already been badly wrong
  about — the GMSS reconstruction missed the IQ sub's depth by 205 mm having got its width and height inside
  35 mm. Width and height stay the **drawn** ones: where a measurement of this gear and a measurement of the
  model both exist, the first wins and 2 mm changes no rig
- **The sub mapping went round a full circle and came back.** Innschleife stated for the next event that they are
  **not** bringing the four black JBL 60 × 60 and are bringing the blue Electro-Voice 95 × 57 instead. That names
  the blue EV as the 0.950 × 0.570 cabinet and "die blauen Kicker 15" with it, which is where `kicker-15` sat on
  02.09 before a correction moved it and got it wrong. So `sub-95x57` → `kicker-15` (blue, Electro-Voice) and the
  old `kicker-15` → `sub-60x60` (black, JBL, model unknown). The 60 × 60's colour goes back to the photograph's
  near-black #1D1D1D, which had been right all along
- **The event rig changed shape, not just labels.** The EV is 0.950 m wide against the JBL's 0.600, so four of
  them are a 3.80 m row before any gap against 2.40 m, and every silhouette rule here is a width in metres.
  `next-event` came out at **480** scenes against 506, `innschleife-next-event-thl4` became
  `innschleife-next-event-tms4` at **146** against 101, and `innschleife-psl-sdwa5-sepp` **422** against 423.
  Each folder was deleted before regenerating, so no stale name survived. 2706 scenes in total
- The roster and its scene folder follow the cabinet: `rosters/innschleife-next-event-tms4.yaml`. Library totals
  **6804.0 kg** across 101 units, Innschleife **1569.6 kg** across 20

### Fixed

- **`docs/sources.md` now records that "read a name back before writing it down" is necessary and not
  sufficient.** Reading the mapping back on 03.09 is exactly what produced the wrong sub mapping, because the
  question and the answer both pointed with words and a confirmation about the wrong cabinet reads like a
  confirmation. Four corrections in four days are written out in sequence. What worked was numbers held against
  published figures, and one unprompted sentence naming a colour, a manufacturer and a size together
- **The render script never rebuilt models**, so the first re-render after the rename failed **197 of 197** with
  "these models are missing or out of date". `models:build` is its first line now
- **`docs/requests.md` now says when nothing is being asked.** Its six Innschleife rows read as a queue somebody
  is waiting on, and they are not — the owner's call is that Innschleife know their own tops and that the JBL
  model and the wbin's provenance can wait. The rows stay as a record of the gaps; the file says so at the top

## [0.104.2] - 2026-09-05

`--order` stopped moving the rigs it has nothing to say about.

### Fixed

- **A `--order` naming none of a rig's stacks skipped the height rule anyway.** `StackSceneWriter::byHeight()`
  returned on the stated path whenever the order was non-empty, without checking whether it named any of the
  blocks in front of it. `--order` states where *systems* go and a `pooled` rig has no systems — its stacks are
  labelled `1`, `2`, `3` — so a system order named nothing in one, every block fell through to `count($rank) +
  $index`, and the rig came out in **solve order** with tallest-in-the-middle never applied. That is a silent
  geometry change in the one mode the option cannot be about. **Measured on the `next-event` sweep: 8 `pooled`
  ids moved, five of them across the possible/impossible line.** The stated path is now taken only when the order
  names at least one block present, and a partial order still ranks what it names and leaves the rest at the end
- **A multi-stack rig ignored `--order` entirely, for the same reason one level down.** `--stacks=2` labels the
  blocks `ours-1`, `ours-2`, `psl-1` and so on, so an order naming *systems* matched no label whole and **533 of
  the 799 multi-system scenes kept the height rule** — which mirrors each system's pair about the centre line and
  puts `ours` in the *middle*, the opposite of a stated order that begins with it. The rank is taken on the system
  part of the label now, so a system's stacks stay adjacent under PHP's stable sort and the systems run left to
  right as stated. Stated by the owner: strictly left to right, grouped, rather than the mirrored stage the height
  rule happened to produce
- **Both affected inventories regenerated with the order recorded in every file.** `next-event` (506) and
  `innschleife-psl-sdwa5-sepp` (423) now carry `--order=ours,psl,innschleife` in their replay lines, so a rebuild
  is faithful. **799 of 799 multi-system scenes read `ours | psl | innschleife`**, against 34 of 266 before. The
  `gmss` inventories are left on the height rule on purpose, no order having been stated for gmss
- **One rig changed feasibility, and it is a real consequence rather than an artefact.**
  `stacked-2-tops-shared---v-------upright-alternate-center-low-----possible` is now `-impossible`: grouping a
  system's two stacks side by side instead of on opposite sides of the stage changed its geometry, and a 6×
  `flexy-folded-horn-hybrid` row of 3.646 m now sits on a 2.451 m support and overhangs 292 mm each side. Every
  other id in both inventories is unchanged. **The old name was left behind on disk as a stale file** — a
  `scene:stack --force` overwrites what it writes and never deletes what it stops writing, which is worth knowing
  before any future regeneration
- Three tests, none of which existed: `--order` puts the systems where it says whatever their heights, asserted in
  both directions so it is the order being tested rather than an arrangement the heights produce anyway; each
  system's stacks stay together in the stated order; and an order naming none of the stacks leaves the height rule
  in charge, which is the `pooled` regression above

## [0.104.1] - 2026-09-04

Documentation caught up with the tree. No behaviour changed.

### Fixed

- **Every scene count in the docs was from an older sweep, and the inventory table was stale in nine of its eleven
  rows.** The sweep has grown from 1374 to **2688** across three axes since those numbers were written, so
  `README.md`, `docs/pipeline.md` and `docs/scenes.md` carried figures that were each individually plausible and
  collectively wrong. Every one of them is re-measured: the default sweep is **146 scenes and no impossible ones**,
  the committed tree is **2327 possible and 361 impossible**, the separation axis splits **868 / 937 / 883** across
  `systems-apart` / `tops-shared` / `pooled`, and single-owner rigs are **301** rather than 153. The table now sums
  to 2688 exactly, which is the check that was missing
- **`--max-scenes` was compared against the wrong quantity.** `docs/scenes.md` read its 1500 default against the
  whole tree, which now exceeds it and made the fuse look broken. **It counts one invocation**, and the largest
  committed run is `next-event` at 506, so nothing is near it. The default is unchanged and the sentence now says
  what it counts
- **`--low-end` was missing from `README.md` entirely.** The option shipped in 0.103.0 and the command table never
  gained it, so the one axis somebody would go looking for after seeing a SKRAM in the middle of a render was the
  one the README did not mention
- **Six options had shipped without ever reaching the option table in `docs/scenes.md`**: `--roster`,
  `--quantity`, `--group`, `--folders`, `--order` and `--low-end`. `README.md` gained `--order` and `--roster`
  beside them. **`--order` turned out to be used by no committed scene at all** — 0 of 2688 record it, so every
  multi-system rig stands in height order instead. **Measured, that order is not merely wrong but unstable**: of
  the 266 named three-wall scenes in `next-event` and `innschleife-psl-sdwa5-sepp` all six permutations occur and
  only 34 are the one asked for, and a single rig's `--low-end=central` and `--low-end=low` variants come out in
  opposite orders because changing where the SKRAMs sit changed which wall is tallest. Filed as `SCN-11` rather
  than fixed here, because the fix is a regeneration of 929 scenes and a re-render
- **`TOOL-16` was in `TODO.md` twice.** The older row still described `SceneStackCommand` at 2098 lines and asked
  for the naming and dealing extractions, both of which shipped in 0.100.0 as `SceneLayout` and `StackDeal`. The
  surviving row carries today's figure, which is 1425 rather than 1374 because the low-end axis added 51 lines

## [0.104.0] - 2026-09-04

The test suite, from 1 h 17 min to about 25.

### Changed

- **`BuildAllCommandTest`'s replay: 26 minutes to 34 seconds.** Replaying every generated scene's own recorded
  command costs one solve per scene, and the set grew from 1374 to 2688 as the sweep gained three axes — a test
  whose cost scales with the output of the thing it tests always ends up here. It replays a **sample with the seed
  printed** now (`tests/Support/ReplaySample.php`): a fresh draw each run covers the set over a week, and
  `SDWA5_REPLAY_SEED=…` repeats a failure exactly. **What is sampled is what gets replayed** — the
  before-and-after comparison still walks the whole tree, because a stale check against a sample would call the
  rest of the repository stale
- **`ShippedScenesTest`: 25 minutes to 15, with the same 128 348 assertions.** It was two data-provider tests over
  the same set, so every scene was compiled **twice** for two questions that read the same geometry. One compile,
  both assertions, each keeping its own failure message
- **`SceneStackCommandTest`: 12 minutes to 7.** 61 invocations that are about an alignment or a shape now state
  `--low-end` instead of sweeping it, which is the same idiom those tests already use for the axes they are not
  testing
- **Two paths are behind `SDWA5_FULL_REPLAY=1`**, both costing a full pass over the tree and neither the only
  cover for what it checks: the regenerate **stage** end to end, and `scene:build` with no argument — the scenes
  it compiles are compiled every run by `ShippedScenesTest` to a stricter standard

## [0.103.0] - 2026-09-04

An eighth sweep axis: where the lowest-reaching cabinets belong.

### Added

- **`--low-end=central|low`**, and `low_end:` in a `stack:` block so a rebuild is faithful. `low` is what the
  solver has always done — both SKRAMs side by side on the floor, straddling the centre line. `central` puts one
  on the floor centre and the second **directly above it**, each flanked to the row's width
- **A candidate the search did not previously contain.** The dealer takes as many of a type as the row budget
  allows, so both SKRAMs went in one row and there was nothing to rank against it. Capping the type at one per row
  is not enough either: that gives the cabinet a 0.61 m row of its own under a 3.6 m row, which fails the support
  check and is never returned. `StackSolver::spreadRows()` builds the flanked version, obeying the same two rules
  `mixedBottomRow()` does — the centre may not be shorter than its flanks, and the flanks come in pairs
- Two measures weighted four to one, both `moment / mass` over the same cabinets — the shape `Stability::tips()`
  already used — with the weight changed from how heavy a cabinet is to **how low it reaches**: the passband where
  it states one, mass where it does not, under the guard `byFillOrder()` states in words

### Changed

- **`low` costs nothing, and that is the design rather than an omission.** The fill already deals the
  lowest-reaching type first and it already lands on the floor, so pricing that re-ranked every rig in the
  repository to express a preference they already satisfy — **eleven solver tests failed** when it did. It is also
  declared first, so where the two values agree the deduplication keeps the `low` name and an unchanged rig is not
  renamed to claim a preference it merely happens to satisfy
- **The axis is measured, not assumed**: `central` differs on **7 rigs of 104** on the GMSS inventory and on none
  of Sepp's eight cabinets. Where it changes nothing it writes nothing

### Fixed

- **Two things the first implementation got wrong, both found by measuring rather than by reading.** The centroid
  was taken over every sub, so twelve Flexys drowned out two SKRAMs and the measure could not see the arrangement
  it exists to choose — it is about the lowest-reaching type alone now. And it weighed a *run* at its own centre,
  so a pair read as one lump at their midpoint and an arrangement with the pair shoved up a row and off to one
  side scored as **more central** than the same pair straddling the middle on the floor. Each cabinet carries its
  own distance now

## [0.102.0] - 2026-09-03

Each sound system aims at its own focus.

### Added

- **`focus:` on a placement**, in either of the two forms the scene's own takes, measured from **that placement's**
  front face rather than the rig's. `scene:stack` writes one per wall for `systems-apart` and `tops-shared`, and
  none for `pooled` — a pooled rig split into two or three stacks is one system in several piles and is aimed as
  one cluster
- The point is resolved at expansion and written into each cabinet as an ordinary `aim_at`, so nothing downstream
  has to ask which focus a cabinet meant

### Fixed

- **Three systems standing side by side all aimed at one point in front of the middle one.** `Focus::point()`
  measures out from the *rig's* front centre, which is right for a cluster and the near-fills beside it and wrong
  for separated systems: measured on a two-wall rig, the outer wall's tops came out at **23° and 26° of yaw** when
  they should have been facing straight ahead, so the systems covered one patch of floor between them instead of
  each covering the room in front of it. Hand-written scenes are unaffected — a fill beside a main cluster belongs
  to that cluster and aims where it aims

## [0.101.0] - 2026-09-03

Innschleife read the name mapping back and corrected most of it. A cabinet nobody had drawn now has a spec, two
ids swapped, one lost its name again and one gained a manufacturer.

### Added

- **`sbh-18`, the one Innschleife cabinet no drawing shows and the only one whose dimensions its builders stated.**
  "120 lang, 55 breit, 80 tief" — three edges, in centimetres, from the people who built it, which is a better
  source than anything else in that folder. Upright it is 0.550 × 1.200 × 0.800; **they build it lying**, so four
  side by side are **4.80 m of horn mouth**, which is what "als großes Horn" meant. Weight 106 kg is the modelled
  volume at 200 kg/m³ and is the only number in the file nobody stated
- **The sweep already contains the rig they described**, which is worth knowing before building a mechanism for
  it: rolling the SBH makes it wider, so `--orientation=turned` and `--orientation=mixed` both lay it down. The
  `mixed` variant of an Innschleife rig is the closest thing in the sweep to how they actually stack

### Changed

- **Two ids swapped, and the mapping that produced them is a lesson rather than a bug.** On 2026-09-02 Innschleife
  listed four "SBH 18", four "WSX 18" and four "die blauen Kicker 15" — three sub types at four each — and the
  three specs drawn from their setup drawings were three sub types at four each, so the names were mapped one to
  one. **A mapping the numbers permit is not a mapping anybody confirmed.** The 0.600 × 0.600 box is a small
  Electro-Voice wbin, `sbh-18` → `kicker-15`; the old `kicker-15` is neither the SBH nor the blue one and goes
  back to a front-size id as `sub-95x57`
- **`kicker-15` has a manufacturer and a colour, both stated**: "Electrovoice wbins, sind die blauen". `build`
  becomes `original`, and the near-black #1D1D1D sampled off the drawing's photograph is replaced by a mid blue
  standing in for a shade nobody has measured — **the third time in that folder a photograph's average has turned
  out not to be a paint colour, and the first time one has been contradicted outright**
- **`top-43x87` is `tms4`**: "Die kleinen sind Turbosound Tms4." First Innschleife cabinet with a manufacturer and
  a model behind it rather than a livery somebody recognised
- **`sub-95x57` is the one left over.** Four sub types where the statement listed three, so the roster names it at
  **0** — a cabinet named at zero is one somebody decided to leave at home, where one left out of a roster is one
  nobody thought about
- **20 Innschleife cabinets, 1506 kg**, and 38 devices, 101 units and 6740.4 kg in the library

### Fixed

- **A published figure that contradicts a stated name is recorded as a contradiction rather than resolved.** The
  published Turbosound TMS-4 is 1143 × 502 × 730 mm, which is `thl4`'s front to the millimetre — the cabinet
  Innschleife call the THL-4 — while they call the 0.430 × 0.870 one the TMS-4. Either the names sit on the wrong
  boxes, a drawn front is wrong by 270 mm, or the published figures are another revision. **No published figure
  was copied into either spec**, because a Turbosound of a similar class was once let in as a size anchor for
  `turbo-top` and came out 61 % too tall against the builder's own numbers

## [0.100.0] - 2026-09-03

SWP-3, all three asks, and a defect found while designing it that had been silently skipping rebuilds since 0.98.0.

### Fixed

- **A scene's identity is its path now, and eleven rigs were sharing one `.blend` before it was.** The inventory
  became a directory in 0.98.0 and from that moment 2072 generated scenes shared **589 basenames — 421 of them
  belonging to two or more inventories**. Everything downstream keyed a scene by its `id`, which is its basename, so
  the first inventory in sort order wrote `build/scenes/generated/<id>.blend` and the other ten were then found to
  have an artifact newer than their own source and **skipped as up to date**. The failure mode of a wrongly-keyed
  artifact is a skipped rebuild, which looks exactly like a current one. Three places had already worked around it
  by keying on the path — `SceneLoader::find()`, `ShippedScenesTest` and `BuildAllCommandTest` — so the repository
  had decided; nothing had told `emit()`, `derivedDir()` or `prune()`
- **Every derived artifact mirrors its scene's directory.** `scenes/generated/gmss/x.yaml` builds to
  `build/scenes/generated/gmss/x.blend`, and the prune walks the derived tree recursively and matches by key. Its
  four `glob()` calls were one level deep, which reads to a prune as "nothing derived exists"

### Added

- **`--group=NAME:owner+owner`, and `sdwa5` and `sepp` are one system by default.** `owner` is what a spec records
  and is not quite the right discriminator: the two travel together and are what stands on a stage when this
  collective plays. `systems-apart` read the owner field, so the rig for the next event came out as **four** walls
  with Sepp's standing apart from ours. It is three now — `main-ours` at 25 cabinets, `main-psl` at 17,
  `main-innschleife` at 14. Stated at invocation time rather than as a `system:` field on a spec, which is the
  answer CVR-3 held out for: who owns a cabinet is a fact about the cabinet, who counts as one system is a fact
  about one gig
- **`--folders=<axis>[,<axis>…]`**, making any axis a directory level instead of a name field — `inventory`,
  `stacks`, `systems`, `shape`, `orientation`, `mirror-style`, `align`, `feasibility`. A value appears in the path
  or in the name and never in both, the nesting order is the order the name already reads in, and a directory
  carries the raw value while the padding stays in the name
- **The default layout is `inventory` alone, which is exactly the tree that already existed**, so the mechanism
  shipped without moving a file. A non-default layout is recorded as `--folders=` in each scene's own line, because
  what a replay is missing is not the values — every folder-able one is already in the line — but which axes are
  folders
- **The inventory axis is permanently a folder** and the ceiling is three levels. The inventory is the only axis
  whose value cannot be recovered from a recorded line, and the cardinalities are 11 × 3 × 3 × 3 × 4 × 3 × 3 × 2, so
  all eight would give more directories than files. A fourth is refused rather than silently produced

### Changed

- **Every option narrows one axis. None of them collapses the sweep.** `--from`, `--stacks` and `--per-owner` were
  read as "the caller has one specific rig in mind", so **"sweep everything, but only two stacks" could not be
  asked for**. It can: `--owner=gmss --stacks=2` writes 48 scenes with every other axis still walking
- **A scene id carries the stack count and the separation, and `--id` records the base alone.** Both are rebuilt
  from `--stacks` and `--systems`, and `--per-owner` is an alias rather than something a scene records — a flag can
  only name one of the three separations and a replay has to name the one it was
- **The default sweep writes 100 scenes rather than 271**, and that is the grouping doing its job: 171 of those
  files were the separation axis solving our own system standing apart from itself. **1899 generated scenes across
  eleven inventories**, down from 2072
- **`src/Command/SceneStackCommand.php` is 1321 lines, down from 2357.** Seven collaborators, each taking the shape
  the file's own docblock had already argued for — pure static, returns `array|string`, the command turns the string
  into an error. `StackChecks` gained the height band, `StackSceneWriter` the block ordering, `SweepAxes` the rig
  enumeration and the naming, and `CandidateCheck`, `FillOrder`, `StackDeal` and `RecordedCommand` are new
- **`TODO.md` compacted from 1122 lines to 1020.** SWP-3 and CVR-3 are closed and deleted rather than ticked, and
  GEO-5's and GEO-2's arguments are compacted to what a later measurement did not supersede

## [0.99.0] - 2026-09-02

Both borrowed systems said what they are bringing, which named four cabinets, proved a fifth exists and made the
difference between what a system owns and what turns up a thing the sweep can express.

### Added

- **Rosters.** A file in [`rosters/`](rosters) states what one system brings to one event, as counts that override
  the specs for one run: `bin/console scene:stack --owner=psl --roster=psl-next-event`. A spec's `quantity` is how
  many exist and it stays that, because the catalog, the load plan and every weight total want exactly that number
- **A roster overrides counts and nothing else**, so it composes: state Innschleife's counts and our own gear is
  untouched, all of it. Two rosters in one run compose the same way, and two that disagree about one device are
  refused rather than resolved — neither file is newer than the other, so there is nothing to prefer
- **Zero is how a cabinet stays at home**, and naming it at zero is not the same as leaving it out, which means
  "bring whatever the spec says". `psl-next-event` therefore carries eight zeros: "the following" means the
  following
- `--quantity DEVICE:COUNT` on `scene:stack`, the same statement typed at a shell for the question nobody will ask
  twice. It wins over a roster where both name a device, being the newer of the two by construction
- **A roster names the folder its scenes are written into**, and `--quantity` without `--into` is refused. Both
  variants of one event are `--owner=innschleife`, so without a name of their own they would overwrite each other
  and the plain Innschleife sweep as well. Every other axis is in the file name or the folder; a count is in
  neither, and that is the one collision this command must not have
- **The recorded regenerate line carries the counts, never the roster**, on the same argument the `--from` list is
  written out on. A roster is a file somebody can edit, and recording `--roster=` would let a correction next week
  silently rewrite last week's rigs under their old names
- `scenes/generated/innschleife-next-event-thl4/` (71 scenes) and `scenes/generated/psl-next-event/` (16)
- **`thl4`, the cabinet this repository refused to spec.** A purple 0.500 × 1.140 m box appears in the first two
  revisions of the 06.12.25 Staudham drawing and in none after, and 0.98.0 left it unspecced because "a spec for a
  box that may have been a draughting error would be worse than the question". Innschleife are bringing two of
  them, so it is real, and a cabinet somebody plans to put in a van is not a draughting error. Depth 0.520 m from
  `tecnare-m2122`, the same 0.500 m width and the same class; weight 59 kg by the 200 kg/m³ model and **probably
  light**, since the Tecnare runs 272 kg/m³. `build: original` is the one assertion made, and it is the weaker of
  the two available: a box called THL4 in Turbosound livery did not come off Innschleife's own drawing board
- **1082 kg of Innschleife gear across 16 cabinets**, and 37 devices, 97 units and 6316.4 kg in the library

### Changed

- **Four of the five Innschleife cabinets are named.** `wsx-18`, `sbh-18`, `kicker-15` and `thl4` replace the front
  sizes their ids used to carry. Three sub types at four each is exactly what the specs already said, so the
  mapping the counts imply is the only one the numbers allow — and the WSX reading is better than that: the
  published Martin Audio WSX is roughly 1100 × 545 × 890 mm, and this spec's 0.570 × 1.100 front was read off a
  drawing with a 0.900 depth derived from our own horn subs months before anybody said the word
- **The small tops keep their front-size id**, because "die kleinen Tops" is a description beside the THL-4 rather
  than a name. It is the one cabinet of the five nobody has named
- **Every borrowed system's ids lost their owner prefix.** `gmss-iq-sub` is `iq-sub`, `psl-thebox-pa302` is
  `thebox-pa302`, and so on for 24 devices. A spec lives at `<category>/<owner>/<id>.yaml`, so an id repeating the
  owner said it twice — the same redundancy 0.98.0 removed from the generated scene file names, in the place it
  had been left. Our own and Sepp's ids never carried one, so this is the convention arriving rather than changing
- **The mapping is second-hand and is going back to Innschleife**, and one piece of evidence pulls against it: the
  "blauen Kicker" photograph averages near black. If a name comes back wrong the fix is another rename of the same
  shape. [docs/requests.md](docs/requests.md) carries the open question
- **PSL stated what they are bringing to the next event**, which is the first figure about that inventory that
  did not come from a published package. The specs keep their sourced 6 and 4 — "brought to one gig" and "owned"
  are different facts — and `docs/requests.md` carries the open question of how many they own. **Reworded on
  2026-09-14**, because this entry asserted what they own and 0.117.0 decided that is theirs to state
- **A spoken count outranks a drawing, and this one was misremembered.** The statement said "alle 4 kleinen Tops",
  the ids and quantity were changed on exactly that principle, and reading the mapping back produced "small tops
  nur 2x". The principle holds; the lesson is to read a count back before writing it down

### Fixed

- **A generated scene built from a roster did not state its counts, so rebuilding it produced a different rig.** A
  `stack:` block is re-solved on every build and the writer only spells a count out when the share differs from
  what the specs own — and a roster hands the command specs it has already rewritten, so twelve of twelve ESX
  looked like the whole inventory and took the shorthand. Loading that file back reads the spec on disk, which
  says six. **Two of the sixteen PSL scenes came out with a cabinet standing on nothing, under a `-possible`
  name**, because the writer had checked the rig it meant rather than the rig it wrote. A count that came from a
  roster is now always written
- **`SceneLoader::find()` returned the first file whose basename matched, and basenames stopped being unique in
  0.98.0.** Ten inventories each hold a
  `stacked-1-pooled--------free----turned--alternate-center-possible.yaml` now, so `scene:build` on that name
  built whichever inventory sorted first, silently. A bare id that names more than one scene is reported as the
  ambiguity it is, with the paths, and a path still resolves as before
- **`ShippedScenesTest` was checking nine scenes and reporting that it checked every one.** Its data provider was
  keyed on the same non-unique id, and PHPUnit refuses duplicate keys — which is how this surfaced. Keyed on the
  path it runs **2735 cases** instead of 9, and that is what caught the two floating cabinets above

## [0.98.0] - 2026-09-02

Two more sound systems, and the sweep stopped enumerating inventories because five owners is where that stops
working.

### Added

- **PSL, ten specs, every figure `datasheet`.** PSL is Pro Sound & Light in Paunzhausen, a rental company rather
  than a crew, and they publish the full technical data for the whole inventory they hire out. So unlike GMSS this
  is a borrowed system whose dimensions and weights are sourced on both axes: the Concert Audio EVOLUTION trio
  (EF 6, ESF, ESX), five the box pro cabinets, one the box, one HK Audio. **22 cabinets, 1280.7 kg**
- **The EF 6 and the ESF are the same trapezoid**, 588 mm across the front and 224 across the back, and the source
  says so in words as well as figures — "identische Abmessungen wie EF-6 System". Repeating a dimension is normally
  a warning sign in a spec; here it is the point, because the two stack and fly interchangeably
- **The ESX is drawn on its side and specced upright**, which looked like a contradiction and is not: the datasheet
  says 1180 × 590 × 915 (H × B × T) and our own setup drawing shows it 118 cm wide by 59 high with the two 18″ side
  by side. Same box, rolled 90°, which is what `--orientation=turned` already produces. At **33 Hz** it is the
  lowest-reaching cabinet anybody here publishes a figure for, so `Passband::orderingLowHz()` puts it under PSL's
  own ESF
- **Innschleife, four specs, and not one of them is named.** They publish nothing, so the only source in existence
  is two Staudham setup drawings in our Drive which embed one photograph per cabinet type at **1 px = 1 cm** — a
  scale the owner states outright. Front width and height are therefore read rather than inferred, and depth,
  weight, drivers, coverage and counts have no source at all. **14 cabinets, 964.0 kg**
- Each Innschleife id is its own front size (`innschleife-sub-57x110` and so on) until somebody reads a badge. The
  rename will cost a file move, an id change and a sweep regeneration, and that price was taken deliberately: a
  spec that exists can be rendered and argued with, and the GMSS reconstruction is what made the right questions
  askable
- **[docs/requests.md](docs/requests.md)**, one row per missing figure with what reads it, so a spec's weakest
  field is a question somebody can answer rather than a footnote nobody reads. Rows are deleted rather than ticked,
  like `TODO.md`
- `--into=NAME` on `scene:stack`, naming the subdirectory of `scenes/generated/` to write into. A replay needs it:
  the recorded line names cabinets rather than owners, so it has no inventory to derive
- **SCN-10**, the front-face image, filed with its design rather than built. The material already exists — PSL's
  `PSL_Subs_px.png` is a front face at 1 px = 1 cm — and four Innschleife cabinets currently differ only in their
  bounding box

### Changed

- **The sweep builds one inventory instead of every combination of owners.** Stated by the owner: it is always run
  against a subset, and the default subset is our gear and Sepp's pooled as one rig. A bare `scene:stack` writes
  **271 scenes**. The powerset was seven inventories for three owners and would have been **31** for five, 26 of
  them multi-system rigs nobody will ever build, past the fuse before writing a file
- **What the powerset bought is kept as the default rather than re-derived every run.** Its finding was that a
  borrowed rig writes more scenes than any single owner, because Sepp's six Achenbachs cannot stand alone and are
  excellent under somebody else's tops. That answer was worth having once
- **The inventory is a directory now and has left the file name.** `scenes/generated/sdwa5-sepp/stacked-2-…` rather
  than `scenes/generated/stacked-sdwa5-sepp--2-…`, which is SWP-3's rule that an axis value appears in the path or
  in the name and never in both — **the system name inside a folder named after the system stated it 271 times over**
- **That also removes a trap rather than just a redundancy.** The owner column was padded to the widest label the
  *specs* could produce, deliberately, so that a narrowed run and a full sweep named the same rig identically.
  Speccing a fifth owner would therefore have renamed all 1374 committed scenes without changing one rig.
  `SweepAxes::labelWidth()` is deleted
- **`all` is gone as a label.** A subset covering every owner was called `all`, which was shorter and stayed
  correct exactly as long as the owner list did: it meant three systems and 39 cabinets, and two more specs later
  the same word meant five systems and 95 units. Every one of the 331 files carrying it described a rig that no
  longer had that name. `gmss-sdwa5-sepp` means the same rig whoever gets specced next
- **All 1374 generated scenes were regenerated into eight folders and every per-inventory count came out
  identical** — 271, 311, 308, 331, 97, 49, 7. That is the check that the change is a rename and not a different
  sweep
- **Silence means the default inventory on the named-rig path too.** A bare `--per-owner` used to mean every owner,
  which with five owners stood five systems side by side, four of them borrowed, from a command line that says
  nothing about whose gear
- `load:plan` and `scene:pack` are documented with `--owner=sdwa5 --owner=sepp` rather than
  `--exclude-owner=gmss`. **A blacklist of borrowed systems grows every time somebody lends us a rig; a whitelist
  of the two that travel with us does not** — and the blacklist had already gone wrong silently, quietly planning
  2.2 tonnes of PSL and Innschleife gear into our two vans
- `bin/console --version` reads `composer.json`, and `docs/inventory.md` was rewritten: it still said "all five
  enclosures listed as owned" and had never gained GMSS

### Fixed

- **`BuildAllCommand` globbed the generated set flatly in four places**, which the new directory level would have
  broken silently in the worst possible direction: the replay would have found nothing to replay, the stale check
  nothing stale, and `prune()` would then have deleted every derived artifact on the grounds that its scene no
  longer existed. One recursive helper answers all four
- **The fleet's shortfall was three different numbers across three files and all three were stale.** Re-measured:
  the two vans and the trailer carry our two systems' gear with **340.5 kg spare**, and the same plan including
  Sepp's 465 kg generator is **134.9 kg short** with three devices left behind. So the shortfall is the generator
  rather than the gear, which is not what any of `README.md`'s 214.5 kg, `docs/load.md`'s 129.5 or `TODO.md`'s
  148.2 said
- `LoadPlannerTest` filtered the fleet with `owner !== 'gmss'` and **still passed** once PSL and Innschleife were
  specced, because a fleet that is already short stays short. It names the two owners that travel now

## [0.97.0] - 2026-09-02

The spec tree grew a second level, because two more systems were about to land in a flat folder of ten files.

### Changed

- **`specs/` is now `<category>/<owner>/<id>.yaml`.** All 22 specs moved, ours included: there is no unmarked
  case any more, so `specs/speakers/sdwa5/` sits beside `specs/speakers/gmss/` rather than our gear lying loose
  in the category folder with everybody else's filed under it. The old convention was a filename prefix and it
  never held — `gmss-` and `sepp-` were owner prefixes, `rack-` and `truss-` were *category* prefixes, and Sepp's
  two speakers carried no prefix at all, so the only reliable owner signal was already the `owner` field
- **No source file changed, and that is a property of the loader rather than luck.** `SpecLoader::files()` has
  walked `specs/` recursively since generated scenes needed the same trick, `category` comes from the file's own
  field, and the one filesystem coupling — the basename must equal the `id` — reads `PATHINFO_FILENAME`, which
  does not care how deep the file sits. `SpecValidator::ID_PATTERN` forbids a slash in an id, so a path can never
  become part of one
- `docs/spec-format.md` says outright that the two directory levels are **filing rather than data**: nothing
  validates that a spec sits in the folder its own fields name, so the two are kept in step by hand and a
  misfiled spec is misfiled rather than broken
- `docs/catalog.md` regenerated. Its row order comes from `SpecLoader::files()`'s path sort, so the nesting
  reshuffled it without a single figure changing

### Fixed

- **`bin/console --version` said 0.65.0 and the project was on 0.96.0**, 31 releases stale, with a `# todo` next
  to it since the day it was written. It reads `composer.json` now, which is the file this project's own
  convention bumps, so the number cannot drift again

## [0.96.0] - 2026-08-17

The seventh axis's third value, and what measuring it said about the reading everybody had of it.

### Added

- **`tops-shared`, SWP-2's third value, so the separation axis is complete.** Each system's **subs** are its own
  stack and every top in the rig is one pool dealt across those walls. On the gear we own that is not a subtlety:
  there are exactly three top types and one belongs to each owner, so `pooled` mixes everything, `systems-apart`
  puts each owner's tops straight back on that owner's own subs, and only this value can stand a Tecnare on GMSS's
  wall. **The sweep writes 398 more scenes, 1374 in total against a fuse of 1500**, which is what raising the fuse
  was for
- `--systems=VALUE`, repeatable, narrowing the axis the way `--shape` and `--align` do. `--per-owner` is unchanged
  and still means `systems-apart`, because 433 written scenes record their own regeneration with it
- `App\Scene\SharedTops` — the deal, in one sentence: **widest top first, each cabinet to the wall with the most
  unused top face.** So the long throw is placed before the fill, the tops spread across walls instead of piling on
  one, and every top is dealt somewhere even where no wall has room left. Five tests pin the rule's fairness rather
  than its optimality, the tie included: the sweep names a file per rig, so two runs that resolved a tie differently
  would write two files for one rig
- `StackBlock::topFaceWidthM()`, which is what a thing standing *on* a stack has to fit, as against `widthM()`'s
  widest tier anywhere. A pyramid's base is its widest row and its top face its narrowest
- Three tests at the command level, one of them the point of the whole value: some stack carries another system's
  tops, asserted generically rather than on the pair of ids it happens to produce today

### Changed

- **The deal is a second pass over solved walls, which is what the item asked for and why.** How much top face a
  wall offers is the solver's answer rather than the inventory's, since the number of rows a wall comes out with is
  what the fill searches for — so a deal made before the first solve is a deal made against a guess. Each stack is
  then solved again from its subs plus its dealt share, and the first solve is thrown away apart from its geometry
- The dealt count is written as `count:` on the stack entry, a key `StackEntry` already had, so a `tops-shared`
  scene is an ordinary generated scene and re-solves on every build like every other one. **No schema change, no new
  placement type, and all 1374 scenes replay from their own recorded command to byte-identical files**
- `SweepAxes` gained the sixth parser and now describes seven axes rather than six

### Fixed

- **70 generated scenes had a sub wall short of its interface and said nothing about it**, and the rule that made
  them silent was defensible: "nothing to fire over anybody's head means nothing to say". That is right about the
  tops and wrong about the header, which prints `Subs reach 1.800 m against a 2.000 m interface` for every stack
  whether or not anything stands on it — so an unexplained miss reads as a solver bug to the next reader. A sub wing
  now says that nothing stands on it, and the sweep's console note says the same rather than claiming tops fire below
  head height when there are no tops
- **The test that was supposed to catch that had a hole in it.** It measured each wall and then looked for the
  explanation *anywhere in the file*, so a scene whose other stack warned about something covered for a silent one.
  Only the one scene with no other warning at all ever failed. Each wall is now held to the lines that follow its own
  header, which is 4413 assertions against 1374, and the tightened version was checked against a pre-fix file rather
  than trusted: it fails on exactly the wall that used to pass
- **Neither defect was specific to the new axis value.** `--split=by-type` deals a stack whole types and can leave
  one of them with no tops the same way, and 69 of the 70 files predate this release

### Known

- **A narrow wall beside two wide ones can be dealt no tops at all**, and that is deliberate rather than a gap in the
  rule. On the `all` rig at one stack per owner all eight tops went to the sdwa5 and gmss walls and left Sepp's six
  Achenbachs as a sub wing, because the wide walls still had more unused face than the 1.22 m one had in total. The
  alternative is worse: forcing a top onto a wall that cannot carry it means the solver drops it and the cabinet is
  in no stack at all, which this repository treats as the worse failure throughout. What a starved wall must do is
  say so, and now it does
- **What `tops-shared` shares is the pool and not the row, and that was settled by measuring rather than by
  argument.** The tempting reading is one tops row bridging two sub walls, and it cannot be built: the three walls
  come out at **2.31 / 2.383 / 1.8 m** in the `systems-apart` scenes and **2.44 / 3.61 / 1.8** in the upright
  variant. Two walls drawn from two different inventories do not come out level, and there is no common module to
  make them — our cabinet heights are 0.600 / 0.763 / 0.836 / 0.914 / 0.960 m. A row resting on both would hang in
  the air over the lower one
- **So SYM-3 did not fall out of this item, contrary to what TODO said for three releases.** A row that really does
  bridge two walls needs walls that are level by construction, which means a mirrored pair out of one pool. That
  mechanism is still to be written, and SYM-3 now waits on nothing instead of waiting on SWP-2
- Adding the value renamed nothing. `tops-shared` is 11 characters against the 13 `systems-apart` had already set
  the id column to, and **not one of the 976 existing scene files changed** — which is the check that the new value
  leaks into neither of the other two

## [0.95.0] - 2026-08-17

The pack as a picture, the wheel arches as boxes, and two specs corrected by their real datasheets.

### Added

- **`scene:pack`** — the load plan as a scene: the convoy in a row with each vehicle's load standing inside it.
  `load:plan` assigns and this places, which are different problems; the positions come from **one stated rule**
  (heaviest first onto the bay floor in rows, then columns on top) rather than an optimal pack. Written as an
  ordinary scene into `scenes/packs/`, so `ShippedScenesTest` sweeps it for cabinets inside each other exactly as it
  does a rig
- **The wheel arches are modelled as solid boxes.** Stated by the owner. Their intrusion is **derived** — a bay
  1.765 m wide with 1.380 m between arches gives 192.5 mm each side — and only the length, height and position along
  the bay are estimates, the last because the axle position is in no document we hold
- `App\Load\PackLayout` and `PackSceneWriter`, with seven tests pinning the rule's **honesty** rather than its
  quality: nothing silently dropped, no two units in one place, the floor inside the arches, nothing past the bay
  depth or through its roof, and a stacked unit always naming a real support
- **`scene:render --labels` — Beschriftungen and a legend, stated by the owner.** One label per device **per
  vehicle** with the count in the text, since seven Flexys labelled seven times is noise and the same cabinet in two
  vans is two facts; the vehicles named; and a legend standing beside the scene naming what each cage colour means,
  which is knowledge that otherwise lived only in `blender/lib/materials.py`. Drawn at *render* time like the aim
  lines, so one assembled `.blend` draws with labels or without and neither is canonical. Every label turns to face
  the camera, because text on a fixed axis is unreadable from half the presets
- The legend only names what the scene can contain: a rig has no cages and no wheel arches, and a legend a reader
  trusts about things that are not in the picture is worse than none

### Fixed

- **`truss-f33-2m` is a Global Truss F33200 and weighs 9.3 kg, not 10.3.** The old figure was derived by fitting a
  line through three published F33 weights, which reproduced all three to within 0.13 kg and was **10 % out at the
  one length nobody had published**. Across five segments that is 5 kg. Provenance goes from `estimated` to
  `datasheet` on both dimensions and weight
- **`truss-tower-4m` is a Varytec Wind Up 85 kg, not the assumed Global Truss ST-132 — the wrong product with the
  right numbers.** The substitution got height and weight exactly right, 4 m and 25 kg, while **overstating the max
  load by 15 kg**: 100 against 85. The two figures easy to check agreed, so the one that decides what may hang from a
  truss bar never got checked. It also missed a **minimum** load of 25 kg, which a wind-up needs to crank safely and
  which no field holds
- **A load bay contains rather than collides**, which is what made the pack scene checkable at all. The geometry
  sweep called the first unit of `packed-convoy` **1.09 m inside the Movano** and would have gone on to say it of
  all 25, because a vehicle's box and a cabinet standing in it overlap by construction — `scene:build` would have
  caged the whole load in red. `Shape::isHollow()` is the exemption and only the load bay qualifies: a truss and a
  scaffold are *open* rather than hollow, their chords are solid, and two of them in one place is a real fault. **The
  pair is skipped, not the check weakened** — two cabinets in one spot inside a bay are still reported, which is the
  reason to sweep a pack in the first place. What nothing checks anywhere is whether the load sticks out *through* a
  wall, which is a containment question rather than a collision one
- Two flaws in the pack scene's own notes, both found by looking at the render: height warnings were reported by
  *assignment* rather than by what was drawn, so both scaffold towers were flagged as "drawn standing" when neither
  had been placed; and a 1.2 m generator on a 1 m trailer was called something that "would have to be laid down",
  which is false — an open bed has no roof

### Known

- **The pack rule cannot turn anything, and 7 of 32 assigned units are unplaceable because of it** — mostly truss
  segments and scaffold towers, all of which would lie down without difficulty. Filed as **LOAD-6**, and the
  geometry is not new: `Orientation` already does this on the rig side
- **A device has an erected size and a transport size and the schema has one field for both.** The truss lift
  transports at 1.75 m and is modelled at 4 m, which is why the convoy render shows a mast standing out of a
  trailer. Filed as **SPEC-15**
- **Labels overlap each other**, since nothing lays them out to avoid it: eighteen on the packed convoy at
  960 × 540 have several sitting on one another. A real fix means screen-space placement, which means projecting
  through the camera. **CVR-9** carries the remainder
- Two sizing mistakes, both found by looking rather than reasoning: labels at `radius / 60` came out around nine
  pixels tall at `--quick-preview` and were a grey smear, and the legend offset by a tenth of the radius — 0.9 m
  against vans 2 m wide — landed on top of the Movano and read as text painted across its side

## [0.94.0] - 2026-08-17

The trailer, on the owner's instruction, and the field it turned out to need.

### Added

- **`specs/vehicles/trailer-750kg.yaml`** — 750 kg permitted gross, ca. 200 kg unladen, so 550 kg of payload. Not yet
  bought, which its own notes admit: `quantity: 1` says the fleet has one because the planner needs a bin, and that
  overstatement is written down rather than hidden
- **`carried_on`, a field on the device naming the one transporter it may ride on.** Without it the plan was
  unloadable. `load:plan` scores bins by how strained they are, and a 550 kg trailer holding a 465 kg generator is by
  far the most strained of the three — so left to the score it sent the generator to a **van** and filled the trailer
  with speaker cabinets. Legal on every weight check, impossible to load, since two people cannot lift it and no van
  has a ramp
- Pinned devices are placed **first**, before anything else can take the room, and **a pin that cannot be honoured
  leaves the device behind** rather than quietly unpinning it — reporting success on a plan nobody can execute is
  worse than a remainder
- A cross-spec validator rule: `carried_on` must name a transporter in the library, and may not appear on one, since
  a trailer is not cargo. It matters more than it sounds, because the planner leaves a pinned device behind when it
  cannot find its bin — so a typo would turn "this rides on the trailer" into "this does not travel" while the plan
  looked complete
- Four tests

### Known

- **Three bins and 148.2 kg still at home**, against 214.5 kg before the trailer. All three finish within ten
  kilogrammes of their limit and all three report `UNDECIDED` for it
- **The trailer states no `load_bay_m` at all, deliberately.** An open bed has a length and a width and no ceiling;
  `load_bay_m` requires all three axes, so a side height would be read as a roof and refuse anything taller than the
  sides. No bay means no space answer, which is honest for a flatbed. What is still weak: nothing distinguishes "no
  bay because it is open" from "no bay because nobody measured it"
- **Towing is checked nowhere.** O1 and O2 are printed on both sets of papers and stored in no field, and nothing
  pairs a trailer with the vehicle that tows it. 750 kg is exactly O2 unbraked on both vans, so today the combination
  is legal by construction rather than by check

## [0.93.1] - 2026-08-17

### Changed

- **The trailer Sepp is buying does not reach one journey, and the arithmetic is recorded before the purchase rather
  than after.** Stated by the owner: 750 kg permitted gross, ca. 200 kg unladen, so 550 kg of payload — and the
  generator is 465 of it, leaving **85 kg**. The whole load comes to 2703.5 kg against 2574 kg of capacity, which is
  **129.5 kg short**. The trailer is a net gain of only 85 kg, because it brings 550 kg of capacity and 465 kg of new
  load with it: a generator trailer rather than spare space. Closing the rest needs a payload near 680 kg, roughly
  900 to 1000 kg gross and braked
- **Not specced until it exists.** `specs/` is an inventory of what the collective has, and its unladen mass is a
  "ca." figure the real machine will settle. LOAD-5 carries the arithmetic; the spec is one file the day it arrives
- LOAD-5 now also names **what the schema is missing beyond the trailer**: towing capacity, which is on both sets of
  papers (O1 3000/2500 braked, O2 750 unbraked) and in no field of any spec; which vehicle tows which trailer, being
  a pairing rather than a property of either; and that `load_bay_m` requires all three axes, which is right for a van
  and wrong for an open flatbed

## [0.93.0] - 2026-08-17

Sepp bought a 25 kVA generator, and it is the heaviest single object in the library by a factor of two.

### Added

- **`specs/power/sepp-generator-25kva.yaml`** — 465 kg, 25 kVA, engine Hatz 3M41, all three stated by the owner.
  The next heaviest device is GMSS's 220 kg wall bass and the heaviest thing that actually *travels* is a 90 kg
  SKRAM, so this is **45 % of either van's entire payload on its own**, and the first device here that two people
  cannot carry at all
- **`load:plan --exclude=ID`**, which is not the question `--exclude-owner` answers. The generator travels on a
  **trailer** rather than in a van — stated by the owner — so it is neither a whole owner's gear nor part of a van's
  load. Without a way to say so the planner puts 465 kg in a van and reports a **679.5 kg** shortfall nobody has.
  A vehicle id is refused, because a van is not cargo
- Three tests, and `specs/power/` as a directory

### Known

- **The trailer is the item most likely to put the whole library on one journey, and it is filed as LOAD-5.** The
  vans are 214.5 kg short; a trailer takes that remainder *and* the generator comfortably, since the Ducato tows
  3000 kg braked on its papers and the Movano 2500. `subtype: trailer` is already accepted and `LoadPlanner` already
  handles any number of bins — what is missing is the trailer's own figures and the rule that **a trailer's load is
  limited by what tows it**
- **The dimensions are estimated and rounded up**, which is the opposite direction from the load bays and for the
  opposite reason: a bay estimated small refuses a load that would have fitted, where an *item* estimated small
  promises a fit that is not there. Expect the real machine to be smaller by up to 200 mm on any axis. The engine
  does not settle the frame — several firms build 25 kVA sets on a 3M41 and an open skid, a canopy and a trailer
  version are three different boxes. One photograph of the nameplate settles the make, the mass and the output
- **No schema field holds 25 kVA**, so it lives in the spec's notes. Filed as SPEC-14, deliberately deferred until
  a second device needs it: a field with one user is a field that will be wrong about the second
- A 465 kg item needs a ramp, a tail lift or a forklift, and the planner knows nothing about any of them

## [0.92.0] - 2026-08-17

**Stated by the owner: the vans need at least wire-type models so a pack can be planned.** They do, and the category
that arrived four releases ago as the one thing never drawn is now drawn.

### Added

- **`shape: load-bay` — a transporter as a cage.** Three parts, each answering a different question a packer asks:
  the vehicle outline for scale, the load bay inside it, and the floor between the wheel arches. On the Movano the
  bay is 1.765 m wide and 1.380 m between the arches, so 385 mm of that width exists only above arch height, which
  is the difference between a cabinet fitting on the floor and not
- `blender/lib/bay.py`, three materials, and one line in `build_model.py`'s dispatch table — the fourth open-frame
  shape needed no new mechanism, which is what that table was for
- Two validator rules that keep the category and the shape in step: **a vehicle drawn as a solid is refused**, and a
  cage with no bay is **not**, because requiring both would make them imply each other and kill the reason the bay
  is optional. A van can be specified from its papers before anybody has been in the back of it, so a bayless
  vehicle draws its outline alone — the honest picture of a van whose inside nobody has measured

### Fixed

- **`build:all` was broken outright, and the loop it broke in was a good one.** `models:build` skipped the two
  vehicles on purpose, and `library:build` then refused to run because two models were missing, advising *Run
  `bin/console models:build` first* — the command that had just declined to build them. Reported by the owner
- **`Category::producesAModel()` is gone.** Every category produces a model again, so an abstraction whose only case
  was wrong is worse than no abstraction. The argument it was built on — that a 6.8 m solid van would be the largest
  object in any picture including it — was right about the *solid* and wrong about the *model*
- **The cage's bars are inset so its outer surface is the declared box.** Centred on the edges, a 25 mm bar put half
  its thickness outside: the Movano came out 2.095 × 2.833 × 6.873 m against a declared 2.070 × 2.808 × 6.848,
  failing `tools/check-glb.py` on all three axes and on the origin, with its lowest point 12.5 mm below the floor

### Changed

- `plan_version` 3 → 4, for `geometry.load_bay`
- The asset library holds 20 devices rather than 18, and the count it prints is honest again
- **`TODO.md` compacted from 1600 lines to 1055.** Eight resolved items were sitting in its tables marked `done`
  with 428 lines of detail behind them, which the Definition of Done says should have been deleted. Checked before
  deleting: the seven orientation/mirror pairs and the vacuous-style rule are in `docs/scenes.md`, the packing
  heuristic in `docs/load.md`, the caging in `docs/pipeline.md`, the release history in this file. LOAD-2 and SWP-2
  were rewritten rather than trimmed, both having described a state that no longer exists
- **`docs/scenes.md` showed the five-field id format**, predating both axes added the day before. The diagram and all
  four examples now show seven
- Sepp's spec records **two seats** (S1/S2) and the 120 kg nose weight (A12), with the note that a passenger comes
  off the payload — both stored masses assume a driver and nobody else

## [0.91.0] - 2026-08-16

SWP-2's separation axis, which is the seventh and the last one stated. **Stated by the owner, shown the measured
count first: take all of it, and raise the fuse to 1500 so the third value has room too.**

### Added

- **The sweep offers rigs where the two sound systems stand apart, which it never did.** `App\Scene\SystemSplit`
  with `pooled` and `systems-apart`. Every generated scene pooled the gear until now — verified rather than assumed,
  since not one written file carried `--per-owner` in its recorded command, because naming that option collapses the
  sweep to a single point. The mechanism existed and the sweep could not reach it
- **Separated rigs are not marginal, they are the majority where they apply.** On the `gmss` + `sepp` pair the sweep
  writes **116 separated against 90 pooled**: giving each system its own narrower stack stands up more often than
  pooling two systems into one wide one. Across the whole sweep the axis adds **433 scenes to 543**, for 976
- A rig drawn from one owner is offered `pooled` alone, because one system separated from nothing is one system.
  Left to the deduplication, every single-owner rig would be solved twice to write one file, and single-owner rigs
  are 153 of the sweep
- Two tests, one of which asserts the separated half is the *larger* half — asserting only that the field is present
  would pass on a sweep that never separated anything, which is the state this replaces

### Changed

- `DEFAULT_MAX_SCENES` 800 → **1500**, and this time the number was measured before the raise rather than after. The
  sweep writes 976; the headroom is for SWP-2's third value, which is not buildable yet and would add roughly 400
- Every id gains a seventh field, so all 543 existing scenes are renamed again. The `pooled` half is byte-identical
  in content to what it replaces

### Fixed

- **A separated rig recorded a command that rebuilt it pooled, and `build:all`'s replay is what caught it.** The
  axis lives on the rig rather than on the input, so `commandLine()` reading `--per-owner` off the input wrote
  nothing for a swept `systems-apart` rig. Replaying then produced a *different* rig under the separated one's
  name — **99 of the 976 scenes replayed to a different file**, most of them flipping `-possible` to `-impossible`.
  The stage whose job is exactly this found it, which is the second time it has earned its keep. Now asserted
  directly as well, so the next axis that lives on the rig is not caught by a three-minute test over a thousand
  files

### Known

- **The third value, `subs apart, tops shared`, is not buildable and the reason is architectural.** It breaks an
  assumption the code holds everywhere: that a stack's tops come from the same pool its subs came from. The solver
  is handed one id list per stack, so "these subs, those tops" cannot be expressed. It needs a second pass dealing
  the tops after the sub stacks are solved, because only that pass can see the sub wall heights the tops row sits
  on. **Adding it costs no rename** — `systems-apart` is the longest value and already sets the field's width
- 976 scenes is 976 `.blend` files and 976 renders per `build:all`, which roughly doubles both stages

## [0.90.0] - 2026-08-16

Sepp's transporter got its papers read and then got put on a scale, and **the two disagree by 365 kg**.

### Fixed

- **Sepp's payload has been three numbers in one day, and only the last one has been near the vehicle.**

  | source | payload | fleet against a 2238.5 kg load |
  | --- | --- | --- |
  | estimated, deliberately cautious | 1200 kg | 14.5 kg short |
  | Zulassungsschein, field A10 | 1365 kg | 150.5 kg spare |
  | **weighbridge, full tank and driver** | **1000 kg** | **214.5 kg short** |

  The estimate was pessimistic and the document was optimistic, which is not the order anybody expects. **A
  registration document is authoritative about what a vehicle may weigh and merely historical about what it does**:
  field F2's 3500 kg is the law and no scale can supply it, while the Eigengewicht is a figure from the day of type
  approval and this van has had shelving, a bulkhead and a ply floor since. Add 75 kg of diesel for a full 90 litre
  tank and the gap is accounted for. A payload needs both sources
- **Sepp's van is a Fiat Ducato, not a Peugeot Boxer.** Right platform, wrong badge — Boxer, Jumper and Ducato are
  the same Sevel van and share their bay dimensions, so the estimated geometry did not move. Luck, not method
- 0.87.0's note that scoring bins on both weight and volume "changes nothing on our own fleet" is now right for a
  different reason: **weight decides everything**, because both vans finish within three kilogrammes of their limit
  and there is no slack for a second dimension to spend

### Changed

- `specs/vehicles/sepp-transporter-l3h2.yaml` → **`specs/vehicles/fiat-ducato-250-l3h2.yaml`**, named by make and
  model like the Movano rather than by whose it is, which the `owner` field already says
- **The first `provenance.weight: measured` in the library.** Its permitted gross stays `datasheet`, because a legal
  ceiling is not something a scale can tell you — the split between the two is the point
- **The 75 kg driver, reconciled between two countries' documents.** Austrian `Eigengewicht` excludes a driver where
  the German field `G` includes one, so `3500 − 2060` derives 1440 kg against the document's own stated 1365.
  Stored literally it would have handed a packer 75 kg it does not have. The weighbridge figure had the driver
  aboard and needs no such adjustment

### Added

- `tests/Load/LoadReportTest.php`. **The report's rules about what is wrong needed somewhere to live that does not
  depend on the fleet being wrong**, since what the fleet is wrong about changed twice today — an overflowing bay
  and an undecided margin are now demonstrated on plans built to order
- `VehicleTest` pins the weighed mass, the derived 1000 kg payload and the `measured` provenance

### Known

- **The Movano has never been weighed, and its 1024 kg is the same class of figure that just proved 365 kg
  optimistic.** Until it goes on a scale the fleet total is one measurement plus one assumption, and the assumption
  is the optimistic kind. LOAD-2 is back at P1 for that reason alone
- Both load bays are still manufacturer figures, and so is Sepp's outer box: an Austrian Zulassungsschein carries no
  dimensions at all. Whether his van is an L3H2 or an L3H3 is 236 mm of doubt that one look at the roof removes

## [0.89.0] - 2026-08-16

CVR-5's second half, and with it the last value of SWP-1's cross product. **Stated by the owner: treat `impossible`
like the other axis, and raise the fuse to 800.**

### Added

- **A rig that does not stand up is written rather than refused.** `App\Scene\Feasibility` is the sixth axis, and
  the two checks that name a cabinet — nothing under it, two cabinets inside each other — stopped refusing and
  started reporting. The rig is written, named `-impossible`, and `scene:build` cages the offending cabinets in
  red. Verified end to end: `stacked-gmss-------2-v-------mixed---centred---block--impossible` renders with its
  floating `gmss-turbo-top` caged and hanging off the end of the row, which is a diagnosis nobody had to read
- **Every id says which side of the axis it is on, possible ones included.** A name with a gap in it says a value
  was left out and never which one, which is the argument that took `pyramid`, `upright` and `alternate` out of
  hiding in 0.79.0. This renames all 483 existing scenes and pads the alignment field, since something now lines
  up behind it
- An impossible scene carries the reason in its own header, so a reader who opens the file learns it there
- `ShippedScenesTest` skips them **by name**, through `Feasibility::isImpossibleId`, so the writer and the test
  cannot disagree about which files are which. A second test compiles every impossible scene and insists it really
  does fail a check — without it the axis would be a way to opt any rig out of every geometry rule by naming it
- Three tests in `SceneStackCommandTest`, including the one that pins **what is still a refusal**: a rig with no
  workable arrangement has no geometry to look at, so painting it red is not an option

### Changed

- `DEFAULT_MAX_SCENES` 600 → 800, as asked. **The premise for it turned out to be wrong and the raise is kept
  anyway**: the sweep writes 543, not the 627 that made 600 look 27 short, so nothing was actually blocked. 800
  leaves room for SWP-2, which is the next axis
- **The fault messages describe rather than refuse.** They used to end "so it is not one of the possibilities",
  which was true while these were refusals and became a contradiction the moment the rigs started being written:
  the file's own header says it is written on purpose and the next line said it could not be

### Fixed

- **The count in 0.88.0's notes was the number of refusals, not the number of scenes.** The sweep writes **60**
  impossible rigs, not 144: 84 of those refusals produce the same impossible geometry as a sibling and collapse in
  the deduplication, which they had never reached before because they were discarded one step earlier. 483 + 60 =
  **543**

## [0.88.0] - 2026-08-16

CVR-5's first half: a cabinet the geometry checks object to is now **shown** rather than described.

### Added

- **`App\Scene\Fault`, and the two checks that name a cabinet now answer with identities.**
  `PlacementChecks::floatingFaults()` and `Interpenetration::faults()` return every offender with its placement id,
  where before each formatted the first one into a sentence and discarded the identity. "A `gmss-turbo-top` would
  stand at 4.668 m with nothing under it across x" took a debug dump, two probes and a corrected coordinate mapping
  to understand; a picture with that cabinet caged says it at a glance
- **`scene:build` writes the faults into the scene plan and Blender draws a red cage around each one.** Verified on
  a probe scene with two SKRAMs 0.3 m apart: both are caged, the third cabinet is not, and the overlap is obvious
  in the render. A cage rather than a recolour, because a placement is an empty instancing a linked collection and
  an empty takes no material — and because a cage says "this one" without hiding what it points at
- `materials.fault_material()`, standalone rather than part of `build_set()`: the marker belongs to a scene and not
  to a device, and asking for an `appearance` section would mean handing it a cabinet's colours to build something
  that must not look like any cabinet
- `tests/Scene/FaultTest.php`, 9 tests

### Changed

- **The prose refusal is now built from the marking rather than derived alongside it**, so the sentence a terminal
  prints and the cabinet a render cages can never be about different cabinets. `PlacementChecks::floating()` returns
  the first fault's own message and `Interpenetration::worst()` formats the deepest of the same sweep
- **Every offender is named, not the first.** A refusal needs one reason to be a refusal; a picture showing one of
  four floating cabinets reads as a complete diagnosis and is worse than no picture. A cabinet floating across both
  axes is still one fault, because marking it twice would count one failure as two

### Known

- **Dormant on the shipped set, by design.** `ShippedScenesTest` guarantees no scene in this repository has either
  fault, so what this serves today is a hand-written scene and a regression. Emitting the sweep's own refusals as
  scenes to look at is the other half of CVR-5
- **The refusals were re-counted against the fuse, which is CVR-5's own first job, and the fuse is too small.** Of
  723 refusals in the default sweep, **579 are duplicates** rather than failures and must never be rendered; the
  real set is **86 floating and 58 interpenetrating**. 483 written plus 144 impossible is 627 against a
  `DEFAULT_MAX_SCENES` of 600, so the headroom of 117 that CVR-5 was banking on is 27 short

## [0.87.0] - 2026-08-16

### Added

- **`load:plan`, and the answer is that the fleet does not carry the library.** It assigns every unit to a
  transporter, heaviest first, with the payload as a hard refusal, and reports weight and space as **two** verdicts.
  On our own gear with GMSS excluded it comes back **20.6 kg short**: 2238.5 kg against 2224 kg of combined payload
  is infeasible before any assignment is made, so no ordering carries it. Leaving the remainder visible is the whole
  point — a heuristic that hid it to look successful would be worse than useless
- **A payload overrun exits `OVERLOADED` (2), not `FAILURE`.** It is a fine, a liability question after an accident
  and a refused insurance claim, so it can never be a warning somebody scrolls past; it is also not a broken
  command, and a script wants to tell "the fleet is too small" from "this crashed". The same argument
  `scene:stack` makes for `NOTHING_TO_WRITE`
- **Space is reported as a lower bound and never as a permission.** A bay already exceeded by bounding boxes is
  evidence the load will not go in; a bay 60 % accounted for is not a pass, because boxes do not tessellate and a
  horn mouth is not a brick. A bay nobody has measured gets no space answer at all
- **The provenance of the verdict, printed with the verdict.** No weight in this library has been on a scale and
  half the fleet payload is Sepp's assumed 1200 kg, so a margin inside that uncertainty is reported `UNDECIDED`
  rather than passed. Today both vehicles land there, at 2.1 kg and 4.0 kg of margin
- `App\Load\LoadPlanner`, `LoadPlan` and `LoadReport`; `tests/Load/LoadPlannerTest.php` and
  `tests/Command/LoadPlanCommandTest.php`; [`docs/load.md`](docs/load.md)

### Fixed

- **A bin scored on weight alone produces plans that are legal and unloadable**, found by running the planner rather
  than by reasoning about it. 30 % of volume headroom *across a fleet* says nothing about either vehicle. Bins are
  now scored by the worse of their two fills, which is the standard vector-bin-packing move. **It changes nothing on
  our own fleet** — twelve Flexys are 1020 kg of a 2224 kg payload, so wherever they go that van is full by weight —
  and the first version of the test that was meant to prove it used two vans of equal payload and passed under both
  scorings. Replaced with one that fails under weight-only scoring, verified against a patched copy

### Known

- **Sepp's van comes out at 112 % of its bay against the Movano's 33 %**, and no ordering fixes it for the reason
  above. What would fix it is a real answer about space, which needs a tape measure inside both bays
- The plan is a bounding-box assignment, so it does not yet know that heavy goes low, that the Movano's wheel arches
  narrow its floor from 1.765 m to 1.380 m, that racks roll and cabinets do not, or that a cabinet has to fit
  through the doors

## [0.86.0] - 2026-08-16

### Added

- **A transporter is a device now, so the library knows what it has to be carried in.** `Category::Vehicle` with the
  subtypes `van` and `trailer`, and a validated `vehicle:` block holding the permitted gross mass and, optionally, the
  load bay. **The payload is derived rather than stored**, as `F.2 − G` off the Zulassungsbescheinigung, so it cannot
  drift from the two masses it comes out of and every figure in the file still cites a numbered field. Stated by the
  owner: a transporter belongs in `specs/`
- **`specs/vehicles/opel-movano-l4h3.yaml`, off its registration document.** 6.848 × 2.070 × 2.808 m outside,
  2476 kg in service against 3500 kg permitted, so **1024 kg of payload with the driver already counted**, since field
  G includes 75 kg by EU definition. The bay is estimated at 4.383 × 1.765 × 2.048 m with 1.380 m between the wheel
  arches, on the owner's instruction to estimate rather than wait for a tape measure. **No VIN, no plate and no
  address**, none of which is a packing input
- **`specs/vehicles/sepp-transporter-l3h2.yaml`, and it says in capitals that it is a guess.** An ex-Deutsche-Post
  L3H2, probably a Peugeot, built 2014, with every number estimated and both masses assumed rather than read. It is in
  the repository so the fleet has two bins rather than one, and it must not be planned against
- **`Category::producesAModel()`, the first category that answers no.** A van is what the picture's contents are driven
  to the gig in, never a thing placed in one, so `models:build` and `library:build` skip it. Without this Blender is
  handed a 6.8 m white box that would be the largest object in every render it appeared in
- **The catalog reports a `fleet` beside the library.** Payload and bay volume per vehicle, so the one comparison that
  matters can be read off `bin/console catalog` instead of worked out
- `tests/Spec/VehicleTest.php` — 8 tests over the payload arithmetic, the refusals, and the two real specs read out of
  `specs/` rather than out of a fixture

### Fixed

- **A vehicle counted as cargo and it broke the only comparison the totals exist to support.** Two vans took the
  library from 3493.7 kg to 8269.7 and `owner sdwa5` from 1856.5 kg to 4332.5, which is exactly the figure somebody
  would hold up against a 1024 kg payload. Vehicles are now out of the weight, volume and per-owner totals. **They stay
  in the provenance tally**, because a van is very much a thing nobody has measured and skipping the whole iteration
  reported `2 of 20 measured` with the two least-measured devices in the library counted as done

### Known

- **The load is 14.5 kg heavier than the fleet can legally carry, and both halves of that comparison are estimates.**
  GMSS gear does not travel in these two vans, stated by the owner, which leaves 2238.5 kg in 20.490 m³ against
  2224 kg of payload and about 29.2 m³ of bay. Half the payload is Sepp's assumed 1200 kg, so the deficit is inside
  the error bar of its own input. Fields F.2 and G off his papers decide one trip against two, and LOAD-3 waits on
  them rather than on code

## [0.85.0] - 2026-08-16

Runtime. **Stated by the owner: kill the running suite and make the thing fast**, with the lighting sweep named as
the first thing to drop if it was what held the pipeline up. Nothing here changes an answer. Every measurement below
was checked by diffing the output against the serial run, and every one is byte-identical.

### Changed

- **The `scene:stack` sweep is solved across every core.** The candidate loop was embarrassingly parallel and had
  always been run on one: each candidate is a solve and a compile over the same immutable inventory, none of them
  reads what another writes, and the file writing happens afterwards on the survivors. **The default sweep goes from
  25 minutes to 1m58s on 28 cores**, output byte-identical, and a single-owner rig from 62 seconds to 5.6. The whole
  sweep is 1206 candidates and about 100 minutes of CPU between them, so the ceiling here is the machine rather than
  the code. `--jobs=1` is the serial path
- **`build:all` regenerates the scenes across every core too.** 483 recorded commands that share nothing took a
  quarter of an hour of one core. Each child's console output is captured and printed back in file order, so the log
  reads exactly as a serial run's did. **The one behaviour that changes**: a broken replay is reported after the
  stage has run rather than stopping it, and the failure named is the one the serial order would have named first
- **`build:all` renders one picture per scene instead of eight, which reverses 0.70.0's default.** Four lighting
  presets times two aim modes times 483 generated scenes is 3864 pictures out of the slowest tool in the repository.
  `--every-variant` asks for the eight back. The useful-by-default argument that made the sweep the default still
  stands for everything cheap, and a render is not cheap
- **JIT on, in `bin/console` and in `phpunit.xml`.** The image ships opcache with a 64 MB JIT buffer and
  `opcache.jit` empty, which means off, and `opcache.jit` is settable at runtime where the buffer is not. One line in
  each place, no image or ddev config to keep in step. **2m31s to 1m58s** on the parallel sweep
- **A generated scene is written to a temporary name and renamed into place.** A rename is atomic on one filesystem,
  so two replays that name the same file — TOOL-15's six duplicates are exactly that — cannot interleave inside one

### Added

- `App\Process\Parallel`, the fork helper behind both stages, and `tests/Process/ParallelTest.php` covering the three
  properties a race would break first: the order out is the order in, work is taken from a shared cursor rather than
  dealt out in advance, and a worker that dies is an error rather than a shorter answer
- `--jobs` on `scene:stack` and on `build:all`; `--every-variant` on `build:all`
- `testTheSweepSaysTheSameThingInOneProcessAsInTwentyEight`, which is the drop-in claim asserted rather than argued

### Fixed

- **The suite's slowest class was measured rather than guessed at.** The PHPUnit result cache holds a per-test
  duration, so the profile was free: of 4238 seconds, **1519 were one test** and the top six were 3044 between them.
  Every one of them was a sweep. `BuildAllCommandTest` goes from 7m46s to 1m14s

### Known

- **The CPU bill is unchanged and TOOL-9 is only half closed.** Its stated lever was pruning ladder steps that cannot
  change the answer, and this prunes nothing — it buys the same work more cores. The sweep is now something you can
  run while waiting, which was the point, but a laptop with four cores gets four times less of this than this
  machine does
- Memoising `solveGroup` was tried and abandoned: 1512 solves across 189 candidates produced **zero** cache hits, so
  every solve in a sweep is genuinely distinct

## [0.84.0] - 2026-08-16

### Fixed

- **Every solo generated scene rebuilt as a different rig from the one its own header described.**
  `SceneStackCommand::stackFor()` gives a stack with nothing beside it `slideSlackM: INF`, so a badly-carried row may
  be moved sideways to get it under something. `Stack::fromReader()` had no key for that value, so the compiler
  re-solved the written file with `null`, which means the row may not move at all. The file therefore stated a
  constraint set that was not the one it was solved against. Measured on
  `stacked-all--------1-free----turned--alternate-center`, same inventory, same placement, same focus table: **four
  rows reaching 1.860 m of subs** with the slack against **two rows reaching 0.660 m** without it, a 23.5 m line of
  cabinet with the tops 1340 mm below the interface. The other candidates were ruled out first and each gives the
  identical wrong answer — the per-entry `aim: near`, the real placement against the command's probe placement, and
  the scene's focus table against the writer's
- **The scene files barely move and the models do.** **148 of the 483** generated scenes are solo and each gains one
  line, and the header comments do not change at all, because the solve was always right and it was the rebuild that
  was wrong. Every `.blend` and every render of those 148 is a different rig from now on, which is the whole point and
  is nearly invisible in `git status`
- **The sweep writes 483 scenes against 450, and the 33 are rigs the command was throwing away at its own last
  gate.** `scene:stack` compiles each candidate's written YAML before accepting it, and that compile read the file
  the compiler would — so it re-solved without the slack, disagreed with the solve that had just produced the
  candidate, and discarded the rig. 43 scenes are new and 10 are gone. Attributed rather than assumed: a sweep from a
  worktree at 0.83.0 writes 450, and **the same worktree with only the `Gravity` line changed also writes 450**, so
  the gravity fix really was inert and this is the whole of the difference. The fingerprint is in 0.83.0's own
  refusals — the scenes now written were skipped with "a `gmss-turbo-top` would stand at **0.660 m** with nothing
  under it", which is the slide-slack answer exactly
- **`build:all` could not be run twice in a row**, which is **TOOL-7** and was reported by the owner running it.
  `scene:stack` returned the same exit code for "the command broke" and "every candidate was refused for a stated
  reason", so the regenerate stage read the second as the first: one rig that had stopped solving aborted the whole
  stage and took the other 482 replays with it. `stacked-all--------1-pyramid-mixed---alternate-stereo` is the file
  that surfaced it — a `gmss-turbo-top` would stand at 2.452 m with nothing under it across x. **Now idempotent**: a
  refused replay is reported stale, left out of the written set and deleted, so a second run has nothing to do. Ten
  such files were on disk, and the stage that exists to delete them was the one keeping them alive
- **A gravity repair could win by throwing a cabinet away.** `Gravity::resolve()` scored a candidate repair with
  `worstBearing()`, and a run standing on nothing reports a bearing of **1.0** — so "walked clean off its support"
  scored as perfect and took the slot. `carriedBearing()` exists for exactly this and reads `on` rather than the
  bearing, and the lookahead onto the tier above already used it; the row's own score did not, one line apart.
  Measured on the case that surfaced it: `2× gmss-nuke + 1× gmss-mid-bass` on two wall basses is carried at 8.5 %
  centred and the slide replaced it with an arrangement carrying a run on nothing. **Effectively inert on the shipped
  set** — 450 scenes and 313 band notes either way, with two refusal messages naming a different cabinet as the
  unsupported one

### Added

- **`slide_slack_m` on the `stack:` block**, in metres or `.inf` for "bounded only by the stage". Unstated it is
  `null`, which is the "may not move" answer a stack with a neighbour needs, so no hand-written scene changes. Written
  by `StackSceneWriter` whenever it is stated rather than only when it differs from a default, because both values are
  meaningful and there is no default to differ from
- **`StackSceneWriter::number()` writes an infinity as `.inf`.** `sprintf('%.4F', INF)` is the string `INF`, which YAML
  reads as an ordinary word and the reader then refuses as "expected a number"
- **A test that a written scene rebuilds to the sub wall its own header reports**, asserted on that number because it
  is the one both sides print in the same words and it separated the two answers by a factor of three. This is the
  third field to break the same invariant after the seating check, so the test is written against the invariant rather
  than against the field
- **`SceneStackCommand::NOTHING_TO_WRITE`**, exit code 2. Still non-zero, because a human who asked for a rig and got
  none needs the shell to say so, and distinct from `FAILURE` so a caller replaying a recorded command can tell a rig
  that is no longer one of the possibilities from a command that broke
- **A structural guard for the next one.** Every constructor parameter of `Stack` must appear as a key
  `Stack::fromReader()` accepts, derived by camelCase to snake_case rather than listed, and a stack that states
  everything must be written out stating everything and load again. Three releases have shipped this bug in three
  different fields and each was found by looking at a render
- **`build:all`'s regenerate stage is run by a test**, which is **TOOL-6** and the gap that let a real defect through:
  a removed pass wrote 141 stray scenes and `git status` found it rather than the suite, because every test checked
  the stage with `--dry-run` and none ran it. The replay is split out as `replayRecorded()` so the two deletions that
  follow it stay out of the test's way, and the test asserts which scenes exist, that each is byte-identical, and that
  the stage reports every one as written. The sibling test proves each recorded command is idempotent; this one proves
  the code that runs them does nothing else
- **`slideSlackM` is covered again**, which is **TOOL-8**, and at the `Gravity` level rather than through a swept rig —
  a rig can stop needing a feature, which is exactly how the old guard quietly stopped guarding anything. The case is
  measured: an Achenbach carried at **3.2 %** on a wall bass's edge, landing at **98.5 %** on the Flexy beside it after
  a 30 mm slide

### Known

- **A scene the sweep collapses as a duplicate still survives the replay**, which is what is left of TOOL-7 and is
  filed as **TOOL-15**. Dedup is a decision across a whole sweep and a replay is one file with nothing to compare
  itself against. Measured at **6 of 489**, every one confirmed a duplicate of a sibling that is also on disk, removed
  by hand so the tree matches a fresh `--force` sweep at 483
- **Sliding never changes the answer on a flat support.** Searched across every sub pair against every one, two and
  three cabinet support: not one case. Every case where the slide helps has a *stepped* support underneath, where a
  cabinet perches on the edge of the taller run. That is why the mechanism is invisible until a mixed row appears

## [0.83.0] - 2026-08-16

### Changed

- **THE FILL'S SEARCH KNOB IS A ROW WIDTH IN METRES**, divided by each cabinet's own width, which is **GEO-12**.
  `$perRow` was one integer applied to every device at once, so `perRow: 7` meant seven Flexys at 4.3 m *and* seven
  mid-bass at 8.5 m, and no setting of it expressed "as many of each as fit 4.40 m", which is 7 Flexys and 3 mid-bass.
  Nine of our ten cabinets are 0.45–0.66 m wide and `gmss-mid-bass` is 1.200 m, so a count stopped standing in for a
  width the day it arrived. The ladder is **derived from the cabinets** — the row widths the inventory can actually
  make — rather than written down as constants, which is the difference between it and the `WIDTH_LADDER_M` that 0.82.0
  deleted, and the unbounded step is always first so nothing is bounded by the search
- **A width does not contain a count, and both are walked.** Built as a width alone it lost **49 rigs**, every one
  refused on bearing rather than on the search running out: a count says "the same number of every type" where a width
  says "the same metres of every type", and neither reaches the other's arrangements. Proven rather than argued, since
  the count ladder alone reproduces the old solver's answer exactly and no width does. `RowBudget` walks both as a
  union rather than a product, and turns a count into a per-device width at the point of use, which is what lets one
  parameter carry both. **This is CVR-8's own lesson recurring inside the item written about CVR-8**
- **`perRowCap()` became `pyramidCeiling()`**, a width carrying the same `PYRAMID_SHOULDER` allowance
  `StackChecks::silhouetteProblem()` already permits. A bare "no wider than the row below" was measured wrong before
  and is measured wrong still: it splits six Achenbachs on six Flexys into two rows of three over a 27 mm shoulder the
  bearing rule allows four hundred of, and the 1.84 m row then cannot carry the tops. A flush wall is not a V
- **`lastRowCount()` is deleted.** It existed only because the pyramid cap was a count while everything around it was a
  width, and both are widths now

### Added

- **THE FILL CAN ASK WHETHER AN ARRANGEMENT SURVIVES BEING PLACED**, which is **GEO-11**'s stack-local half.
  `StackSolver::solve()` takes an optional predicate and `SceneCompiler::seatingCheck()` supplies one that expands the
  candidate's tiers and compiles them, so the answer comes from the same `orientationFor()` and `worldBox()` the
  finished scene uses rather than from a second opinion about where a cabinet's edge is. A candidate that overlaps now
  loses to the next one instead of the whole rig being discarded by whoever compiles it. Callers that cannot place a
  cabinet pass nothing and get the old behaviour
- **It found a rig that was being shipped with two cabinets inside each other.**
  `--from=gmss-* --max-width=3.70 --orientation=turned` answered with five rolled IQ subs slid along a 2.77 m support
  at 2.66 m of subs, and two of them interpenetrate once placed. Nothing had checked it, because a stated
  `--max-width` keeps an invocation out of the shipped-scene set the sweep covers. The check refuses exactly that
  candidate and the search falls to a clean 2.70 m
- **`StackSceneWriter::focusPoints()` and `::AIM`**, so the writer and the seating check read one definition of
  `far: 10 m / 1.8 m`. Two copies is how the two sides drift apart, and they did: judging the tops firing straight
  ahead where the file states `aim: far` moved a turned cabinet's outermost corner and refused overlaps that existed
  only inside the check, which brought the `all` inventory's turned rigs back at **0.660 m of subs** against 1.860 m
  once fixed

### Fixed

- **`scene:stack` solved with no seating check and then compiled the answer with one**, so the command wrote the
  arrangement its own solve liked and the compiler rebuilt a different one from the same file. That is GEO-11's own
  defect reappearing between two stages instead of three, inside the change meant to fix it. Both go through
  `SceneCompiler::seatingCheck()` now
- **The row budget was read per device inside one row.** A packed row full at 3.28 m for six IQ subs became roomy again
  the moment a 0.670 m wall bass was considered, because six of *those* are 4.12 m — so it pulled a wall bass into the
  bottom row and cost the GMSS pyramid its arrangement. Device-independent where a row holds several types,
  per-device where it holds one, since the support and pyramid ceilings are allowances scaled by the cabinet on the
  end of the row
- **The seating check ran on every candidate**, which is a compile per arrangement and stopped the sweep finishing at
  all. It is asked last, only of a candidate that would win, and **memoised by arrangement** — the ladder proposes the
  same rows from many budgets, so most steps are already judged. **Moving it out of the search was tried and is worse**:
  placing the answer, striking it out when it overlaps and re-running the fill took **58 minutes** against 23m40s,
  because a re-run is another walk of a fifty-step ladder and a fill costs far more than a compile
- **Three tests that pinned arithmetic rather than the rule they are named for.**
  `testTheAchenbachsStandOnTheFlexysRatherThanUnderThem` named the top row's cabinet count where the invariant is that
  no Flexy stands above an Achenbach, `testOwnerStillBindsWhenAnotherOptionCollapsesTheSweep` read owner binding off a
  pyramid rig that no longer has a good arrangement, and `testASoloStackSlidesARowRatherThanLosingTheRig` pinned the
  2.66 m answer that turns out to overlap

### Known

- **A full sweep now takes 20 minutes, and the ladder is why rather than the seating check.** Measured by
  short-circuiting the check off and re-running the same sweep: **20m14s without it**, against roughly 23 minutes
  with, so GEO-12's fifty-step ladder against the old dozen-step count is the whole of the increase and GEO-11's
  compile is two to four minutes of it. Runtime is explicitly not a constraint on this project, so this is recorded as
  a bill rather than a defect — and recorded at all so nobody optimises the wrong half. Filed as **TOOL-9**
- **`slideSlackM` has no dedicated test any more.** GEO-12's wider search solves the rig that guarded it with every row
  narrower than the one under it, so nothing slides. The feature is plainly still live — **273 generated scenes carry
  an overhang warning against 262 before** — which is exactly why the gap is easy to miss. Filed as **TOOL-8**
- **GEO-11's scene-level half is open**, which is aiming and cross-placement alignment. It is the genuinely circular
  part: aiming needs the scene's front face, the front face needs every placement, and every placement needs the solve

## [0.82.0] - 2026-08-16

### Changed

- **THE SUB HEIGHT BAND IS AN AIM, NOT A GATE.** Stated by the owner: the sub/top interface height is an optimisation
  problem rather than a hard constraint, so tops standing below or above head height is not a reason to refuse a rig or
  to call a scene invalid. It was a refusal on every invocation, and it was by a wide margin the largest single source
  of skipped candidates in the command — **551 of 1056**, 411 walls too short and 140 too tall, more than every geometry
  rule in the repository put together. `target_sub_height_m` is what the solve optimises, `interface_height_m` and
  `max_sub_height_m` are the band a miss is measured against, and everything about whether the rig *stands up* stays a
  gate: bearing, support, the pillar rule, the silhouette rules and interpenetration
- **The miss is reported in two places and needed no new surface for either.** `StackChecks::boundsProblems()` has
  produced both misses as warnings since 0.70.0 and `StackSceneWriter::header()` has always written every warning into
  the file, so the scene already said it and the gate was throwing the scene away anyway. What is new is a `noted` line
  on the terminal for whoever ran the sweep and is not going to open 396 files
- **`SceneStackCommand::heightCost()`** ranks the deal strategies on distance from the target plus `OUT_OF_BAND_PENALTY`
  times the part of the miss outside the band. Inert at the default band, since 2.5 m is the midpoint of 2–3 m and every
  in-band wall is already nearer the aim than every out-of-band one. Kept because without it a stated `--max-sub-height`
  could neither refuse nor rank, which is a bound that means nothing
- **AN UNSTATED WIDTH LIMITS NOTHING.** Also stated by the owner: how wide a generated scene comes out does not matter
  unless a parameter limiting the width is explicitly passed. `--max-width` had a 3.70 m default, so every generated
  scene was solved against a stage nobody had asked for. It has no default now, `Stack::$maxWidthM` reaches
  `StackSolver::ceilingFor()` as null, and **no generated scene carries a `max_width_m` or a `--max-width=` any more**.
  The widest row in the set went from 4.89 m to 9.376 m
- **`WIDTH_LADDER_M` and `buildInBand()` are deleted.** A width is either stated, in which case deviating from it is
  disobeying it, or absent, in which case there is nothing to deviate from. `isSweep()` now decides one question rather
  than two
- **EVERY AXIS IS IN THE SCENE NAME**, which renames the whole generated set. Three of them used to be left out at one
  value each — `pyramid`, `upright` and `alternate` — so that the ordinary rig kept a short id, and the price was a
  directory nobody could read: a gap in a name does not say which value was omitted, only that one was, so telling
  `stacked-gmss-1-center` from its six siblings meant knowing the defaults by heart. Now
  `stacked-gmss-1-pyramid-upright-alternate-center`, in the order the sweep nests the axes: rig, shape, orientation, mirror
  style, alignment. **One value is still absent because it does not exist**, rather than because it is a default: a
  null orientation means `--roll-mirror` named the cabinets outright, so that axis has no value. The mirror style is
  written even where nothing is rolled and it decides nothing — the sweep only pairs `upright` with `alternate`, but
  `--orientation=upright --mirror-style=centred` is honoured, and a name that dropped a vacuous style would give those
  two rigs the same file name
- **EVERY AXIS IS ALSO A FIXED-WIDTH COLUMN**, padded with dashes, so a directory listing lines up and a reader can scan
  one axis down the page instead of parsing each name. `SceneStackCommand::padded()` measures each axis from its own
  enum cases, so a new value widens its column by existing rather than by a number written somewhere. **The owner column
  is measured over every combination the specs allow rather than over the ones a given run walks**, which is the one
  trap here: padding to what the run happens to hold would have let `--owner=gmss` name a rig `stacked-gmss-1-…` where
  the full sweep names the identical rig `stacked-gmss------1-…`, one rig with two file names decided by an option meant
  to narrow the sweep rather than to rename it. The last field stays ragged, since nothing is lined up behind it
- **The sweep writes 396 scenes against 150**, of ~1200 candidates. 286 of them carry a band note — 211 walls short of
  the interface and 75 over the ceiling — each one written on the file it belongs to. `DEFAULT_MAX_SCENES` stays at 600
  and did not bind

### Added

- **`build:all` deletes generated scene files the sweep no longer writes**, with `--keep-stale` to switch it off. A
  replay *renames* rather than replaces — each scene's own recorded command rebuilds it under whatever name the current
  naming produces — so an axis that gains a value or a name that gains a field leaves the old file sitting there,
  compiling and rendering and describing a rig the sweep no longer offers. `SceneStackCommand::$written` reports the
  paths each run wrote and staleness is a set difference against that report, **never a timestamp**: two earlier
  attempts at the latter destroyed the scene set, because `filemtime()` is whole seconds where `microtime(true)` is
  fractional and a file written in the same second the run started reads as older than the run. Three guards — a run
  that wrote nothing deletes nothing, a scene with no `Regenerate it with:` line is not the pipeline's to delete, and
  only `scenes/generated/` is touched
- **The fill order is decided by frequency now, not by mass.** Stated by the owner: the lowest and most powerful subs
  belong as low and as central as the rig allows. `byFillOrder()` sorts on the driven low corner and keeps mass as the
  fallback, **and it compares frequencies only between two cabinets that both state one**. That guard is the whole
  difference between this and the version that broke: the earlier frequency-first sort read a missing passband as `INF`
  and fell back to `quantity × width`, so every cabinet without a passband sorted above every cabinet with one and the
  40 kg IQ subs went under the 220 kg wall basses. Nine of our ten speakers state no passband, so a rule that ranks on
  absence ranks almost everything on nothing. **No generated scene moves**, checked rather than assumed: our three
  cabinets with a passband come out in the same order either way, because the Achenbach reaches 35 Hz but is
  high-passed at 38 on purpose so that it sits above the Flexys, and GMSS's four state none and fall through to mass.
  The rest of that rule is **GEO-14**, where the owner has settled that the shapes keep priority and the acoustic order
  is an optimisation rather than a gate, and the missing power figure is **SPEC-13**

### Fixed

- Three tests that assumed the band collapsed the output to one scene. Two are narrowed to the single rig they are
  about rather than to whatever survived the gate, and the third replaces "every generated scene stands inside its own
  band" with the invariant that took its place: **every generated scene outside its band says so on its own file**
- **`testARecordedLineNamesTheOrientationRatherThanTheCabinets` no longer names its evidence by file name.** Naming two
  shipped files broke it three times for reasons that had nothing to do with orientation, once per change to the id
  shape. The rule it is about holds for every generated scene, so it is now asserted across the whole set, with a count
  of each mode so a sweep that stopped writing one fails here rather than passing an assertion loop that never ran
- **Two sweep tests that pinned the padding by accident.** `testTheMirrorStyleAxisIsSweptOnlyWhereSomethingIsRolled`
  matched `-turned-centred-` literally and `testTheBareCommandWritesScenesAcrossOwnersAndStackCounts` put a single dash
  between the owner label and the stack count. Neither is about column widths. The first collapses runs of dashes
  before asserting, so it stays about the pairing of two axes, and the second takes `-+`

### Known

- **The stale-scene deletion catches a rename, not a rig the sweep has stopped offering.** `regenerate()` replays every
  file that carries a recorded line, so every one of them lands in the written set by construction and can only look
  stale when its replay comes out under a different name. Measured while cleaning up after the rename: **18 files
  recording `--max-width=3.7` outlived the release that deleted the width ladder**, kept alive by the very stage meant
  to clean up after it, and they surfaced only when a fresh `scene:stack --force` was diffed against the directory. They
  are set aside rather than deleted, since they are the only artifacts of what the ladder produced and **GEO-12** exists
  to bring them back. Filed as **TOOL-7**. Until it lands, `git status` after a full sweep is the check that finds them
- **The width ladder was also a search dimension, and deleting it cost 18 scenes.** Filed as **GEO-12**. A stage width
  in metres caps each device's row count by that device's own cabinet width — 4.40 m deals 7 Flexys and 3 mid-bass —
  where `StackSolver`'s `$perRow` caps every device to the same integer, and our cabinets run 0.45 m to 1.20 m wide. So
  no row-count setting reproduces what a width produced. Measured against the 150: **18 scenes lost outright, 28 more
  pushed outside the band** (`stacked-all-2-free-mixed-column-center` went 2.381 → 4.173 m of subs), and 110 of the 396
  fully inside 2–3 m against 150 before. The fix is to make the search knob a width budget rather than a count, with the
  unbounded case always in the search so nothing is bounded by it

### Added

- `tools/tops-row-spread.php`, the evidence behind SYM-3. It lays the eight tops out across one envelope three ways —
  equal air with the ends pinned, equal pitch with the ends pinned, and what `Alignment`'s single scalar does today —
  so the figures in `TODO.md` can be re-measured rather than trusted

### Changed

- **Two P1 items stated by the owner, written down and not yet built.** **SWP-2** makes how separate the sound systems
  stand a swept axis at three values — each system its own stack, the subs per system with the tops shared, and
  everything pooled. Only the third is generated today, verified rather than assumed: 0 of the 150 scene files carry
  `--per-owner` and none states `--split`. **SWP-3** adds sweep configuration, which is every axis value switchable
  individually plus a system grouping that overrides `owner`, so `sdwa5` and `sepp` can be swept as one system. That
  second half is the answer CVR-3 was waiting for, and it is a grouping stated at invocation time rather than a
  `system:` field in the specs
- **CVR-8 is new and P1**, the same statement as CVR-7 about a different number. Stated by the owner: how wide a
  generated scene comes out does not matter unless a parameter limiting the width is explicitly passed. Today
  `--max-width` is read as `readFloat(…) ?? 3.70`, so every generated scene is built against a stage nobody asked for,
  and `buildInBand()` stops at the width ladder's 6.00 m end rather than widening until the rig fits. **It is built
  with CVR-7 rather than after it**: the width does two jobs, and removing only the gate leaves nothing deciding how
  wide a row wants to be, so the bottom row takes every cabinet of its type and the rig collapses to one row. Once the
  target height is what the solver optimises, a one-row wall loses on its own merits and the width becomes an output
- **SYM-3's 169 mm is no longer a rig-losing number**, which is what CVR-8 changes about it. It costs a wider scene
  rather than a refusal, and it still bites where a width *is* stated
- **TOOL-6 raised to P1**, once the cause of the stray-scene incident was confirmed as a code defect rather than
  anything about how the command was invoked. The removed `regenerateTurned()` pass compared against `--roll-mirror=`
  in a recorded command line after the format had moved to `--orientation=turned`, so it turned the turned rigs again.
  A stale string comparison is the whole of it, nothing environmental contributed, and `regenerate()` is still the one
  stage that writes into the repository while only ever being run with `--dry-run`. The TOOL section moves up the file
  with it, and TOOL-6 gained a section naming `prune()` as the obstacle
- **SYM-3 is no longer blocked on a decision**, and neither blocker turned out to be what it looked like. Spreading subs
  is SWP-2's shared tops row rather than a change to `Alignment`, since the ask needs sub *stacks* moved apart under one
  tops row and never needed a load-bearing tier stretched — so ALN-4's rule stands untouched and the two items are
  independent. The evenness rule is **equal pitch**, settled by the owner against a recommendation of equal air. Its
  cost is measured: a uniform pitch has to clear the widest adjacent pair, so the tightest row of our eight tops is
  **4.090 m against 3.921 m**, and those 169 mm decide which stages a rig fits at all

## [0.81.0] - 2026-08-15

### Added

- **`shape: v`**, SWP-1's last missing axis value. The sweep writes **150 scenes of ~1200 candidates**, 29 of them `v`. The pyramid's mirror at both places the pyramid acts: the fill puts
  the *narrowest* row-making type on the floor so the wall has somewhere to grow, and no row may be narrower than the
  one below it
- **`StackChecks::silhouetteProblem()`** — every shape rule in one place and **in metres**

### Changed

- **THE SHAPE RULES ARE WIDTHS, NOT CABINET COUNTS.** Stated by the owner of the gear, for the pyramid, the V and the
  tower alike. The pyramid was written as a count — no row holding more cabinets than the row below — and the premise
  that lets a count stand in for a width is false: nine of our ten cabinets are 0.45–0.66 m wide and `gmss-mid-bass` is
  1.200 m. The V made it obvious rather than causing it. Built on a count rule it produced **21 stacks that narrow
  against 8 that widen**, and `free` widened more often than the shape named after widening
- the pyramid's allowance is **a tenth of its outboard cabinet per side**, and both bounds on that number were measured.
  It cannot be zero — six Achenbachs on six Flexys stand 27 mm proud per side and are flush, and forbidding that splits
  them into two rows of three, whereupon the 1.84 m row cannot carry the tops and a 2-way is dropped. It cannot be a
  whole cabinet either, which was the first thing tried: `2× gmss-nuke + 1× gmss-mid-bass` stands 265 mm proud out of a
  590 mm cabinet and reads as a V
- the lift's pyramid guard compares widths too. It predicted a cabinet *count* through a `lastRowCount()` helper, and the
  support's width was already computed one line above for the bearing test, so one number now answers both
- `perRowCap()` keeps its count as a **hint** rather than a rule: a row of at most as many cabinets as the row below is
  nearly always what the width rule wants too, so the search finds it first instead of walking down to it. Where the two
  disagree the width rule wins, because it is the one that refuses
- **`DEFAULT_MAX_SCENES` raised 200 → 600**, the owner's call, sized for CVR-5 rather than for this release
- **the scene set is 148 → 150, and that small number is the honest one.** The V adds 29 rigs; the tightened pyramid
  refuses 79 candidates the count rule had allowed, and the V's own rule refuses 74. Thirty scenes written under the
  count rule are no longer produced and were deleted. The gain is not the count, it is that a `-v-` file now widens and
  a `-pyramid-` one does not
- **the test suite went from 9 minutes to 24.** Recorded because it is a fourfold jump from one change, and unlike the
  earlier growth it is not that there is more to check. The width rules refuse arrangements *mid-search*, so the solver
  walks far more of the space before it settles. Runtime is not a constraint on this project, so nothing was done about
  it, but the next axis should expect the same multiplier

- **`TODO.md` is sorted by priority throughout**, rows inside each table and the group sections by their own top row, so
  the first table in the file holds the highest-priority item in the file. A `P4` tier was added for work that is wanted
  but that nothing waits on. Restated by the owner in the same pass: **CVR-7 is new and says the sub/top interface height
  is an optimisation problem rather than a hard constraint**, so tops below or above head height stop being a reason to
  refuse a rig. That is not implemented here, only written down, and it is what CVR-1 dropping to P4 follows from. CVR-5,
  SYM-3 and GEO-9 are P1. Two pieces of gear joined the file with no specs yet, two 3 × 3 m tents and five Euro pallets

### Fixed

- three tests that asserted on what the count rule produced. `testASoloStackSlidesARowRatherThanLosingTheRig` named the
  `2× gmss-nuke + 1× gmss-mid-bass` row, which is the exact 265 mm step-out the width rule now refuses, so it is
  re-anchored on a case that still exercises the slide and the case was **verified by taking the slack away**: with
  `slideSlackM` forced to null the same invocation writes nothing and reports `3.340 m against the 3.000 m ceiling`.
  The other two are a shape count and a scene file that the count rule used to produce

## [0.80.0] - 2026-08-15

### Changed

- **`SceneStackCommand` split into three, 1998 lines down to 1635.** TOOL-5. Size was the symptom; the reason is that
  two clusters in it never touched the solve, so they could not be reasoned about or tested without a rig, an inventory
  and a console. **No behaviour change** — the scene set is byte-identical and the suite is unchanged
- **`App\Scene\SweepAxes`** takes the five axes that have something to decide: `modes()`, `shapes()`, `mirrorStyles()`,
  `orientations()`, the orientation/mirror `pairs()` and `ownerCombinations()` with its `labelFor()`. An axis is a fact
  about what the sweep covers, and now it reads as one
- **errors come back as a string rather than being printed.** Each parser returns its values or the message naming what
  was misspelled and what was allowed, and the command decides that an error is red text on stderr. That is the whole of
  the seam. The four near-identical parsers collapse into one `of()` that names every allowed value, because a refusal
  saying only "unknown value" sends somebody to the source to find out what is allowed
- **`App\Scene\PlacementChecks`** takes `floating()` and `coveredFraction()`. The sibling of `StackChecks`, and the
  distinction is the data rather than the severity: `StackChecks` reads *tiers*, where "the row below" is a thing you can
  point at, and this reads `PlacedDevice`s, which are cabinets at world coordinates with no memory of which row they came
  from. Everything the compiler does between those two representations is what these checks exist to catch
- the contact tolerance is defined once, in `PlacementChecks`, and the command takes it from there. Two copies of a
  tolerance drift the first time one of them is tuned

## [0.79.0] - 2026-08-15

### Added

- **`target_sub_height_m` and `--target-sub-height`, defaulting to 2.5 m** — the sub/top transition a rig *aims at*, as
  opposed to the two bounds it has to stay between. Stated by the owner of the gear. A bound says which arrangements are
  allowed and a target says which of them is best, and the solver had no answer to the second question: it kept the
  **shortest** arrangement that cleared the interface, which parked the transition just over 2.0 m wherever it could.
  Across the sweep the same rigs now sit **0.189 m from the aim on average against 0.222 m**
- two rules that keep an aim from doing damage, both found by measuring rather than by reasoning. **The ceiling binds
  before the target does**, so a preference can never reach past a bound to pick an illegal arrangement — inert at the
  default, since 2.5 m is the band's midpoint, and live the moment somebody states an aim off it. **With nothing legal,
  the aim falls back to the ceiling**: five Flexys and three Achenbachs under a 1.0 m ceiling can build 1.363 m or
  2.126 m, and ranking on the target alone answered with the rig that misses by 1126 mm over the one that misses by 363

### Changed

- `build()` ranks attempts by the **worst stack's** distance from the aim rather than by the tallest stack's height.
  Scoring the tallest was right while the tie-break was "shorter wins"; with a target it let an attempt win because its
  tall stack sat at 2.48 m while its other stack dropped to 1.773 m and the whole rig was then refused
- the ceiling warning says "the nearest the target that every tier is still carried at" rather than "the shortest
  arrangement in which every tier is still carried", because that is the rule the solver now follows
- **28 generated scenes changed arrangement** and four collapsed into their `alternate` sibling, so the set is 148 rather
  than 149. No rig was lost — every one of the four still ships under the sibling's name — and three new rigs appeared.
  Refusals moved between families rather than in total: 267 too-short became 258, 38 too-high became 54
- three tests pinned the solver's arithmetic where they meant to pin its shape, and the aim moved that arithmetic. Five
  Flexys under a ceiling now come out 3 + 2 rather than one row of five, and the GMSS pyramid stands on four IQ subs
  rather than six. Both assert the ordering — Achenbachs above Flexys, IQ subs on the floor — which is what they are
  about and what did not change

## [0.78.0] - 2026-08-15

### Added

- **The inventory axis is every non-empty combination of owners**, which is SWP-1's step 4 and CVR-3. Suite: 952 tests,
  16 751 assertions, 9 minutes 11 seconds. The sweep goes from
  61 scenes of 426 candidates to **149 of 804**, and **not one previously shipped scene changed by a byte**
- **the borrowed-gear pairs are where the output is**, which the TODO had guessed wrong. It read "this axis does not pay
  off on its own", on the grounds that `sepp` alone cannot produce anything — still true, `sepp` writes 0. What it missed
  is the middle: `sdwa5-sepp` writes **50 scenes**, more than any single owner and more than `all`. Six Achenbachs cannot
  fill a 2 m wall alone and are excellent under somebody else's tops, which is exactly the borrowing this repository
  supports on purpose. `all` writes fewer because 41 cabinets in one rig is two sound systems, where 25 is a gig
- `--owner=NAME`, repeatable, which **narrows the inventory axis without collapsing the sweep**. It is the one narrowing
  option here that is not a rig somebody named: `--from` means "this rig at this width", where `--owner` still asks the
  sweep to walk the stack counts, shapes, orientations and the width ladder. An unknown owner is refused and the owners
  there are named

### Changed

- **`DEFAULT_MAX_SCENES` raised 80 → 200**, deliberately, which is what the fuse is for. The owner combinations write 149
  and 80 refused the run outright — the correct behaviour, and the point at which somebody looks at the number and
  decides it is the output they meant. The next raise is CVR-5, whose `impossible` half would turn today's 655 refusals
  into written scenes
- the over-the-limit message names options that exist. It advised `--align/--subs`, and `--subs` has never been an option
  of this command
- **`--owner` binds on the narrow path too**, so `--owner=gmss --stacks=1` builds GMSS's gear rather than everything.
  Silently ignoring a stated option is the failure mode this command avoids everywhere else. Naming both `--owner` and
  `--from` is refused outright, since they say the same thing at different resolutions and no reading of both is anything
  but a guess
- three of `SceneStackCommandTest`'s slow cases name one alignment and one shape. They assert what the orientation and
  mirror axes offer, which neither alignment nor shape changes, so the bare sweep in them was the same assertion at eight
  times the runtime. `testTheBareCommandWritesScenesAcrossOwnersAndStackCounts` is where that full run is paid for once

### Removed

- **`build:all`'s second, hard-coded orientation axis.** A `regenerateTurned()` pass re-ran every generated scene's
  recorded command with `--roll-mirror=flexy-folded-horn-hybrid --roll-mirror=skram` under an `-turned` id — which is
  where the ten `-turned-` scenes deleted in 0.76.0 came from, and it was exactly the second copy of the command list
  that stage's own docblock argues against. The orientation axis supersedes it on every count: every sub rather than two
  named cabinets, three modes and seven pairs rather than one, and each choice recorded in the file's own line

### Fixed

- **`build:all` wrote 141 stray scenes and silently rewrote two committed ones.** The removed pass detected an
  already-turned rig by looking for `--roll-mirror=` in the recorded command, and a turned rig now records
  `--orientation=turned` — so it turned the turned rigs again and produced ids like
  `stacked-sdwa5-sepp-2-turned-turned-column-center`. 149 scenes in, 290 out
- the gap that hid it: **nothing ever ran that stage**, only `--dry-run`. A new
  `testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet` pins the contract it rests on — replaying all 149
  recorded commands rewrites exactly those 149 files, byte for byte — in 18 seconds. Covering the stage itself is TOOL-6,
  since it calls `prune()` and a test that fails midway could delete real renders

## [0.77.0] - 2026-08-14

### Added

- **The orientation axis — which cabinets lie on their sides — as `StackOrientation` and `--orientation`.** SWP-1's step
  2, and the largest single gain measured anywhere in this project: the bare sweep goes from **11 scenes of 66 candidates
  to 61 scenes of 426**. Three-stack rigs are generated for the first time, and `stacked-all-2-turned-centred-stereo`
  stands **39 cabinets** where the upright rig of the same gear stands 38. A rolled sub is wider and shorter, and both
  halves pay — a wider row fills the stage in fewer cabinets, a shorter row lands inside the 2–3 m sub height band
- three modes. `upright` rolls nothing, `turned` rolls every sub whatever it measures, and `mixed` rolls a sub only where
  rolling makes it **wider and shorter**. `mixed` is read off the specs rather than stated, so it needs nothing measured:
  it leaves `gmss-mid-bass` standing at 1.200 × 0.500, the one sub already wider than tall, where rolling would make the
  wall *taller*; and it leaves the 0.600 × 0.600 `achenbach-18` standing, where rolling is geometrically nothing
- **tops are never rolled at any setting.** Settled by the owner of the gear rather than derived, and the reason is
  acoustic: a top's horn throws its pattern in one orientation and rolling the cabinet rolls the pattern with it. The
  measurement agrees — rolling everything wrote 18 scenes against 24 for the subs alone — but the numbers are not why
- the orientation and the mirror style are swept **as seven pairs rather than as 3 × 3**, because the style only decides
  what a *rolled* row does with the odd cabinet it cannot halve and is vacuous otherwise. Independently multiplied, a
  third of every candidate would be a duplicate by construction. A pair that rolls nothing in *this* inventory is dropped
  as well, which keeps the names honest: a `-turned-` file always has something turned in it

### Changed

- `--roll-mirror` **switches the axis off**, so every hand invocation that names cabinets works exactly as it did. The two
  options answer the same question at different resolutions, and a line saying `--roll-mirror=skram` means those cabinets
  rather than "sweep three modes and ignore what I said"
- a recorded command line carries `--orientation=MODE` rather than the cabinets it resolved to, so a replay stays correct
  when a new sub is measured or a wrong dimension is corrected. **Every one of the 11 previously shipped scenes changed
  in exactly that one comment line and nothing else**, and all 11 ids are still written — the axis is purely additive
- `readMirrorStyles()` no longer decides its own default. It returns the styles somebody named, and the new
  `orientationPairs()` decides what to sweep, since which cabinets roll is exactly what the orientation axis answers
- six tests now pin `--orientation=upright` where they name a rig and count its scenes, exactly as they already pin
  `--shape=pyramid`. An axis that grows makes "1 scene" arithmetically wrong rather than regressed
- `testEveryGeneratedSceneCompilesAndPlacesEveryCabinet` asserts **23 cabinets less whatever the file states it left
  out**, rather than a flat 23. `mixed` rolls the Flexys, so a sub row is 3.112 m of four cabinets instead of 3.646 m of
  six, the wall tapers faster and the two 2-ways have nothing to stand on — 21 carried, both missing ones named. A silent
  drop still fails, which is the defect the test exists for

### Fixed

- **a latent bug in `testASingleStackIsNeverMirrored`**, found by the new axis rather than caused by it. It asserted the
  output does not contain `mirror:`, and a rolled cabinet states `roll_mirror: 90.0` — the same seven characters. The
  loose form passed only for as long as nothing was ever rolled and would have failed on a perfectly correct stack the
  moment one was. It asserts `mirror: true` now, which is the key it always meant

### Notes

- the suite is **770 tests and 11 229 assertions in 9 minutes 34 seconds**, against 662 tests in 88 seconds before. A
  candidate is a solve plus a compile plus an interpenetration sweep, there are now 426 of them, and `ShippedScenesTest`
  compiles all 61 written scenes on top. Runtime is explicitly not a constraint on this project, so this is recorded as a
  fact rather than as a problem

## [0.76.0] - 2026-08-14

### Removed

- **The ten hand-invoked `-turned-` scenes**, settled by the user: old autogenerated scenes are not wanted. They were
  exactly the set the current sweep does not produce, every one carried an explicit `--roll-mirror` in its recorded
  command line, and nothing referenced them but one historical aside in a docblock. `scenes/generated/` now holds
  precisely what `scene:stack` writes — the invariant it was always meant to have. The suite drops from 682 tests to 662,
  since `ShippedScenesTest` generates its cases from the directory
- with them goes **SWP-1's id collision**, which was the last thing blocking the orientation axis. Four of those files
  would have been silently rewritten by `--force`, because a hand invocation rolling only `flexy` and `skram` produces the
  same id as an orientation mode rolling every sub. There is nothing left to collide with

### Changed

- the rule that fell out is recorded in SWP-1: **a file in `scenes/generated/` that the sweep does not produce is stale,
  not precious**, and `comm -23` between the directory listing and the sweep's ids is the whole check

## [0.75.2] - 2026-08-14

### Changed

- **Runtime is recorded as not being a constraint on this project**, stated by the user outright, and it is written into
  `TODO.md`'s reading rules rather than buried in one item. A sweep that takes ten minutes and a test suite that takes
  longer are both acceptable if they produce more correct scenes, so no estimate, design or prioritisation may trade
  coverage away for speed
- SWP-1's step 3 goes back to **raising `DEFAULT_MAX_SCENES`**, which is the fuse and nothing else. It refuses rather than
  truncating, so it has to be raised deliberately once a step writes more than 80: step 2 alone writes 61 and fits, and
  the `impossible` axis turns 55 refusals into written scenes and will not
- **the reason step 2 was reverted is corrected.** It was recorded as runtime; the honest reason is **8 unread test
  failures**. The suite ran out of time before their causes could be read, so the axis was reverted rather than committed
  on a guess. The 5 minute 37 second test class and the 10-minute suite are now recorded as facts about the suite rather
  than as blockers
- SWP-1 gains a note on how to rebuild step 2, since it is mechanical and was measured working: one enum, seven pairs
  built once in `execute()`, an `--orientation` option, and a nullable `?StackOrientation` threaded through five
  signatures. `commandLine()` records `--orientation=MODE` rather than the resolved cabinet list, so a replay stays
  correct when a cabinet is measured or added

## [0.75.1] - 2026-08-14

### Changed

- **SWP-1 gains a sixth axis, `(possible, impossible)`**, where an impossible rig is emitted anyway with its offending
  cabinets painted red rather than refused. That is CVR-5, and as an axis it is the complement of `possible` rather than a
  variant of it, so it doubles the candidate count to **2646** and turns today's 55 refusals into 55 written scenes
- **Step 2 of SWP-1 — the folded orientation/mirror axis — was implemented, measured and reverted, and the measurement is
  the point.** The fold works exactly as specified: `66 × 7 = 462` candidates writing **61 scenes** against 11. The
  `-turned-turned-` doubled suffix from the first prototype turned out to be an artefact of that prototype, not of the
  design. `DEFAULT_MAX_SCENES = 80` is *not* the constraint, since 61 fits under it
- **the constraint is runtime, and it is severe enough to block the axis.** `SceneStackCommandTest` alone went from
  seconds to **5 minutes 37 seconds**, and the full suite passed 10 minutes without finishing. A candidate costs a solve
  plus a compile plus an interpenetration sweep, and narrowing the two incidental bare sweeps to `--orientation=upright`
  was nowhere near enough. At 2646 candidates it is four times worse again. SWP-1's step 3 is rewritten from "raise the
  fuse" to "solve the runtime", at ca. 3h, with three unmeasured options recorded: cache the solve across candidates
  sharing a rig, have `ShippedScenesTest` compile a sample, or split the exhaustive sweep tests into an excluded slow
  group
- eight test failures were left unread when the suite ran out of time. Recorded as unread rather than assumed trivial,
  though they are most likely assertions pinning scene ids or counts that the new axis legitimately changes

## [0.75.0] - 2026-08-14

### Added

- **`MirrorStyle::Column`, a third answer for the odd cabinet in a rolled row.** `alternate` swaps its side each row, so
  the stack balances and the seam between the two mirrored halves zig-zags; `column` sends it to the same side every row,
  so the seam runs straight up the wall and the stack is lopsided by one cabinet instead. Same heights as `alternate`, so
  unlike `centred` it introduces no step and nothing new can fail on it. It is two lines — `Tier::mirrored()` already
  derives the midpoint from the row parity, and this is that line with the row ignored
- SWP-1's implementation order, so the five remaining steps each end green and measured rather than being one change

### Changed

- **`MirrorStyle::Upright` is renamed to `Centred`, and the rename is not cosmetic.** The sweep is gaining an
  *orientation* axis whose own values include `upright`, meaning nothing is rolled, so a scene called
  `stacked-gmss-1-turned-upright-center` would read as a contradiction — "every sub turned, nothing rolled". `centred`
  also describes what the style does. **No file on disk is renamed**: nothing rolls in the default sweep, so only
  `alternate` is ever written and no committed scene carries `-upright-`
- **SWP-1 folds orientation and mirror style into one axis of seven values** rather than two axes with a rule saying the
  second is meaningless under the first one's default. `Tier::mirrored()` acts only on rolled segments, so the styles are
  byte-identical when nothing is rolled; enumerating `upright` + `turned`×3 + `mixed`×3 makes that unrepresentable
  instead of guarded, which is the bug fixed in 0.73.0. The two stay separate *options*, since a hand invocation wants
  `--orientation=turned` without an opinion on the odd cabinet. The candidate count is unchanged at 1323

## [0.74.7] - 2026-08-14

### Added

- **`TODO.md` gains an `SWP` group and `SWP-1`, which states the target sweep as one cross product** rather than leaving
  it implied across six places: `(1/2/3 stacks) × (every non-empty combination of sdwa5, gmss, sepp) × (center, block,
  stereo) × (pyramid, free, V) × (upright, turned, mixed) × (alternate, centred, column)`. That is **1323 candidates**
  against today's 66, since the mirror axis only bites where something is rolled and so multiplies with orientation as
  `upright(1) + turned(3) + mixed(3)`
- the three values that do not exist yet are specified: **`V` as a stated shape** (`free` is not it — `free` *permits* a
  rig to widen going up without asking for it), **`column` as a third mirror style** with **`upright` renamed to
  `centred`**, and **all seven owner combinations** in place of today's three fixed groups
- two load-bearing consequences are recorded rather than discovered later. `DEFAULT_MAX_SCENES` is 80 and will refuse the
  sweep outright, since an orientation-axis prototype alone wrote **49** scenes from 318 candidates. And runtime: 318
  candidates already take a couple of minutes, so 1323 is on the order of ten, with `ShippedScenesTest` compiling every
  written scene on top

### Changed

- CVR-3 is rewritten. It described narrowing the default `--from` to one sound system; what SWP-1 needs is the inventory
  axis widened to all seven owner combinations. `sepp` alone still produces nothing until CVR-1 lets a top stand on
  something that is not a cabinet
- an orientation-axis prototype was built, measured and reverted, and the trap it found is recorded in SWP-1: its scene
  ids **collided with four committed files** (`stacked-sdwa5-1-turned-center` and three siblings), whose committed
  versions came from a hand invocation rolling only `flexy` and `skram`. An orientation mode rolling every sub produces
  the same id and a different rig, so `--force` rewrites them silently. It also emitted a doubled suffix,
  `stacked-sdwa5-1-turned-turned-center`, so the id builder needs a test across every axis rather than a spot check

## [0.74.6] - 2026-08-14

### Changed

- **GEO-6's premise is refuted and it becomes the highest-value open item measured in this project.** It said the whole
  inventory cannot be turned at once. It can, and turning is the largest scene gain found anywhere. Measured on the same
  66-candidate sweep, varying only what `--roll-mirror` names: upright — **which is what the default sweep does** — writes
  **11**, flexy plus skram turned writes 15, **every sub turned writes 24**, and every cabinet turned writes 18
- turning every sub **more than doubles the output** and produces `stacked-all-3-*` rigs, the first three-stack rigs ever
  generated. INFO-1 records that "no three-stack rig is generated at present; the band refuses them all" — a turned sub
  wall is shorter, so it lands in the band
- turning *everything* is worse than turning the subs (18 against 24) for a physical rather than geometric reason: it rolls
  the **tops**, 194 of them across the written scenes. A top's horn is designed for one orientation and rolling it 90°
  rolls its dispersion with it, so those rigs are geometrically valid and acoustically wrong. Low frequency is
  near-omnidirectional, which is why the same objection does not apply to a sub
- **so the real gap is that the sweep has no orientation axis at all**, and GEO-6 is raised to P1 and marked `decision`:
  which cabinets may legitimately be laid on their side is physical knowledge about this gear, and this repository
  deliberately refuses to guess it. GEO-10 is raised to P1 with it, since "subs turned, tops upright" *is* a mixed
  orientation and carries most of the gain

## [0.74.5] - 2026-08-14

### Changed

- **The pyramid was reimplemented as a pure width rule, twice, and both versions measured worse than the count rule they
  replace.** `perRowCap()` was deleted and the shape expressed in `ceilingFor()` instead, once bounded by the row below
  and once by the bottom row. On the 66-candidate sweep: the count rule writes **11 scenes with 0 interpenetration**,
  bounding by the row below writes 7 with 2 interpenetrations, and bounding by the base writes 8 with 3. Reverted, and
  `TODO.md` records the numbers
- a tolerance is **not** the lever, which the previous entry guessed it was. Tried at 10 mm — the checker's own
  `OVERHANG_TOLERANCE_M`, "what the rubber feet and the working gaps absorb" — and at 30 mm, both giving 7 scenes,
  identical to no tolerance at all
- **why both fail is structural.** A width bound makes rows narrower, narrower rows make more of them, and a wall of many
  thin rows is a staircase; `Gravity` splits each row into runs at the heights that staircase presents and those runs end
  up inside each other, at **127 mm** on `stacked-all-2-center`. That is the same cascade three attempts at splitting the
  tops row hit at 203 mm. Bounding by the base cannot cascade and still hits it, because the inventory forces thin rows
  whatever the bound permits
- so this is the **third** instance of one architectural split, after GEO-2 and GEO-4: the fill decides row widths,
  `Gravity` decides where cabinets land, the compiler decides final x, and no stage sees the next one's answer. GEO-5 is
  re-estimated at ca. 6h, dropped to P3 and now depends on that reconciliation. The count rule stays, with its false
  premise and its two legal-but-V rows documented

## [0.74.4] - 2026-08-14

### Fixed

- **A lift could still step a pyramid outward, because it was the one row-building path the pyramid cap never reached.**
  Every other path asks `perRowCap()` how many cabinets the row below holds; a lift cannot, since it reserves its flanks
  before a single tier exists. Enforcing it at emission is impossible too — by then the source's own rows are built, so
  handing surplus cabinets back would strand them with nowhere to go. `liftPairs()` now *predicts* the cap the same way
  it already predicts the support's width, through the new `lastRowCount()`. **Inert on the current inventory**: 11
  scenes, no scene file changed, 5 overhang warnings before and after, so it guards a shape that can occur rather than
  fixing one that does

### Changed

- **`perRowCap()`'s stated premise is false and `TODO.md` records it.** It explains at length why the pyramid cap counts
  cabinets rather than comparing widths, ending "the cabinets are all 0.45–0.66 m wide and a row of `n` is about `n`
  cabinets across whatever they are". Nine of the ten are in that range and **`gmss-mid-bass` is 1.200 m**, nearly double
  the widest of the others, so one of them in a row breaks the equivalence between "no more cabinets" and "no wider" and
  the V returns. Two of the five remaining overhang warnings are exactly that, both legal under the bearing rule and both
  reading as a V; the other three are the tops row, which `topRow()` deliberately never caps
- GEO-5 is therefore reduced to a tolerance question and marked `decision`, since both extremes are already measured as
  wrong: a strict width cap forbids a 27 mm shoulder the bearing rule allows four hundred of, and no width bound at all
  is what ships today

## [0.74.3] - 2026-08-14

### Fixed

- **`Gravity`'s repair scoring could not see a cabinet hanging in mid-air.** `landsOn()` answers `bearing => 1.0` for a
  run with no support at all, which is correct where it is used — the bottom tier stands on the floor and the floor
  carries anything — and exactly wrong for any tier above the first, where nothing underneath means the cabinet falls.
  So `worstBearing()` read an arrangement that had abandoned cabinets to the air as *perfectly carried*. The new
  `carriedBearing()` reads `on` rather than the bearing

### Added

- **a one-tier lookahead in `Gravity`**, so a repair is scored on the row it fixes *and* on the tier that row carries.
  Judging a repair on its own bearing alone is a local optimum the tier above pays for: a row slides for its own sake
  and walks out from under what stands on it, and because the pass runs bottom-up nothing has looked at that tier yet.
  The gate is deliberately still the row's own bearing, so repairs fire exactly where they always did; only which
  repair wins now accounts for the row above
- a `GravityTest` case pinning the `bearing => 1.0` trap directly, since it is the sort of thing that gets
  reintroduced by anyone writing a new scoring function

### Changed

- **GEO-4's diagnosis is corrected a second time, and the lookahead is why.** The previous entry said the slide needed a
  bound that kept the row under what stands on it. That was built, and the trade did not move: **10 scenes against 11**.
  The lookahead models the tier above as its *nominal seats*, and the real tops row is moved downstream by
  `throwFirst()`, the `clear_of` fill solves and `spreadApart()`, all in the compiler after gravity has finished. So a
  slide that is safe against nominal tops starves the tops that actually get built
- that is the same architectural split GEO-2 turned out to be — gravity decides support before the compiler decides
  final x — so GEO-4 is re-estimated at ca. 6h and now depends on the two-pass compile rather than on anything further
  inside `Gravity`. The multi-stack slide stays off, and the lookahead is kept as a correct and inert prerequisite:
  11 scenes, no scene file changed, 680 tests green

## [0.74.2] - 2026-08-14

### Changed

- **GEO-4's diagnosis is corrected, and the old one was wrong.** It held that multi-stack row sliding was blocked on the
  tops row learning to stand on its support's plateau. `Gravity::reseat()` settled that in 0.74.1, so the bound was
  measured again — and the trade did not move. Switching it on takes the sweep from **11 scenes to 10**: it gains
  `stacked-gmss-2-center` and clears both `LEFT OUT` cabinets, and it loses **both** `stacked-all-2-center` and
  `stacked-all-2-stereo` while doubling "nothing under it at all" from 3 to 6
- the real gap is that **a slide is bounded by the neighbouring stack and by nothing above it.** `max(0.0, clearance / 2
  - gap)` is correct as far as it goes and no interpenetration appears with it, but a sub row slides for its own bearing
  and walks out from under the tier it carries, which no bound expressed in stack clearance can see. The bound has to be
  the intersection of room beside the stack *and* staying under what stands on the row, which is a change in
  `Gravity::slidSeats()` rather than in the line that enables it. GEO-4 is re-estimated at ca. 3h, dropped to P2 and is
  no longer blocked by anything

## [0.74.1] - 2026-08-14

### Fixed

- **A stereo tops row was spread across its support without being asked again what it stands on**, which is the second
  half of the same defect 0.74.0 fixed for fills. `Stack::spreadApart()` walks the runs out across the *whole* support
  span, and that span routinely straddles supports at different heights, so a run pushed outboard kept the height of the
  support it had left: `stacked-all-2-stereo` hung a tecnare at 2.347 m and `stacked-all-3-free-stereo` a turbo top at
  4.668 m. No tier check could see it, because the bearings had been computed before the move
- **`stacked-all-2-stereo` is now written**, taking the sweep from 10 scenes to **11**, with no existing scene changed.
  Floating refusals fall from 3 to **1**, and that one is `stacked-all-1-stereo` — the rig that cannot reach the height
  band at any width on the ladder, so it is a correct refusal rather than a defect. GEO-2 is closed

### Added

- **`Gravity::reseat()`** and **`Gravity::topFacesOf()`**, public so a caller that moves finished runs can re-derive
  what they land on. `Gravity::resolve()` never had this bug because its own repairs hand back *seats* and go through
  the fill again; this is that discipline made available to callers holding runs. `reseat()` recomputes everything from
  `lo`/`hi`, so it is idempotent and safe on runs that did not move
- a `GravityTest` case on a deliberately stepped fixture — a 1.400 m wall bass row beside a 0.500 m mid bass row — that
  shoves a seated run over the short support and asserts it is reseated onto it, plus that reseating is idempotent

## [0.74.0] - 2026-08-14

### Added

- **`align.clear_of`** — an earlier placement whose *cabinets* these must not come within `inset_m` of, measured on the
  shells by the new `Interpenetration::gapBetween()`. The companion to `align.outside` and deliberately not a
  replacement for it: `outside` collapses its reference to an x span and gets past the whole of it, which is what a
  hand-written envelope needs, while `clear_of` only keeps off the cabinets themselves, which is what a fill beside the
  long throw needs

### Fixed

- **A near-field fill was pushed far enough to leave the run that had given it its height.** Fills are solved against the
  nearest long-throw run, and that solve used `align.outside`, which measures the *span* a reference covers. An aimed
  cabinet's span runs far wider than its body — a 2-way yawed 29.4° presents 0.8523 m on a 0.5 m cabinet — and down a
  chain of fills it compounds: a GMSS turbo top was driven **514 mm** off its seat, keeping the 4.668 m it had been given
  while ending up over a support 228 mm lower, so it hung in the air and the sweep refused the rig. Fills now use
  `clear_of`, which lands on exactly the stated working gap. **Floating refusals fall from 6 to 3**, the support family
  from 11 to 8, with the same 10 scenes written
- the fill now sits **12 mm closer** than before and still keeps its full 20 mm, asserted on the shells rather than
  inferred. `outside` had been putting 20 mm between the *extreme x points* of two rotated boxes, and since the cabinets
  nest in y the real separation was larger than asked for
- `Alignment`'s two rewriting helpers used **positional** constructor arguments, so adding a parameter ahead of `insetM`
  silently slid the inset into the new field and `side` into the inset. Every alignment test failed at once, which is the
  good case; both now pass by name

## [0.73.3] - 2026-08-14

### Changed

- **GEO-2 is diagnosed. A near-field fill is moved sideways after gravity has decided how high it sits, and it keeps the
  old height.** Neither `floating()` nor the tops row's width is at fault, and `Gravity` is not wrong either. In
  `stacked-all-2-free-center`, `Gravity` seats the outer turbo-top at x 0.7732 resting on the IQ sub run with **78 %
  bearing**, cantilevering 78 mm over a 228 mm step. The compiled scene then places it at x 0.2589, **514 mm to the
  left**, over the achenbach alone, while it still carries the IQ subs' 4.668 m. So it hangs 228 mm in the air
- **which cabinets move says why.** The long throw does not move at all and every near-field fill does: −514 mm, −146 mm
  and +324 mm against their seats, with the centred 2× tecnare run unmoved. `nearFieldFills()` calls every top narrower
  than the widest a fill, and a fill is placed with `align.outside` against the long throw, solving its x to clear that
  cabinet's **aimed** footprint — and the yaws reach −53.6°. `Stack::spreadApart()` is not involved, since it fires only
  for `stereo` and this is `center`. So **x comes from an alignment solve, z comes from a gravity seat, and nothing
  reconciles them**
- two measurement traps are cleared and recorded. The `z` gate is innocent, since `CONTACT_TOLERANCE_M` is 0.001 m and the
  measured `dz` was exactly `0.0000`. And comparing *box* centres rather than positions invents displacements that do not
  exist, because a yawed trapezoid's box inflates asymmetrically — the same two turbo-tops show boxes 0.6125 m and
  0.8263 m wide on a 0.45 m cabinet. Compare `liftedPosition()`, never `worldBox()`

## [0.73.2] - 2026-08-14

### Changed

- GEO-2's second candidate cause is downgraded before anyone spends time on it. The `z` gate in
  `SceneStackCommand::coveredFraction()` only considers cabinets whose box top sits within `CONTACT_TOLERANCE_M` of the
  cabinet's box bottom, which would drop every supporter if a top landed a hair off a stepped support.
  **`CONTACT_TOLERANCE_M` is 0.001 m**, a millimetre rather than a float epsilon, and `Gravity` derives every `z` by
  summing exact cabinet heights, so the accumulated error is orders of magnitude inside it. The likely answer is
  therefore that the float is **genuine** and the `all-2`/`all-3` tops row really does reach past the stack under it,
  the same family as `all-1` and merely less extreme. If the numbers confirm that, the fix is not in the checker and the
  item becomes a question about distributing the tops across stacks

## [0.73.1] - 2026-08-14

### Changed

- **12 of GEO-2's 22 refusals are the generator working correctly, not defects, and `TODO.md` now says so.** `all-1`
  puts 41 cabinets in one stack and cannot reach the 2–3 m sub height band at any width on the
  `WIDTH_LADDER_M` — 8.676 m at a 2.00 m stage down to 3.840 m at 6.00 m, and that best case is still 840 mm over the
  ceiling. Declining an impossible rig is the whole promise `scene:stack` makes, that a generator emitting a scene the
  compiler rejects is worse than no generator, so those 12 want no geometry and no code
- GEO-2 is therefore narrowed to the **10 `all-2` and `all-3` refusals**, and re-prioritised to P1 at ca. 2h with the
  starting point written down. All 10 are raised by `SceneStackCommand::floating()` and its helper
  `coveredFraction()` against the *compiled* scene rather than by `StackChecks` against the tiers, which is a code path
  nothing in `TODO.md` had read. Eight of the 10 are one cabinet at one height, so `coveredFraction()` returned exactly
  `0.0`, meaning no cabinet at all overlaps the top in both plan axes. The two candidate causes are recorded in order:
  whether the float is genuine, given that an inflated axis-aligned box would report *more* coverage rather than less and
  so should not be able to invent one, and whether the exact-equality `z` gate against `CONTACT_TOLERANCE_M` drops every
  supporter when a top lands a hair off a stepped support, which is what `all-*` rigs have everywhere
- the discriminator question under CVR-3 is answered with the spec data: there are **three** owners rather than two
  systems, `gmss` with 5 speakers, `sdwa5` with 3 and `sepp` with 2. `sepp` never gets a rig of its own and appears only
  inside `all-*`

## [0.73.0] - 2026-08-14

### Changed

- **The `--mirror-style` axis is swept only when `--roll-mirror` names a device, which halves every default run.**
  `Tier::mirrored()` acts only on segments lying on a quarter turn, and the default sweep rolls nothing, which the
  written scenes confirm with zero `roll_mirror` keys. So the mirror was a no-op for every default candidate and
  `upright` came out byte-identical to `alternate`: **66 `upright` candidates and 0 written**, 18 of them recognised as
  duplicates by `deduplicate()` and the other 48 refused earlier on the height band or on support, in each case
  identically to their `alternate` twin. Letting `deduplicate()` catch them afterwards was not good enough, because a
  candidate costs a full solve plus a compile plus an interpenetration sweep. The default run drops from **132
  candidates to 66** and the test suite from **53 s to 28 s**, with `scenes/generated` byte-identical. A stated
  `--mirror-style` is still honoured whatever is rolled
- SYM-1 is closed as already implemented. `MirrorStyle::Upright` *is* the centred odd cabinet, producing
  `2 + 1 upright + 2` for a row of five, and `Alternate`'s lopsidedness is the deliberate alternative that balances the
  stack across rows when no single row can be symmetric. Its recorded measurement is corrected from "40 odd rows across
  all 11 mirrored stacks, in 11 of 19 scenes" to **8 odd rolled rows across 4 scenes**, all of them in the `-turned-`
  scenes, which come from a separate invocation that does pass `--roll-mirror`

## [0.72.9] - 2026-08-14

### Fixed

- **GEO-2's reasoning as published in 0.72.7 was wrong, and `TODO.md` is corrected.** It argued that the tops row is
  wider than any wall a 3.80 m stage can carry. There is no such limit. `DEFAULT_MAX_WIDTH_M` is 3.70 m rather than
  3.80 m, and the sweep does not hold a rig there in any case: `SceneStackCommand::WIDTH_LADDER_M` walks
  `2.00 … 6.00 m` whenever a rig misses the sub height band, which is what "tried stages 2–6 m" in the refusals means
- the conclusion survives for a simpler reason, now measured across the whole ladder rather than at two widths. The tops
  row is **3.921 m at every stage from 2.00 m to 6.00 m**, dead flat, because `StackSolver::topRow()` puts all 8 tops in
  one row unconditionally. And **`all-1` cannot reach the 2–3 m sub height band at any width on the ladder**: widening
  the stage does shorten the wall, from 8.676 m down to 3.840 m, and 3.840 m is the best case and still 840 mm over the
  ceiling. The widths that would give the tops a usable support are exactly the widths where the wall is too tall to be
  allowed. So the rig fails on two independent counts and neither is geometry — one stack is being asked to hold 41
  cabinets, two complete sound systems

## [0.72.8] - 2026-08-14

### Changed

- **SYM-1's fix is already in the repository, and implementing the item as worded would delete a sweep axis.**
  `Tier::mirrored()` does not mirror positions, it mirrors **roll direction**, and it touches only segments rolled a
  quarter turn. An odd row therefore cannot be a palindrome in roll unless one cabinet is left standing up, which is
  exactly `MirrorStyle::Upright` — implemented, generated by default, and producing `2 + 1 upright + 2` for a row of five.
  Centring the odd cabinet in `MirrorStyle::Alternate` produces the identical row, and the two styles already agree on
  every even row, so `MirrorStyle`, the `--mirror-style` option and half the sweep's mirrored candidates would become
  redundant. The lopsidedness is also deliberate and documented in the method: alternating the extra cabinet by row index
  is what balances the *stack* when no single row can
- SYM-1 is marked `decision` at P2 with the two ways out written down. Either it is already done, in which case the work
  is finding out why **no `upright` scene is ever written** — all 10 scenes the bare sweep writes are `alternate`, so the
  upright siblings are all refused or deduplicated and the axis costs candidates without producing a rig — or
  `MirrorStyle` goes altogether
- SYM-1's recorded measurement is corrected. It read "40 odd rows across all 11 mirrored stacks, in 11 of 19 scenes" and
  the measured figure is **8 odd rolled rows across 4 scenes**

## [0.72.7] - 2026-08-14

### Changed

- **GEO-2's premise is refuted by arithmetic, and `TODO.md` says so.** The entry claimed the lever was the shape of the
  wall, so that GEO-2 waited on GEO-9. `StackSolver::topRow()` puts every top in one row unconditionally, and for the
  `all` inventory's 8 tops that row measures **3.921 m at a 3.80 m stage and 3.921 m at a 5.00 m stage** — it does not
  respond to the stage at all. The widest wall a 3.80 m stage can legally carry is 3.80 m, so **the tops row is wider than
  the widest possible support** and no shape reaches it: `pyramid` leaves 751 mm per side over air, `free` leaves 1421 mm,
  and a flush wall filling the whole stage would still leave 60 mm. GEO-2 no longer depends on GEO-9, and re-measuring it
  after GEO-9 would have proved nothing
- all 22 refusals in that family name a **tops** cabinet and not one names a sub, and every one is an `all-*` rig. 12 of
  the 22 are `all-1`, one stack holding both complete sound systems with 8 tops in one row. Every per-owner rig is clean.
  **CVR-3 is therefore promoted from P3 to P1** and its dependency on GEO-2 dropped, because narrowing the default
  `--from` retires those 12 without touching the solver. The note under CVR-3 that read "that is GEO-2, not a `--from`
  problem" had it backwards and is corrected
- GEO-9 drops from P1 to P3. It is worth having as a shape crews build and it buys nothing measurable, since the item it
  was raised to unblock is refuted independently of it

## [0.72.6] - 2026-08-14

### Changed

- **GEO-9's cheap version is measured and rejected, and `TODO.md` now says why.** The plan was one branch in
  `StackSolver::ceilingFor()`: bound a `tower`'s row by `min($maxWidthM, $supportM)` where the other shapes get
  `$supportM + 2 × OVERHANG_PER_SIDE × RolledBox::widthOf(...)`. It was built, measured and reverted. `ceilingFor()` is an
  upper bound, and a narrow row is not a row that was capped but a row whose device ran out of cabinets, so lowering a
  ceiling can only ever make a wall narrower. On the five GMSS types at 5 m the best `tower` reads 3.280 / 1.340 / 1.200 /
  1.200 m going up, which tapers by two metres over four rows. Across the sweep the branch wrote **0 scenes from 66
  `tower` candidates**, of which 36 missed the height band, 12 deduplicated against a `pyramid` or `free` sibling, 12 were
  refused by the shipped-scene sweep and 6 interpenetrated, and `SceneStackCommandTest` caught a `tower` scene placing 21
  of 23 cabinets. `pyramid` and `free` regenerated byte-identical throughout, so the branch was inert for the existing
  shapes and the result is the tower's own
- GEO-9 is re-estimated from ca. 4 hours to ca. 8 hours and marked `measured` rather than `open`. A flush wall needs a
  width **target** carried into `StackSolver::packTo()` beside its existing budget, so a row keeps taking device types
  until it reaches the target rather than until the next one does not fit. That is the packer's objective changing rather
  than a new enum case

## [0.72.5] - 2026-08-13

### Fixed

- **`TODO.md`'s GEO table was corrupt and is repaired.** A scripted edit two releases ago deleted the GEO-3 row and took
  the newline with it, merging GEO-2 and GEO-4 into one 7-column row that read as GEO-2 with GEO-4's text inside its
  `Needs` cell. GEO-4 had therefore stopped existing as a row, and GEO-2 still carried text superseded by the measurement
  that demoted it. Both are rows again, with the chain stated in the group intro: GEO-9 gives the wall a usable top face,
  which is what GEO-2 needs, and GEO-4 waits on GEO-2
- a cross-reference that read "see GEO-9 and GEO-5 and GEO-9", from two replacements both matching, now names each once

## [0.72.4] - 2026-08-13

### Fixed

- **`docs/catalog.md` regenerated, because it still described a sound system that no longer exists in the specs.** It
  carried the photo-derived GMSS reconstruction — `gmss-turbo-sub` at quantity 8 and `gmss-middle-sub` at 3, neither of
  which is under `specs/speakers/` any more — instead of the builder's five real cabinets: 6 IQ subs, 2 nukes, 2 wall
  basses, the USB mid bass and 3 turbo tops. The totals moved with it, from 3432.7 kg and 26.627 m³ to **3493.7 kg and
  26.586 m³**, so every weight or volume figure quoted from that file since the GMSS specs were rewritten was wrong. It is
  generated by `catalog --write` and nothing had run it

## [0.72.3] - 2026-08-13

### Changed

- `TODO.md` gains **GEO-9 for the `tower` and `mixed` shapes** at P1, since GEO-2 waits on it: `pyramid` and `free` cannot
  express a wall of constant width, which is why this rig reads 2.730 / 1.890 / 2.420 / 1.200 m going up and lands its
  tops on its own narrowest row. It records the part that is not obvious from outside the code — adding the enum cases is
  free, because `shapeFrom()` goes through `tryFrom()` and lists `cases()` in its error, but the *rule* is not:
  `perRowCap()` expresses `pyramid` as a **ceiling** (`min($perRow, $last->count())`) and a tower needs a **floor**,
  insisting on the equal count that `rowSizeFor()`'s divisor balancing would otherwise reduce. So it needs a minimum
  threaded into `rowSizeFor()` beside the maximum, not a line in `perRowCap()`
- a dangling cross-reference in GEO-2 pointing at a shapes row that had never been written is now pointed at GEO-9

## [0.72.2] - 2026-08-13

### Fixed

- **A stated `mix_with` row is capped by the pyramid rule like every other row-building path**, which it was not: it built
  its row from the raw width and could come out holding more cabinets than the row under it, which is the V the shape
  exists to forbid. A verified no-op on the library, since no generated scene passes `--mix` and every scene file came out
  byte-identical, so this closes a gap rather than changing a rig

### Added

- `TODO.md` gains a **`LOAD` group for the two transporters and packing**: where a vehicle belongs in the schema, specs
  for Stefan's and Sepp's vans, a packer for the whole inventory or a stated subset across one van or both, and a report
  that keeps space and weight separate. Nothing about either vehicle is sourced yet, and a load bay is the inside where
  `geometry.dimensions_m` is an outer bounding box, so the schema question is marked as needing a decision

### Notes

- `reserveLifts` still escapes the pyramid cap and cannot be fixed the same way, because it reserves its flanks before any
  tier exists and `perRowCap()` has nothing to measure against at that point. It needs the cap where those rows are
  emitted. **9 of the 13 pyramid-shaped stacks** still step outward
- `docs/catalog.md` is stale as shipped, still listing `gmss-turbo-sub` and `gmss-middle-sub`, which no longer exist under
  `specs/speakers/`. Any total quoted from it needs a `catalog --write` first. Recorded, not fixed

## [0.72.1] - 2026-08-13

### Changed

- `TODO.md` GEO-2 records that **splitting the tops across two rows is the wrong lever**, measured three times against a
  baseline of 10 scenes and 23 refusals. An unbounded split cascaded, since each row becomes the next one's support and
  each budget is narrower than the last: eight tops became seven ever-thinner rows, a 7-tier rig grew to 13, and gravity
  broke those rows into runs overlapping by 203 mm. Capped at two rows it stops cascading and still loses, trading
  `nothing-under` 6 to 4 and `bearing` 5 to 4 for `top-on-nothing` 12 to 16, two new interpenetrations and one scene.
  The real cause is that the wall's rows read 2.730 / 1.890 / 2.420 / 1.200 m going up, so the tops land on the narrowest
  row in the stack. The lever is the wall's shape, so GEO-2 now waits on GEO-5 and the shapes row and drops to P2

## [0.72.0] - 2026-08-13

### Fixed

- **A row keeps the working gap between its own cabinets, and not only on paper.** Three mechanisms space a run against
  something else, namely `align` into an envelope, `align.outside` clear of a named neighbour, and the tier chain outside
  the run inboard of it. None of them asked whether a run's own copies clear each other. The row is laid out at `gap_m`
  from nominal widths and then every cabinet is yawed at the focus, which swings its front corners towards its neighbour:
  17.6 mm of one cabinet inside the next on the by-type tops row, 7.4 mm free-shape, 4.6 and 3.7 mm stereo.
  `--stacks=3 --split=by-type` goes from **0 scenes written with 12 overlap refusals to 6 written and none**
- **`Interpenetration::narrowestGap()`** is the measure it needed. `worst()` clamps at zero and reports overlap rather
  than clearance, so a bisection chasing a positive gap never brackets and gives up silently. This one stays signed
  through zero. It also takes the separating distance across **every** axis, where `separation()` stops at the first axis
  that separates a pair, which is right for a yes-or-no answer and useless as a metric

### Notes

- **The measure is the shell and never the bounding box**, and one attempt was spent learning it. A box grows by
  `depth x sin` as a cabinet toes in, about 26 mm on a 0.520 m deep Tecnare, while a tapered cabinet's outermost point is
  its back bottom corner and moves inward: three aimed Tecnares span 1.5137 m where nominal widths and gaps give 1.540.
  A box measure invents overlaps that do not exist, and it spread a correct row by 32 mm
- Two `StackTest` assertions were re-pinned rather than worked around. They recorded a row whose aimed cabinets sat closer
  than the 20 mm they were given, which the row narrowing overall had hidden. It is 1.5427 m now, just past the
  unrotated 1.540
- Clearance outranks the envelope where the two conflict, because bearing has slack, two thirds of a cabinet past each
  end and 600 mm on that row, while interpenetration has none
- The safety contract held throughout: all 19 shipped scenes regenerate byte-identical, since none of them contained an
  overlapping pair for this to correct

## [0.71.4] - 2026-08-13

### Changed

- `TODO.md` GEO-3's recipe corrected by an attempt that was implemented, measured and reverted. The mechanism is right
  and the win is real — `--split=by-type` went from 0 scenes written with 12 overlap refusals to **6 written and 0
  overlaps** — but both objectives tried were wrong. `Interpenetration::worst()` clamps at 0 and reports overlap rather
  than clearance, so the solve silently no-ops. An axis-aligned x-gap is wrong in *direction* for tapered cabinets: a
  toed-in trapezoid's outermost point is its back bottom corner and moves inward, while its bounding box grows by
  `depth × sin θ`, about 26 mm on a Tecnare, so it invents overlaps and spread a pinned row by 32 mm. What is needed is
  `Interpenetration::separation()` without its early break, which is the true SAT distance. The no-op contract held
  throughout: no shipped scene moved

## [0.71.3] - 2026-08-13

### Changed

- `TODO.md` GEO-3 carries the worked-out implementation recipe rather than only the diagnosis, since the mechanism it
  needs already exists: `SceneCompiler::clearedOutside()` solves the identical shape for run-to-obstacle clearance, and
  the within-run version is the same three lines with `Interpenetration::worst()` as the objective and `placedFor()`
  materialising the copies. It records the guard to copy from `Alignment` — one plain row, nothing nested, two or more
  copies, placement aimed — and the **no-op contract that the two reverted attempts this release lacked**: every one of
  the 19 shipped scenes must come out byte-identical, because none of them contains an overlapping pair for the fix to
  correct, so a regenerate followed by an empty `git diff` is the check that the change is safe before the refusal
  count is even looked at

## [0.71.2] - 2026-08-13

### Changed

- `TODO.md` GEO-3 re-measured after 0.71.0 and sharpened from "an aimed row is spaced on flat widths" to where it
  actually lives. The per-owner 22.5 mm overlap **is fixed and writes**; `--split=by-type` still refuses every variant
  but `block` fell from 135.6 mm to 17.6 mm and `stereo`'s envelope refusal is gone, leaving 17.6, 7.4, 4.6 and 3.7 mm.
  The refusing ids are copies of one placement rather than two runs, so the defect is a run's **internal** pitch: it is
  nominal while its copies are aimed, and the chain that solves run-to-run clearance never asks the same question inside
  a run. Raised to P1, because GEO-2 depends on it and GEO-4 depends on GEO-2

## [0.71.1] - 2026-08-13

### Changed

- `TODO.md` GEO-2 carries its diagnosis and the result of an attempt that was reverted, so the next pass starts from
  evidence. Both systems' eight tops are 3.921 m in one row on a 2.420 m sub wall, the carryable width is 3.020 m and a
  6 + 2 split fits. Splitting took "nothing under it at all" from 6 refusals to 0 and bearing from 5 to 0, and took
  interpenetration from 0 to **12**, because two aimed tops rows toe into each other and the clearance chain has no
  notion of a tops row standing on another one. GEO-2 now depends on GEO-3 rather than on nothing

## [0.71.0] - 2026-08-13

### Fixed

- **`block` no longer compresses a row below its own spacing, which is what every interpenetration refusal was.**
  `StepSolver` bracketed from `0.0`, and `block`'s parameter is a factor on each copy's x offset, so any solve below
  `1.0` pulled the cabinets *into each other*. Only `spanAt(0.0)` was guarded, which catches nothing but the absurd end.
  Measured on `stacked-gmss-1-block`: a tops row of three aimed turbo tops spans 1.406 m once the toe-in is counted and
  the nuke row carrying it is 1.200 m, so the solver squeezed the pitch from 470 mm to about 390 and neighbours ended up
  **92 mm inside each other**. `Alignment::minParameter()` now names the floor — the arrangement's own spacing, in both
  modes — and `StepSolver::solve()` brackets from there
- **A row wider than its envelope keeps its spacing and warns, instead of erroring.** The old message said the cabinets
  were too wide "even stacked on one spot", which was only ever true of `block`: `stereo`'s parameter is a distance
  added to each column's offset, so its 0 is already the natural spacing rather than every cabinet on one point.
  Refusing a rig over a row that stands up perfectly well unspread was the wrong answer either way
- **8 interpenetration refusals and 6 spread-envelope refusals are gone**, all 14 of them. The written count moves from
  11 to 10 because `stacked-sdwa5-2-free-stereo` and `-free-block` turn out to be one rig: their `at:` values were
  already identical and only the `mode:` line differed, so `block` no longer compressing makes the duplicate visible

### Changed

- `Gravity::resolve()` takes a slide **slack** plus a stage bound rather than one span, so how far a row may move and
  how wide the stack may be are separate questions. `Stack::$slideWithinM` becomes `Stack::$slideSlackM`

### Notes

- **Multi-stack sliding is measured, safe and deliberately still off.** The bound a rig needs is `clearance / 2 - gap`,
  since `StackSceneWriter::centres()` leaves exactly `--clearance` between two envelopes, and switching it on produced
  no interpenetration anywhere. It is not an improvement yet: sliding a *sub* row moves what the tops row above it
  stands on, so enabling it took "a cabinet with nothing under it at all" from 6 refusals to 12 and cost
  `stacked-all-2-center` entirely, against the one `gmss-mid-bass: LEFT OUT` it fixes there. The tops row has to stand
  on its support's plateau first. `TODO.md` recorded that dependency and was right where the plan overrode it

## [0.70.1] - 2026-08-13

### Changed

- `TODO.md` restructured as grouped tables — stable IDs, priority, an effort estimate to 5 minutes, what each row buys
  in measured refusals, blocking IDs and state, with detail prose in per-item blocks under each group's table. Plain
  numbers had broken every cross-reference twice in one session, once when an item was inserted at the top and once when
  a resolved one was deleted
- seven claims in `TODO.md` that the implementation had overtaken were corrected against measurement — among them "no
  multi-stack stereo rig survives the checks" (5 ship), "16 of 30 multi-stack scenes not height-symmetric" (3 of 13),
  and a `--per-owner` example that cannot be reproduced because `--per-owner` now writes nothing at all
- two contradictions recorded rather than quietly resolved: spreading sub clusters is asked for by the stereo item and
  forbidden by the alignment rule that only the top tier may spread, and "outermost tops as wide as possible" competes
  with "all tops spaced evenly" whenever a row does not exactly fill its envelope

## [0.70.0] - 2026-08-13

### Added

- **A generated scene must meet its sub height band, and one outside it is not written.** `interface_height_m` (2.0 m)
  and `max_sub_height_m` (3.0 m) have bounded the sub/top transition all along, and the solver treated both as
  preferences — reporting a miss as a warning and building the rig anyway. Right for a scene somebody wrote; wrong for
  one the sweep generates, and measurably so: of the **54 scenes that shipped, only 16 had every stack between 2 and
  3 m**, the rest including `stacked-all-3-turned-center` at 5.73 m and `stacked-sepp-2-center` at 0.60 m — a rig whose
  tops fire at knee height. Refusals name the measurement, the bound and the millimetre miss, because "no workable
  arrangement" for a rig that is 40 mm too tall sends somebody hunting for a geometry fault
- **The sweep moves a rig onto a stage that fits it rather than skipping it.** A sub wall gets shorter as the stage gets
  wider, so a miss points in a direction: too tall widens, too short narrows, along a ladder of real stage widths
  (2.00–6.00 m). The width it settles on is written into the recorded command — a replay that inherited the default
  would rebuild the rig that missed. `stacked-all-2-center` is the case: refused at 3.70 m, 2.033 and 2.833 m of subs
  at 4.40 m

### Changed

- **`--max-width` is a starting width in a sweep rather than the width.** Threaded through the solve explicitly instead
  of being read from the option deep inside `stackFor()`, so the ladder cannot be silently undone one layer down
- **The band binds every invocation, not only the sweep.** `build:all` regenerates a generated scene by replaying *its*
  recorded line, which names `--from` and `--stacks` and so is not a sweep — holding only the sweep to the band would
  have left every out-of-band file rewritten exactly as it was, for as long as it existed
- **The scene set is 17 files where it was 54**: 10 from the sweep plus 7 turned siblings. The 44 removed are rigs
  outside the band — 19 uprights and their 19 turned siblings, plus 6 replaced. Fewer scenes, each one buildable

- **A badly-carried row is slid along its support instead of the rig being thrown away**, where nothing stands beside
  it. **A row does not have to be centred on what carries it**, and assuming it did was refusing rigs that stand up: the
  stage-width ladder cannot help a *quantity-bound* rig, and the GMSS cabinets are one — four sub types and at most six
  of any one of them means the row count is set by the types, so the wall is 3.34 m at every width from 3.70 to 6.00 m.
  Their one arrangement inside the band packs the nukes and mid-bass into one row, and centred that row put the outboard
  nuke on 8 % of its own width. Slid, both ends are carried and `stacked-gmss-1-center` writes at **2.84 m** — the
  system's single-stack coverage back. `Gravity`'s existing repair (`outboardSeats`, seating the ends on a stepped
  support) returns null here for want of a second support run, so the two are tried best-of and neither is kept unless
  it improves the worst bearing — which is what keeps every rig that stands up today unchanged
- **The rescue applies to any tier, not only the top one.** It was written for outboard fills and gated to the last
  tier, which read as though it were a property of tops rows; a packed *sub* row lands the same way and had no repair

### Notes

- **Sliding is bounded by what else is in the scene, not by gravity.** Only a stack with nothing beside it may move a
  row, and then only inside the stated stage width. Stacks are spaced on their widest tier and their envelopes
  deliberately overlap in x — tiers at the same height are each centred and narrower — so an unbounded slide reaches
  into the stack next door: measured, **180 mm of interpenetration across five `all-3` scenes**. Letting a multi-stack
  rig slide needs the spacing to work from resolved extents rather than centred tier widths, which is the same root as
  the two open tops-row items and is recorded in `TODO.md`
- **A candidate refused for something other than a height miss gets one rung of the ladder, not all of it.** Walking it
  fully retried each of the 122 refused candidates at four widths and tripled the sweep to recover the one scene a
  single rung already recovers

## [0.69.0] - 2026-08-12

### Fixed

- **Every run of a tops row is now spaced against its inner neighbour, not just the fills.** The clearance chain
  handled fill-to-throw only, on the reasoning that the long throw is placed by gravity on its own support and should
  not be moved. That is true of *one* throw run and wrong the moment the throw lands in several: nothing spaced those
  against each other, and two runs of the same device came out **360 mm inside each other** — over half a cabinet —
  because each was placed independently and neither knew the other was there. Chaining fills to a throw and then
  leaving the throws unspaced is a star with a hole in the middle. The innermost run keeps gravity's position, so the
  centre of the rig does not move, and ordering by distance from the row's centre is what makes the chain resolvable
  in one pass — `align.outside` can only name a placement already resolved
- **`block` writes 5 scenes where it wrote none.** Every `block` variant used to collapse into its `center` sibling,
  which the TODO recorded as a limitation; with the chain covering all runs it produces genuinely different rigs in
  five cases. The sweep goes from 25 written to **28**, and interpenetration refusals from 14 to 12

### Notes

- **`block` is worse than "collapses into `center`", and that is now visible.** Of the 12 remaining interpenetration
  refusals, **every same-tier one is a `-block` variant** — `stacked-gmss-2-block` at 360 mm,
  `stacked-sdwa5-1-block` at 149 mm, `stacked-gmss-1-block` at 92 mm. Justifying an *aimed* row across its support is
  the fixed-point problem `StepSolver` exists for, and something in that path is not accounting for the toe-in, so the
  mode emits overlapping geometry rather than merely failing to spread. Nothing broken ships — the generator refuses
  it — but the item is a defect rather than a gap, and it is recorded as one
- **The one remaining cross-tier overlap is a different family**: `stacked-all-2-center` puts a tier-4 run 127 mm
  inside a tier-3 run, which is the stepped-support case `swallows()` guards for packed sub rows and has never guarded
  for the tops row

## [0.68.0] - 2026-08-12

### Added

- **`scene:stack` with no options now writes scenes. It wrote none at all before.** The project's stated goal is that
  as many *sensible* configurations as possible come out of one command in its default settings, and measured against
  that the command scored zero: `--from` defaulted to every speaker in the repository, which since the GMSS cabinets
  arrived means two sound systems in one unbounded stack — a rig nobody would build, whose eight tops alone are
  3.921 m. Every generated scene had to spell four to six flags out. **Absence now means sweep**, over two more axes
  beside the `--align` × `--shape` the command already swept: **one rig per owner plus one from everything**, and
  **one, two and three stacks**. 25 scenes out of 132 candidates, the rest being 65 duplicates and 42 named refusals.
  Naming `--from`, `--stacks` or `--per-owner` collapses it to that point, exactly as naming `--align` does
- **`--max-width` defaults to 3.70 m and `--max-sub-height` to 3.0 m**, so the bare command has bounds to solve
  against. 3.0 is the top of the 2–3 m band asked for; a rig that cannot get under it reports the miss in millimetres
- **`--mirror-style=alternate|upright`**, both generated by default. An odd cabinet in a turned row has no arrangement
  that is both symmetric and flat: `alternate` sends it to one side and flips which side on the next row up, so the
  stack balances even though no row does; `upright` leaves it unrolled in the middle, which is a palindrome and stands
  172 mm proud (763 mm against the rolled 591). `deduplicate()` drops whichever makes no difference
- **`StackBlock::subHeightM()` and `heightM()`** — one derivation for the three callers that need it: the header, the
  strategy tiebreak, and the stack ordering. Three copies of one sum is how a header ends up disagreeing with its rig

### Changed

- **The strategy choice breaks a tie on height.** `build()` kept whichever attempt placed the most cabinets — right,
  since a cabinet in no rig is the worse failure — but had nothing to say between two attempts placing the same
  number, so the first tried simply won. That is how stating a `max_sub_height_m` could make a rig come out *taller*
  than not stating one. **The ceiling is monotone at the solver level** — probed across six inventories × two shapes ×
  two interface heights: 13 shorter, 11 unchanged, **none taller** — so the extra height was never the solve, it was
  this tie resolved by strategy order. Equal cabinets now take the shorter rig
- **The taller stacks go where the alignment wants them**: middle in mono, ends in stereo. The same rule as the tops
  row one level up, and until now the blocks came out in solve order with nothing looking at their heights, so
  `--per-owner` read `3.34 | 3.20 | 1.80` with the tallest hard left in 7 of 30 multi-stack scenes. It reads
  `2.44 | 3.61 | 1.80` now. **This improves symmetry and does not deliver it** — ordering places the tall stacks but
  cannot make the flanks equal, which depends on the split giving each stack similar contents
- `--max-scenes` defaults to 80, since the sweep legitimately produces dozens; it still refuses rather than truncating

### Fixed

- **`build:all` prunes stale derived files under every `generated/` directory.** The scene ids changed shape wholesale
  this release, which left 38 orphaned `.blend` files and 11 orphaned renders: every stage only ever added, so the
  tree accumulated a layer per release and a reader could not tell which pictures belonged to the current rigs. A
  `.blend`, plan or render whose scene id no longer exists is removed and named. **Generated scene *files* are
  deliberately not pruned automatically** — deciding one is stale means knowing which files the run wrote, and two
  attempts at that by timestamp destroyed the scene set outright (`filemtime()` is whole seconds while
  `microtime(true)` is fractional, so a file written in the same second as the run started read as older than the run).
  A stale scene file shows up in `git status` and is overwritten by the next `--force`; a deleted one is 25 files of
  work. Automating it wants `scene:stack` reporting the paths it wrote, not a cleverer clock
- **A recorded regeneration command now reproduces the scene's own name.** The sweep builds a name from the base id
  plus which rig it is — `stacked` + `-gmss-1` — but recorded only the base, so every replay wrote `stacked-center`
  over one file while the real 25 went stale
- **The generator now refuses a candidate that interpenetrates**, on the same test the shipped-scene sweep applies.
  The separating-axis geometry moved out of `ShippedScenesTest` into `App\Scene\Interpenetration` so both callers
  share one answer — it was previously the test's private business, which meant the command could write a scene the
  sweep would then reject, and the failure surfaced one command later
- **That check found overlaps that had always been there.** `--dry-run` writes no file and only written scenes reach
  the sweep, so several rigs asserted on by tests had been interpenetrating unnoticed: the free-shape GMSS per-owner
  stack by **22.5 mm**, a by-type three-stack aimed tops row by **17.6 mm**, and others between 7 and 23 mm. All are
  the aimed-row toe-in family. The rigs are refused now rather than written, and the underlying spacing defect is
  recorded rather than papered over

### Notes

- **No multi-stack stereo rig survives the checks yet**, so the stereo half of the ordering rule is implemented and
  unexercised: the tops-spread envelope refuses the narrow supports every generated scene has. Asserting it would mean
  asserting on a refusal

## [0.67.0] - 2026-08-12

### Added

- **`shape:` on a stack, and `scene:stack --shape=pyramid|free`** — both generated by default, one scene each, the
  way `--align` already writes one per layout mode. **The pyramid keeps the plain scene id** and the other gets a
  `-free` infix, so the default scene is the good one and no existing id was renamed
- **`build:all` generates a turned sibling of every generated scene**, laying the horn-loaded cabinets on their
  sides. **16 of 18 solve**; a refused one is reported with its reason and the build carries on rather than failing —
  the same treatment `scene:stack` already gives an unbuildable alignment. Which cabinets are turnable is stated in
  the command, continuing the existing decision not to invent a spec field to serve a layout
- **The generator refuses a scene the shipped-scene sweep would reject.** Its stated promise was already "a
  generator that emits a scene the compiler rejects is worse than no generator", but the floating-cabinet check
  lived only in the test — so it wrote `all-speakers-three-turned` with a top 1.261 m up over open air, compiling
  cleanly and failing one command later. Same check, same data, in `compileYaml`

### Fixed

- **Generated stacks came out V-shaped and too tall, which was a regression from 0.66.0.** The weight-first fill
  order put the two wall basses — heavy but narrow, 1.34 m of row between them — on the floor, and `swallows()`
  then refused to widen that row with the 0.500 m mid bass. A narrow base under wider rows is both the V and the
  height: five of the twelve generated stacks widened as they rose, one of them 1.34 m → 2.51, and the low
  three-stack rig had gone from 1.526 / 1.684 / 2.070 m to 1.526 / 2.114 / 3.240. **The pyramid shape is the fix
  and it is two rules, not one:**
  - **the fill is ordered for row width, not weight** — six IQ subs on the floor is a 3.28 m base where two wall
    basses is 1.34, and the same twelve cabinets come out 3.28 → 2.56 → 1.54 in three rows and 2.070 m against
    1.34 → 1.20 → 1.63 → 1.63 → 1.54 in five and 3.240
  - **no row may hold more cabinets than the row below it.** A *count*, not a width, and that distinction is the
    rule working rather than not: capping the width was tried first and refused six Achenbachs' 3.700 m on six
    Flexys' 3.646 — a 27 mm shoulder per side against an allowance of four hundred — splitting them into two rows
    of three, whereupon the 1.84 m row could not carry the tops and a 2-way was dropped. A flush wall is not a V
- **It is an improvement, not a cure, and the number is 21 → 9.** Across the 39 generated stacks, 21 had a row
  holding more cabinets than the row below it; as pyramids 9 still do. The cap is applied where rows are *dealt* and
  where they are *packed*, and three other paths build rows without consulting it — a lifted flank
  (`reserveLifts`), a stated `mix_with`, and the mixed bottom row. The bottom row needs no cap, being first; the
  other two are the residual and are worth doing next
- Measured across every representative stack, the pyramid removes the V in **five of six** and is shorter in
  **four of six**: `everything` 6.871 → 4.966 m, GMSS alone 4.010 → 3.340, the awkward by-type stack 3.240 → 2.070.
  Two configurations place a cabinet fewer as a pyramid (38 against 39, and 21 against 23), which is the honest
  cost of the count cap and the reason both shapes are generated
- **`StackSceneWriter` now emits `shape:`.** A bound left out of a generated file is a bound the rig quietly stops
  answering to, because the `stack:` block is re-solved on every build — the pyramid variant was rebuilding as its
  own free-form sibling, which is what made the two indistinguishable and deduplicated away
- **`commandLine()` no longer freezes the fill order.** Recording the resolved `--from` list meant a scene generated
  from the default gear kept the order it was written with for ever; it now records `--from` only when one was
  actually passed

### Notes

- **TODO 22 is resolved and deleted** — `build:all` regenerates every generated scene from its own recorded command,
  so a comment table cannot go stale while the rig stays right. TODO 4 now carries the measured turned results
  rather than one example, TODO 29's 4.085 m is corrected to 4.563 (pyramid) and 5.628 (free) — both still above
  what a 4 m crank stand reaches — and TODO 21 is narrowed to `block`, the one alignment still spaced on flat widths
- **TODO 5 was deliberately not attempted.** Centring the odd cabinet of a mirrored row is the same idea as the
  stereo tops row, but it moves cabinets in every mirrored rig in the library and wants its own measured pass

### Known broken

- **Six hand-built scenes still reference the GMSS cabinets as they were before the builder gave real figures**, and
  fail the overlap and floating sweeps: `all-speakers-one-center`, `both-systems-side-by-side`,
  `both-systems-stereo`, `everything`, `gmss-full-stack` and `gmss-full-stack-truss`. Each hand-derives a GMSS stack
  against dimensions that moved by up to 40 % — the wall bass went from 1.020 m to 1.400 m tall, the mid bass from
  0.595 m to 1.200 m **wide** and from two cabinets to one — so they need their stacks re-derived rather than their
  device ids renamed. Not attempted here because the arrangement in the photo is what they are models *of*, and
  because the tops count is still open (see the note below)

## [0.66.0] - 2026-08-12

### Added

- **`max_sub_height_m` on a stack, and `scene:stack --max-sub-height`** — a **ceiling** on the sub/top transition,
  the mirror of `interface_height_m`'s floor. Stating one inverts what the solver optimises for: instead of returning
  the first arrangement that *reaches* the interface, it walks the whole search and keeps the **shortest** that stands
  up. Missing the ceiling is a warning naming the miss in millimetres, not a refusal — the number it reached is the
  inventory's own floor
- **A row may hold as many device types as it takes** (`StackSolver::packedRows`). This is the only thing that can
  make a stack of many types short, and the reason is arithmetic: a row costs the height of its *tallest* cabinet, so
  two types in one row cost one height rather than two. Our five own cabinets on the 3.70 m stage go from six rows and
  3.640 m to five rows and 3.040 m; all 39 speakers in three stacks come out at 1.526 / 1.684 / 2.070 m against the
  3.146 m that was previously the floor. Packing is offered *alongside* the ordinary deal and the shortest that stands
  up wins — neither is better everywhere: on 2 SKRAMs, 2 wall basses, a mid bass and 2 2-ways the deal finds 1.445 m
  and the pack 2.465 m
- **`scene:stack --split=by-type`** — gives each stack whole device types instead of a share of every device, balanced
  by `quantity × width`. What makes a rig low: a by-count stack holds every type and is as many rows tall as there are
  types, where a by-type stack holds two or three
- **`scene:stack --no-asymmetry`** — leaves the odd cabinets out rather than giving one stack more than another,
  which was the old behaviour
- **Every generated scene records the command that made it**, as a runnable line in its header. A generated file that
  cannot say how it was generated has to be reverse-engineered from its own contents first, which is exactly what
  regenerating eleven scenes after the GMSS re-measure required
- **`build:all` regenerates the generated scenes first**, by replaying each file's own recorded command. That closes
  the last gap in "from specs to pictures": every other stage already followed the specs, and these files did not —
  re-measuring the GMSS cabinets left eleven of them describing rows that no longer existed and a full build noticed
  nothing. There is deliberately no list of commands in the code, because a second copy would drift from the files.
  **This is the only stage that writes outside `build/`**, and `--dry-run` says how many files it would rewrite

### Changed

- **The odd cabinets are now placed by default.** Three Tecnares across two stacks were 1 + 1 with the third left out
  of the rig; they are now 1 + 2, with the unevenness named in the scene header instead of the omission. The stacks
  stop being identical, which is the price, and `--no-asymmetry` declines to pay it. `stacked-two-center`,
  `stacked-two-flat-center` and `stacked-two-turned-center` each gain a cabinet; `stacked-turned-center` gains the
  two SKRAMs
- **Generated scenes live in `scenes/generated/`, and everything derived from one follows it** into
  `build/scenes/generated/`, `build/plans/generated/` and `build/renders/generated/`. `SceneLoader::files()` is now
  recursive — the same `RecursiveDirectoryIterator` `SpecLoader` uses for `specs/`, which its docblock already
  promised — so `scene:build stacked-center` still resolves by bare id and no command knows the subdirectory exists.
  Eleven generated scenes moved out of `scenes/`
- **`scenes/all-speakers-three-low-center.yaml` is now generated**; the hand-written one is
  `scenes/all-speakers-three-stated-low.yaml`, renamed because two scenes cannot share an id and "stated" is what
  distinguishes it — a person chose which cabinets share a stack. It states only the contents and the ceiling now,
  where it used to state a row-height sum per stack that went stale the moment a cabinet was re-measured
- **The Achenbachs stand on the Flexys**, not under them. `byFrequency` always asked for that — both are driven from
  38 Hz and the Flexy stops at 200 against the Achenbach's 1500, so the Flexy is the more sub-like of the two — and
  the low scene's "widest row first" rule turned out not to exist: 3.700 m on 3.646 m is 27 mm proud per side against
  the two thirds of a cabinet `Gravity::MIN_BEARING` allows
- **`all-speakers-two-center` is gone.** With the re-measured GMSS cabinets no by-count arrangement of all 39 speakers
  across two stacks stands up — every width from 4 to 6 m and both interface heights leave the tops row on a stub. The
  two-stack rig exists as `all-speakers-two-low-center`, split by type
- `detail-check` gains the fifth GMSS cabinet and 300 mm of sheet; everything right of the 1.200 m mid bass moved
- **The GMSS nukes are laid on their sides**, mirrored, in every scene where it measures better. Rolled a nuke is
  0.770 × 0.590 rather than 0.590 × 0.770, so each column stands on a wider, lower foot. In `gmss-full-stack` that
  drops the columns from 2.780 m to 2.600 — level with the tops to within a centimetre, which is how the photograph
  reads, where upright they stood 170 mm proud — and widens the rig from 2.560 m to 2.920 m. `gmss-full-stack-truss`,
  `both-systems-side-by-side` and `both-systems-stereo` follow it hand-placed; `all-speakers-three-stated-low` (110 mm
  overhang → none) and `everything` (108 mm → 18 mm) follow it through `roll_mirror`. **`all-speakers-one-center` is
  the deliberate exception**: in one stack the wider pair costs 670 mm of height and adds two overhangs, so it keeps
  its nukes upright and says why. Measured both ways in every scene rather than assumed
- **`gmss-full-stack-truss` now hangs its inner two MACs over the cabinets**, because the stack widened to 2.920 m
  while the fixtures stayed at ±1.050. 1.777 m of vertical clearance, so nothing touches, but it is over gear rather
  than over floor — and widening the spacing cannot fix it: the outer pair would need ±4.680 on a truss whose half
  span is 4.500. Recorded in the scene rather than quietly left true-looking

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Two fills on the same side of a top row were placed inside each other** (TODO 28). Each fill was solved `outside`
  the long throw, which works for one fill per side and puts the second on top of the first: a GMSS turbo top and a
  2-way went 99 mm inside each other, which their own toe-in opened to 213 mm. A wider gap was no remedy — four times
  the gap moved it 35 mm — because they were never spaced against each other at all. The fills are now a **chain**,
  each solved outboard of the one inboard of it and emitted in that order
- **`StackSceneWriter` now emits `max_sub_height_m`.** A bound left out of a generated file is a bound the rig quietly
  stops answering to, because the `stack:` block is re-solved on every build

### Notes

- **TODO 30 is resolved and TODO 22 is what the new header line addresses.** "Each device type costs at least one row"
  was true and is no longer: that is what packing removes
- **A stepped row propagates upward, and that is why the pack checks its own candidates.** Two SKRAMs in the bottom
  row leave the level Flexy row above them sitting at three different heights, and the Achenbach row above *that*
  straddles the seams and lands on 1.9 % of itself. Nothing local to a row can see that coming, so the packer proposes
  and `StackChecks` disposes. Sizing rows to the tall segments instead was tried and is wrong — a cabinet outboard of
  the plateau lands on the shoulder, which is what a stepped wall looks like — and it cost 3.8 m of height
- **The planned stepped-row bearing check was dropped**: `bearingProblems` already judges every cabinet against what
  it personally landed on via `Gravity::resolve`, and `Stability::tips` covers the row as a body. A second rule would
  have duplicated an existing one, which `StackChecks`' own docblock warns against
- **The planned `pillarProblems` re-derivation was dropped too**: it counts cabinets *in the stack*, so a stack that
  received a single cabinet has `$held = 1` and is already exempt. Dealing remainders cannot trigger it
- **GMSS is five cabinets, not four**, and 14 cabinets at 994 kg. The builder's stated figures replace the estimates
  read off the photo. Still `estimated` provenance, deliberately: there is no datasheet, no plans in hand, and
  `measured` would claim we taped it. **Open question — the photo appears to show four tops where he said three**

## [0.65.0] - 2026-08-12

### Added

- **`scene:stack --mix=DEVICE:OTHER[,OTHER]`** — states a `mix_with` from the command line, and `StackSceneWriter`
  now emits `mix_with` into the generated file. Both halves were missing: the code's own comment said "anything
  wanting `count`, `align` or `mix_with` is edited into the written file afterwards", and a hand edit to a generated
  scene is undone the next time the command writes it
- **`scenes/all-speakers-three-low-center.yaml`** — all 41 speakers in three stacks with **every sub/top transition
  under 3 m**: 1.363, 2.037 and 1.445 m

### Notes

- **What decides a stack's height is which device types are in it, not the stage width.** Each type costs at least one
  row and the sub height is the sum of the rows, so a stack holding six sub types is six rows tall however wide the
  stage. `scene:stack` splits by count and keeps small-quantity devices together, which lands one stack with most of
  the types and makes it the tall one — and that split is the one thing `--from` cannot override, which is why the new
  scene states its contents per stack
- **The generated scenes are already at their floor.** 3.146 m for the three-stack and 4.359 m for the two-stack's
  second stack are not settings: an interface target of zero — which makes the solver take the widest rows and so the
  shortest stack — gives exactly the same, and six `--mix` combinations across two to five stacks and every width from
  3.70 to 8 m gave the same or worse. `--mix` is still the right feature to have; it just does not move these
- **A mix can make a stack taller**, which is worth knowing before reaching for it: a shorter stack makes the solver
  narrow rows to get back above the interface target, so mixing with a 2.5 m target took the three-stack from 3.146 m
  to 3.783. With the target at 2.0 the same mix gives 2.42
- **A low rig and a truss over it are not both available with this much gear.** Two or three types per stack means
  they stand side by side: 10.59 m across against the generated 6.77. Ten metres is every truss segment we own, so no
  goalpost spans outboard of it — the Gerüst supports land inside a stack, which the overlap sweep caught. So
  `everything.yaml` keeps the taller, narrower arrangement


## [0.64.0] - 2026-08-12

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A stated `mix_with` placed the flanking device's cabinets twice.** `fillWith()` iterated
  `foreach ($remaining as [$device, $count])`, which destructures a snapshot taken before the first iteration — so a
  mix that consumed the flanking device zeroed it in `$remaining` while the loop, still holding the stale count, dealt
  the same cabinets again into rows of their own. **Eight GMSS turbo subs became sixteen out of a stock of eight**,
  and `scene:build`'s over-use warning was the only thing downstream that noticed. The loop now iterates
  `array_keys()` and re-reads each count
- **And it took the whole flanking stock rather than what fits.** All eight either side of three middle subs is a
  6.065 m row on a 5 m stage, so the mix meant to *widen* a narrow tier had the bounds check refuse the whole
  arrangement instead. `statedMix()` now adds flankers in pairs while they fit the support and `$perRow` — which it
  received all along and never used — and leaves the rest in `$remaining` for their own rows

### Changed

- **`all-speakers-one-center` now holds all 41 cabinets, up from 38**, and its sub/top transition drops from 6.546 m
  to **4.163 m**. Stating `mix_with: [gmss-turbo-sub]` on the middle subs takes their tier from 1.825 m to 2.885 m;
  every tier above widens with it — six turbo subs at 3.160, six Achenbachs at 3.700 — so the 3.744 m tops row is
  carried and the three Tecnares no longer have to be left out. Nine tiers became seven
- `all-speakers-two-center` also reaches all 41 cabinets with the same mixes stated, and drops the `gap_m: 0.05`
  workaround: its tops are un-aimed, so it never needed the wider gap

### Notes

- **The 2–3 m band is still not reachable for every stack, and that is arithmetic rather than a setting.** Each device
  type costs at least one row, and a mixed row is as tall as its *tallest* member — so merging two types only buys
  height when it removes a row outright, which needs the whole flanking stock in one row and the support cap decides
  that. A stack holding seven device types is about seven rows tall whatever the stage width, which is why
  `all-speakers-two-center`'s second stack stays at 4.359 m
- The `gap_m: 0.05` workaround matters more than it looks: on the one-stack scene it grew the eight-top row by 210 mm
  and dropped the outer top's bearing to 33%, a hair under the third the checker requires. A workaround carried into a
  scene that did not need it nearly refused it


## [0.63.0] - 2026-08-12

### Changed

- **All four all-speaker scenes state `interface_height_m: 2.5`** rather than 2.0, to bring the sub/top transition
  towards a 2–3 m band. `all-speakers-two-center`'s first stack goes 2.163 → 2.763 m and
  `all-speakers-two-matched-center` sits at 2.763 on both stacks
- **`everything.yaml`'s truss now rests on the two Gerüste**, whose 5.000 m platforms are the tallest thing we own.
  The GMSS towers still stand, outboard and carrying nothing, which is honest for a scene whose point is showing the
  whole inventory — at 5.200 m they could take the truss instead

### Notes

- **Widening the stage does not move the sub/top transition at all**, which was the assumption behind this change and
  is worth writing down: every width from 3.70 m to 8 m deals the same rows. What forces the row count is the cabinet
  counts and the support cap, not the stage. `interface_height_m` is the only lever — it is a height the tops must
  clear, and the solver picks the widest row that still reaches it
- **Two stacks are still outside the band and cannot be brought in by width or stack count**:
  `all-speakers-three-center` sits at 3.122–3.146 m, which is its floor, and `all-speakers-one-center` at 6.546 m —
  33 subs in one stack need eight rows at the capped widths, so a 2–3 m transition there is arithmetic, not a setting
- More stacks does not help: devices with two or three units are kept together because a lone cabinet cannot be
  flanked, so one stack always inherits them and goes tall — 3.722 m at four stacks, 4.833 m at five
- **The route to a 2–3 m transition everywhere runs through the mixed-row fix**, not the stage width: the only way to
  widen rows is to widen the 1.825 m middle-sub tier, and that means mixing it with a neighbour — the path whose
  `mix_with` fill currently over-fills a row. Filed with TODO 28


## [0.62.0] - 2026-08-12

### Added

- **Four scenes dealing both systems' speakers into stacks with owner ignored** — the comparison the per-owner
  scenes cannot make:
  - `all-speakers-one-center` — 38 cabinets in one stack, 6.804 m tall
  - `all-speakers-two-center` — 39 cabinets, everything in and asymmetric: 16 beside 23
  - `all-speakers-two-matched-center` — a true 14 + 14 pair, at the cost of 13 cabinets
  - `all-speakers-three-center` — 39 cabinets in three stacks, the widest of the set
- **`scenes/everything.yaml`** — every device in the repository in one picture: the three-stack speakers, 10 m of
  truss with all four MACs hung, both Gerüste and all three racks. 55 cabinets, 3237.5 kg, 15.50 × 2.16 m

### Notes

- **One stack is hand-written and the other three are generated**, because `scene:stack --stacks=1` refuses this
  inventory at every width and — after 0.61.0 — that refusal is arithmetic, not a limitation. Three middle subs pin
  a 1.825 m tier that no row count widens; the bearing rule caps everything above near 2.5 m; eight tops in one row
  are 3.744 m and cannot be split. So the scene *states* which five tops go up and leaves the three Tecnares out.
  Still solver-solved — a `stack:` block re-dealt every build — with a human choosing the list
- **Two stacks cannot be symmetric here, so both answers ship.** I had this wrong when planning: I thought symmetry
  cost 3 cabinets. It costs 13 — devices with two or three units are kept together because a lone cabinet cannot be
  flanked, so a true pair drops every odd-quantity device including all the Tecnares and turbo tops, leaving 2 tops
  for a 28-cabinet rig
- **A new defect, found by the overlap sweep: aiming a mixed tops row places two cabinets coincident** — 391 mm
  inside each other, exactly one turbo top's width, and a wider `gap_m` barely moves it so it is not toe-in. None of
  these scenes aims its tops, and three carry a hand edit that regenerating would undo. `topRow()` builds the row
  correctly; the fault is downstream in distributing an aimed mixed row. Filed as TODO 28
- **A finding worth more than the scene: our 4 m crank stands cannot clear a combined rig.** Three stacks of 39
  cabinets reach 4.085 m, so a truss at 4.000 m hung its MACs 285 mm inside stack 1. `everything.yaml` stands the
  goalpost on GMSS's 5.2 m towers. The stands are fine for our own 3.125 m rig and not for both systems together.
  Filed as TODO 29


## [0.61.0] - 2026-08-12

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The stack fill proposed rows its own checker rejects.** `StackChecks::bearingProblems` requires every cabinet to
  keep `Gravity::MIN_BEARING` — a third of its width — on what carries it, while `StackSolver::fillWith()` sized each
  row from `perRow` and `max_width_m` and knew nothing about the tier below it. `perRow` is a **count, not a width**:
  six Achenbachs at 0.600 m is 3.700 m where four Flexys at 0.591 m is 2.424 m. With five device types that never
  bit; with nine it does, and `scene:stack` reported "no workable arrangement" for rows it had generated itself
- A row is now sized against the tier carrying it. The bound is `MIN_BEARING` rearranged rather than a new rule:
  `rowWidth ≤ S + (4/3)·w`, two thirds of a cabinet past the support each side, which is exactly the overhang the
  checker already permits. Capping harder would refuse rigs this repository ships — `full-rig-arc`'s Achenbach row
  stands 27 mm proud of its sub wall on purpose

### Notes

- **The stage still bounds the widening, and that ordering matters.** `rowSizeFor()` exists to widen a row rather
  than strand a one-wide pillar, and bounding that loop by the *support* instead of the stage turned twelve Flexys
  into six rows of two. A pillar is a worse failure than an overhang, so the support decides where a row starts and
  the stage still decides how far it may widen
- `ceilingFor()` returns `?float`, not `INF`. `perTier()` short-circuits on `null`, so handing it `INF` reached
  `(int)floor(INF)` — undefined in PHP — and came out as a row of one: every tier a pillar, in a stack with no
  stated width at all
- No regressions: 612 green, and all eight scenes that re-solve a `stack:` block on every build still build
- **One stack from all nine speaker types is still refused, and it is now provably impossible rather than a
  solver limitation.** Three middle subs pin a 1.825 m waist, so with the bearing rule nothing above it can exceed
  about 2.5 m — while eight tops in one row are 3.744 m, and tops cannot be split across rows. Every remaining
  problem names a top. A scene that states which tops go up solves cleanly; see TODO


## [0.60.0] - 2026-08-12

### Added

- **`specs/racks/rack-amp-12u.yaml`** (quantity 2) and **`rack-power-12u.yaml`** — the amp and distribution racks.
  The first non-speaker devices that needed **no new geometry at all**: a rack case really is a box
- `Category::Rack` gains a **`power`** subtype. It had `amp` / `network` / `shipping` and a distribution rack is none
  of them — a gap filed when the truss landed and closed by the spec that needed it
- Both racks in `detail-check.yaml` (sixteen of seventeen devices) and all three behind the sub wall in
  `full-rig-truss.yaml`, 43 mm off its back face — where amp racks actually live

### Notes

- **The amp rack's weight is derived from published figures for every amplifier in it**, which is unusual for a rack:
  3× Gisen MM14K (2U, 12 kg), 1× Behringer EP4000 (2U, 16.6 kg), 1× FP10000Q (2U, 12 kg) and 1× Gisen M60-series DSP
  (1U, ~13 kg) — **11U and 77.6 kg**. Two of those datasheets are in our own Drive under
  `Hardware/Amps _ Verstärker _ DSP/`, which is what TODO 11.1 meant by "documented in Drive"
- **The arithmetic explains why there are two amp racks, and it is not space.** Eleven rack units fits a single 12U
  rack by height. But 77.6 kg of amplifier plus ~30 kg of case is a **108 kg rack**, which two people cannot lift;
  split across two it is 69 kg each. So this is one rack type with `quantity: 2` and half the complement in each,
  rather than one full rack and one empty
- **12U of rails is exactly 533.4 mm** — 12 × 44.45, the one thing about a rack that is never in doubt. What is
  estimated is the case around them: ~0.700 × 0.600 × 0.750 m once shock mounts and lids are allowed, and the ~30 kg
  an empty 12U shock-mount weighs
- **The amplifiers get no specs of their own**, deliberately: invisible inside a closed rack, and five more boxes in
  `detail-check` that nobody can see. Their figures are in the rack's header and `docs/sources.md`, which is where a
  weight is meant to be traceable to. Filed as TODO 26
- **Two loose ends, both settled by looking at the rack** (TODO 25): the fourth amp is either the EP4000 or the
  Proline 3000 and the owner is not sure which — the Proline is 3U and 37 kg, which takes each rack to 79 kg — and
  "gisen md60" matches no Gisen product, though their M60-series DSP fits the description at 1HE and under 13 kg
- **`rack-power-12u` is the weakest spec in the repository** and says so: its case follows the amp racks and its 15 kg
  of contents is a guess at breakers, socket panels and cable, with no component list to add up. Filed as TODO 27,
  along with the fact that nothing in this schema can record electrical load — and the rig's amplifiers are rated in
  the tens of kilowatts

## [0.59.0] - 2026-08-12

### Added

- **`shape: moving-head`** — base, yoke arms and head. A rack really is a box and loses nothing by being drawn as
  one; a moving head drawn as a box is unrecognisable, and four hung on a truss would read as four flight cases
- **`shape: scaffold`** — four posts, bracing on all four sides and a platform deck. Open like a truss for the same
  reason, with the platform as the one solid part because that is what makes it a scaffold rather than a frame
- **`blender/lib/tubes.py`** — the capped-cylinder primitive extracted from `truss.py`, now shared by all three
  open-frame builders, plus `add_box` and `mesh_object` since all three finish the same way
- **`specs/lighting/gmss-mac-2000-performance-ii.yaml`** — GMSS's four Martin MAC 2000 Performance II. "4pcs Martin
  mac performance 2" identifies a catalogue product, so 408 × 490 × 743 mm and 39.5 kg are **datasheet figures** —
  the only GMSS gear that has any
- **`specs/stands/geruest-krause-ah7.yaml`** — the two Krause Plattformgerüst AH7. Footprint, platform height,
  200 kg capacity and ~84 kg are published; only the tube sections are estimated
- **`scenes/gmss-full-stack-truss.yaml`** — the GMSS stack, its 9 m truss on two towers, and all four MACs hung from
  it. The first scene here where anything hangs from anything, and the first use of `fly.id`: the report adds the
  truss and the fixtures into **one 195.2 kg bar total**, which is what a truss's capacity is checked against
- `full-rig-truss.yaml` gains both Gerüste, and `detail-check.yaml` both new devices — fourteen of fifteen
- `Category::Stand` gains a `scaffold` subtype. A work platform is a stand; `speaker-pole`/`tripod`/`riser` had no
  room for it
- `tests/Spec/ShapeTest.php` — `isCabinet()` is true for exactly box, trapezoid and wedge, and the enum's values are
  the strings `build_model.py` dispatches on

### Changed

- `plan_version` 2 → 3, and `build_model.py` now dispatches through an **`OPEN_FRAME_BUILDERS` table** rather than a
  chain of `if shape == …`. Three non-cabinet shapes made the chain the wrong shape; the next one is a table entry
- `Shape::isCabinet()` lists the shapes that *are* cabinets rather than negating truss, and its `match` has no
  default arm — so a new shape nobody has classified fails to compile instead of quietly inheriting a grille
- `SpecValidator` grew `validateShapeBlocks()`, one loop covering "required for its shape, refused on any other" for
  all three blocks, where `validateTruss()` had been doing its own copy of it

### Notes

- **Both identifications were checked rather than assumed.** "Martin mac performance 2" is the MAC 2000
  Performance II; the Gerüst is the Krause AH7, and **AH7 means *Arbeitshöhe* 7 m, which is platform + 2 m of a
  person's reach** — so the platform is at 5 m. A spec that put 7 m in the bounding box would clear a truss it does
  not clear, and `SpecValidator` now refuses a platform above its own frame
- **Martin's axis naming needs care**: it quotes "408 length × 490 width", where this repository's `width` is across
  the device — so 490 is the width and 408 the depth. Reversed, the yoke arms end up on the wrong faces
- **A rolled placement's `fly.height_m` is its top, not its bottom**, and getting that wrong left the fixtures
  hanging 800 mm under the truss instead of 70. A model is built bottom-center so its mesh runs *up* from the
  placement point; rolled 180° it runs *down*. The render showed it at once and no test could have
- **`provenance.dimensions` cannot say "outer box sourced, internals estimated"**, which both new specs need: the
  MAC's box is Martin's and its base/yoke/head split comes off photographs, and the Gerüst's footprint is published
  where its tube diameters are not. Both say so in prose only. Filed as TODO 24
- The plan split this as 0.59.0 then 0.60.0; it shipped as one release because the enum, the validator, the dispatch
  table and the shared tube primitive are common to both shapes and splitting them would have been artificial

## [0.58.0] - 2026-08-12

### Added

- **`shape: truss` — the first device geometry that is not a hexahedron, and the first that is not a loudspeaker.**
  A truss is mostly air, so drawing its bounding box would stand a solid wall where a 9 m span belongs and hide the
  whole rig behind it. `blender/lib/truss.py` builds chords and bracing from the stated tube sizes instead
- `geometry.truss` block: `chords` (2 a ladder, 3 a triangle, 4 a box), `chord_diameter_m`, `diagonal_diameter_m`
  and `bay_length_m`. Validated by extending `SpecValidator::validateShape()`, which already did exactly this job
  for the taper fields — required for its shape, refused on any other, and the tubes have to fit the stated box
- **`dimensions_m` stays the true bounding box**, and chord centres are derived *inwards* from it by one radius so
  the tubes touch its faces. That is what lets scene placement, the overlap sweep and the catalog's shipping volume
  go on reading one field without knowing a truss from a subwoofer
- Four specs in a new `specs/truss/`: `truss-f33-2m` (5 of ours), `truss-tower-4m` (2 telescopic stands),
  `gmss-truss-9m` and `gmss-tower-5m`
- **`scenes/full-rig-truss.yaml`** — the full rig under a goalpost, 8 m of truss on two 4 m stands. What
  `docs/scenes.md` has wanted for a while: "a venue rather than a void". The truss is placed with `fly`, whose
  docblock already anticipated it, and its 4.258 m top clears the arc below by 875 mm
- `detail-check.yaml` gains the truss segment and both towers — twelve of thirteen devices, with `gmss-truss-9m`
  deliberately left out and the reason stated in the file

### Changed

- `plan_version` 1 → 2. A plan carrying a truss block is one an older builder would silently draw as a box
- `build_model.py` skips what a truss has none of: grille, handle recesses, chamfer, drivers and the coverage cone.
  The origin shift and the rigging and estimated markers still apply — those are about the device, not the cabinet
- `Shape::isCabinet()` asks the question of the *shape* rather than the `Category`, because it is the geometry that
  decides: a rack is a `Box` and wants the same shell treatment, while a truss cannot take a grille whatever it is
  filed under

### Notes

- **The lengths are ours and the cross-section is a class standard.** "5x 2m three point truss segments" states the
  part that matters structurally and leaves the brand and section open, so those come from Global Truss F33 —
  chord Ø 50 × 2 mm, diagonal Ø 20 × 2 mm, 290 mm overall width. Prolyte X30 and Eurotruss FD32 are within a few
  millimetres, which is what makes it a class rather than a guess at a brand
- **The weight is derived from three published points, not interpolated by eye.** F33 is quoted at 6.4 kg / 1.0 m,
  8.2 / 1.5 m and 14.1 / 3.0 m, which fit `2.55 kg + 3.85 kg/m` — 6.40, 8.33, 14.10 against those. The 2.55 kg
  intercept is the end connectors, which is why short segments are heavy per metre. So 2 m is 10.3 kg
- **The cross-section is derived from the same figures**: chords Ø 50 inside 290 mm overall puts the centres on an
  equilateral triangle of side 240 mm, so the box is 257.8 mm tall by 290 across
- `truss-tower-4m` matches the Global Truss ST-132 on the stated description, so its **weight and heights are
  published** (25 kg, 4.0 m max, 1.8 m min, 100 kg load). What is estimated is its shape
- **The towers are placeholders and look it.** Both are a 0.203 m column, the folded base size; the folding
  outriggers are not drawn, and unfolded they spread to 1.499 × 1.499 m — the footprint that actually has to be
  kept clear. So the footprint those specs report is the mast's. Filed as TODO 10.4
- `bay_length_m` is the only number in a truss spec with no source at all; manufacturers publish tube sizes and
  weights but rarely the brace pitch. It changes the picture and nothing else
- Adding `blender/lib/truss.py` made every model stale, as `Staleness::blenderInputs` intends — one full
  `models:build` rebuilt all thirteen

## [0.57.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The GMSS cabinets were still too big beside ours, and the scale is now calculated rather than chosen.** The
  photograph gives **ratios** well — how tall a middle sub is against a turbo sub, how many to a column — and gives
  **absolute size** not at all, since nothing in the frame has a known length. Conflating the two is what went wrong
  in 0.52.0 (a ~60 mm logo read as ~3 mm/px) and again in 0.55.0 (matching the turbo sub to our Flexy)
- **The scale comes from the one physical fact GMSS stated: the middle sub's 18″ driver.** A 460 mm frame plus 18 mm
  walls plus ~15 mm of mounting margin each side means the baffle cannot be under 526 mm, so **below a set scale of
  0.751 the stated 18″ will not physically mount**. The set scale is **0.85**, fixed by the same driver: it puts the
  middle sub's baffle at **595 mm**, between the two 18″-loaded cabinets measured here — Flexy 591, Achenbach 600
- **One factor across all four cabinets**, which sets how big GMSS is without disturbing the proportions between its
  own cabinets — the part the photograph does support:
  - `gmss-turbo-sub` → **0.510 × 0.637 × 0.765**, 54 kg
  - `gmss-middle-sub` → **0.595 × 1.020 × 0.850**, 81 kg
  - `gmss-mid-bass` → **0.595 × 0.425 × 0.595**, 45 kg
  - `gmss-turbo-top` → **0.391 × 0.935 × 0.552**, 56 kg
  - stack 2.86 × 3.000 m → **2.44 × 2.550 m**
- **Reverted a wrong fix from this same session**: bringing the middle sub from 1.200 to 1.000 while leaving the
  others alone. It treated the one thing the photograph establishes — how the cabinets compare with each other — as
  the thing to change. A uniform factor was the correction

### Notes

- The GMSS cabinets are now **smaller than ours one for one**, which follows from the driver calculation rather than
  being an oversight. `gmss-turbo-top` is where the set and its outside evidence disagree: left to the Turbosound
  TMS-4 alone it would be 0.502 × 1.143 × 0.730, but it belongs to a set with one scale. A measurement would settle
  it; until then internal consistency wins, because the photo supports proportions far better than absolute size
- `docs/sources.md` gains a *What the photograph can and cannot give* section with the driver arithmetic and all
  three scale attempts side by side

## [0.56.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`both-systems-per-owner-center.yaml` was the one scene 0.55.0's rescale did not fully reach.** Its cabinets were
  right — a `stack:` block is re-solved on every build, so the rig always follows the current specs — but two things
  around it were not:
  - its **baked `at:` positions** were computed when the GMSS stacks were wider, so the 0.5 m clearance the
    generator intended had quietly become more than that
  - its **comment table still described the pre-rescale solve**: rows of 3.26 m that now build as 2.55, and a
    five-row GMSS stack that now builds as four
- Regenerated, and generated with **`--gap=0.05`** rather than carrying a hand-edited gap for one stack. That
  removes the hand edit 0.55.0 introduced, so the file is now internally consistent — its comments match what it
  builds, and `scene:stack` can rewrite it without reintroducing an overlap. 7.96 × 1.06 m, 4.399 m tall
- The aimed-row overlap recurred on regeneration at the new sizes — 16.2 mm between two GMSS tops — which is
  TODO 22 confirmed live rather than anything new

### Notes

- **A generated scene's comment table goes stale while the scene itself stays correct**, because `scene:stack`
  writes the row summary once and the compiler re-solves the block on every build. The rig is never wrong; the
  documentation beside it can be, which is worse than none because it reads as authoritative. Filed as TODO 23
- 0.55.0's note that this file "carries a hand-edited `gap_m: 0.05`" no longer applies — that is what this release
  removes

## [0.55.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Every GMSS cabinet was much too big, and the both-systems scene is what found it.** 0.52.0–0.54.0 sized them off
  the photograph, by reading the blue illuminated logo on the sub faces as a ~60 mm badge and deriving ~3 mm per
  pixel. Standing that rig beside our own made it obvious: fourteen GMSS cabinets came out a wider *and* taller wall
  than our twenty-three, which is not credible. A photograph with no scale reference cannot fix absolute size, and
  that 60 mm assumption was carrying all of it
- The set is now sized by **comparison with cabinets somebody has actually measured** — chiefly
  `flexy-folded-horn-hybrid` at 0.591 × 0.763 × 0.964, the same class of single-18″ folded horn as a GMSS turbo sub:
  - `gmss-turbo-sub` 0.800 × 0.950 × 0.900 → **0.600 × 0.750 × 0.900**, 84 → 66 kg
  - `gmss-middle-sub` 0.900 × 1.350 × 1.100 → **0.700 × 1.200 × 1.000**, 125 → 100 kg
  - `gmss-mid-bass` 0.900 × 0.600 × 0.850 → **0.700 × 0.500 × 0.700**, 68 → 53 kg
  - `gmss-turbo-top` 0.460 × 1.150 × 0.700 → **0.460 × 1.100 × 0.650**, 70 → 66 kg
  - The stack goes 3.66 × 3.450 m → **2.86 × 3.000 m**; owner gmss 1393 → 1132 kg
- **The turbo top barely moved, and that is the tell**: it was the one GMSS cabinet whose size never came from the
  photograph, but from the Turbosound TMS-4's published figures
- **An aimed row is wider than a straight one, twice over.** Every cabinet in an aimed row toes in on the focus from
  its *own* position, so the row does not share a yaw, and a rotated box occupies `width × cos θ + depth × sin θ`
  across x. In `both-systems-stereo` that first put two tops 4 mm into each other — fixed with `gap_m: 0.040` where
  every other row uses 0.020 — and then put the outer top 27 mm into the column beside it, fixed by standing that
  scene's columns 1.130 m off the stack centre where the GMSS scene uses 1.080

### Added

- **Three scenes putting both sound systems together**, where nothing did before:
  - **`both-systems-side-by-side.yaml`** — our `full-rig-arc` beside the GMSS stack, each copied from its own scene
    with nothing changed but the x it is centred on. 8.48 m wide, with a 1.920 m walkway between the two rigs. This
    is the file that caught the oversizing, and it is the only place an estimate stands next to a measurement
  - **`both-systems-stereo.yaml`** — both systems aimed at one focus as a stereo pair, GMSS stage left. 10.33 m
    wide. Two numbers in it are decisions rather than derivations and are called out as such: the 7.000 m between
    stack centres and the 15 m focus
  - **`both-systems-per-owner-center.yaml`** — generated by `scene:stack --per-owner`, a stack solved from
    constraints for each of the three owners. 41 cabinets, 4.398 m tall. The solver deals GMSS into five rows, a
    genuinely different answer from the photographed arrangement
- `detail-check.yaml` now holds all **nine** devices. It is the "one of every device" contact sheet and had been
  left at the five sdwa5/sepp cabinets when the GMSS specs arrived

### Notes

- **`scene:stack` has the same aimed-row bug and it is not fixed here** — filed as TODO 22. It spaces an aimed row on
  the cabinet's flat width, so a stack far enough off the centre line emits a scene whose own cabinets overlap: the
  generated GMSS stack sat 2.65 m out and two of its tops were **22.8 mm inside each other**.
  `both-systems-per-owner-center.yaml` carries a hand-edited `gap_m: 0.05` for that stack, which regenerating the
  file would undo. Not GMSS-specific: any `--per-owner` or `--stacks=N` rig can hit it
- Every overlap above was caught by the sweep in `ShippedScenesTest`, none of them by eye — the renders looked fine
- **The side-by-side render shows the provenance without being asked to.** Our cabinets come out with cones, horn
  mouths and grille insets because their specs carry an `audio.layout` read off CAD or a datasheet; the GMSS ones are
  featureless blocks, because nothing is known about their baffles and the repository will not draw a driver it
  cannot cite. Which system is measured and which is reconstructed is visible at a glance
- The comparison now reads: ours 3.700 m wide and 3.125 tall in 23 cabinets, GMSS 2.860 and 3.000 in 14 — so their
  boxes are bigger one for one while the rig as a whole is narrower

## [0.54.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The mid-bass cabinets are the foot of the outer columns, not a row in the middle.** In the photo each column
  stands on a short wide box carrying the same recessed oval logo as the cabinets above it. 0.53.0 read those as a
  fourth turbo sub per column and 0.52.0 as plinths; they are the small subs, they are on the ground, and the
  turbo subs stand on them
- **Six turbo subs, not eight.** GMSS owns eight and only six are in the photograph — three to a column — so the
  scene builds the six that can be seen. `scene:build` warns about over-using gear and never about under-using
  it, so a rig that spends part of the inventory is a legitimate arrangement
- **The middle bay's second row was empty**, which left the tops 0.950 m below the column shoulders. It now holds
  a third middle sub

### Added

- `gmss-middle-sub` quantity 2 → 3. The third is laid **on its side** (`roll_deg: 90`) across the pair on the
  ground — the one genuinely speculative placement in the rig, and the reason to believe it is that the same
  quarter turn settles two independent things that were not aimed for:
  - **it carries the tops.** The row of three is 1.420 m; on a cabinet standing up there would be 0.900 m under
    it and each outer top would hang half off, where rolled there is 1.350 m and every top keeps 92% of itself on
  - **the heights come out level.** Tops finish at 3.400 m against the columns' 3.450 — 50 mm, which is what the
    photograph shows. Standing that cabinet up instead puts them at 3.850 m, 400 mm proud, which it does not

### Notes

- The rig is now 14 cabinets, 1225 kg, 3.450 m tall, footprint 3.66 × 1.10 m
- What the photo shows in that second row is a cross-braced horn mouth, wider than it is tall. A middle sub on its
  side is the reading that fits the shape; it could equally be a cabinet type GMSS never listed
- Still guessed: every depth, since the side faces are foreshortened past reading; the counts of middle subs and
  mid-bass cabinets, which GMSS never stated; and the absolute scale, which still rests on reading the blue logo
  as a ~60 mm badge

## [0.53.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The three GMSS tops are one row**, side by side on top of the middle section. 0.52.0 split them — one on each
  outer column and one in the notch — which produced a silhouette the reference photo does not have
- **Every GMSS cabinet was far too small, and the subs were the wrong shape.** They were estimated as ~0.700 m
  near-cubes; the photo shows them clearly taller than wide, about 4:5. The stack came out 2.66 m wide and 3.94 m
  tall — a narrow tower — where the photograph is a **broad wall**. It is now 3.46 × 1.10 m and 3.800 m tall
  - `gmss-turbo-sub` 0.700 × 0.700 × 0.850 → **0.800 × 0.950 × 0.900**, 66 → 84 kg
  - `gmss-middle-sub` 0.600 × 0.800 × 0.950 → **0.900 × 1.350 × 1.100**, 74 → 125 kg
  - `gmss-mid-bass` 0.800 × 0.550 × 0.750 → **0.900 × 0.600 × 0.850**, 58 → 68 kg
  - `gmss-turbo-top` 0.500 × 1.140 × 0.700 → **0.460 × 1.150 × 0.700**, 70 kg unchanged
  - The rig's weight goes 1002 → 1268 kg on the back of it
- **The two low boxes at the foot of the outer columns are cabinets, not plinths** — each carries the same
  recessed oval logo as the cabinets above it. Reading them as risers left the scene two subs short of the stated
  eight; four per column is now exactly the eight GMSS listed

### Changed

- The mid-bass pair stands side by side rather than stacked. At 0.800 wide a pair would have driven into the
  columns, which is why 0.52.0 stacked them; at 0.900 on the 1.820 m bay they sit inside it with 20 mm to the
  columns, and their flat 1.820 m top is what gives the three tops one row to stand on

### Notes

- **The whole scale rests on one assumption**: there is no scale reference in the photograph, so the anchor is the
  blue illuminated logo on the sub faces, taken as a ~60 mm badge measuring about 20 px — roughly 3 mm per pixel
  at the near column. Every dimension is downstream of that 60 mm. **One real measurement off any GMSS cabinet
  would correct the whole set in a single pass**
- Depths are the least certain of the three axes and did not move much: the side faces are foreshortened past
  reading, so they are inferred from the cabinet class rather than measured off the photo at all
- **One thing still disagrees with the photo:** the columns finish at 3.800 m and the tops at 3.100, so the tops
  sit 0.700 m below the shoulders where the photograph shows them closer to level. Something in the middle
  section is still too short — most likely it stands on a riser, and the low boxes under the middle bay are not
  modelled because nothing is known about them

## [0.52.0] - 2026-08-11

### Added

- **GMSS (Gena Made Sound System) as a second documented system** — four speaker specs, their models,
  `scenes/gmss-full-stack.yaml` and quick-preview renders from three cameras. 15 cabinets, 1002 kg, 3.940 m tall,
  a 2.66 × 0.95 m footprint
  - `gmss-turbo-sub` (8, 3000 W RMS), `gmss-middle-sub` (2, 18″, 1600 W RMS), `gmss-mid-bass` (2, 2× 700 W RMS),
    `gmss-turbo-top` (3, 2500 W RMS)
- The scene reproduces the photo's silhouette: two outer columns of four subs to 2.800 m with the middle section
  stopping at 1.900, which is the notch that makes the mid-bass horn mouth visible at all. Every cabinet is fully
  supported — the mid-bass pair is stacked rather than side by side because side by side they would drive 180 mm
  into the columns, not merely overhang
- `docs/sources.md` gains a *GMSS is estimated end to end* section with the derivation of every number

### Notes

- **Not one GMSS dimension or weight is sourced.** What GMSS supplied was counts, power ratings and one site
  photograph with no scale reference; searching for the system online returns nothing, as expected for a locally
  built rig rather than a product. All four specs are `provenance: estimated`, `catalog` lists them in the
  measuring backlog and `scene:build` warns that the positions rely on un-measured cabinets
- Dimensions are reconstructions scaled off this repository's own measured cabinets — `achenbach-18` and
  `flexy-folded-horn-hybrid` for the subs — and off the Turbosound TMS-4's published 1143 × 502 × 730 mm and
  74.8 kg for the tops. The TMS-4 is a size sanity check and **not** a `clone_of`: GMSS never said these are
  Turbosounds, and 2500 W RMS is five times a TMS-4's rating
- **No passbands, no crossover points and no driver sizes** beyond the middle sub's stated 18″. Those are the
  numbers this repository refuses to invent, so the `audio` block is absent from three of the four specs
- Round numbers throughout, deliberately: 0.700 is honest about being a guess where 0.7124 would pretend to be a
  measurement
- The counts of the middle subs and mid-bass cabinets are read off the photograph, not stated. The plinths under
  the whole stack are real and not modelled. Truss, towers and the Martin MAC lights were out of scope

### Changed

- `SceneStackCommandTest` names the collective's own gear via a new `OWN_GEAR` constant where it used to rely on
  "every spec in the repository". That stopped meaning one rig once a second system was documented, and
  `--per-owner` now yields three stacks rather than two — which is the case that option exists for

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- `docs/scenes.md` still described `end-fire-lattice` as `count: [2, 3, 2]`; it has been `[3, 2, 2]` since 0.48.0

## [0.51.0] - 2026-08-11

### Changed

- **One `build/renders/built-with.json` for the whole render tree**, replacing 0.50.0's hidden stamp beside every
  picture — which in practice meant several hundred hidden files interleaved with several hundred PNGs. Entries are
  keyed by each render's path relative to the manifest (`studio/full-rig-side.png`), so they stay readable and stay
  true if the build tree moves. Recording one render leaves every other entry alone, and the render stages are
  sequential, so there is no writer to race
- `Staleness::manifestIn()` replaces `stampFor()`; `settingsChanged()` and `recordSettings()` take the manifest as
  their first argument. A render **absent from the manifest** is what now counts as changed, and a manifest that
  cannot be parsed makes everything in its tree redraw rather than be trusted

### Notes

- 57 stamps written by 0.50.0 were removed from `build/`; they are gitignored and every render they described is
  recorded in the manifest on its next pass. Nothing needs `--force`

## [0.50.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A render was never redrawn when only the *settings* changed.** Staleness compared a PNG's mtime against its
  scene `.blend` and the Blender scripts, and no mtime can answer "was this drawn the way I am now asking for" —
  nothing on disk moves when the default resolution is raised or `--lighting=stage` is passed. The inputs really
  had not changed, only the instructions had. Since only the camera and the scene id appear in a PNG's filename,
  that covered nearly every setting there is: `scene:render --lighting=stage` reported a studio render as up to
  date and returned without drawing anything

### Added

- **`scene:render` records what each picture was drawn with**, in a hidden `.<name>.png.built-with.json` beside
  it, and compares on the next run — camera, lighting, samples, resolution, ground plane, aim mode. Change one and
  that render redraws; change none and it does not
- `Staleness::settingsChanged()` / `recordSettings()` / `stampFor()`, as a second rule beside `outOfDate()` rather
  than a class of their own, since "is this current" should stay one question with one answer. Beside each output
  rather than in a manifest so nothing is read-modify-written and a moved or deleted PNG cannot leave a lie behind
- The aim mode now resolves *before* the freshness check, because it is part of what is being asked for

### Notes

- **This supersedes 0.49.0's note that a one-off `build:all --force` was needed.** A picture with no stamp counts
  as changed, so every render still on disk at the old 1600×900 redraws itself on the next `build:all` and carries
  a stamp afterwards. Same cost either way — the difference is that nobody has to know to ask
- The stamp is written only after Blender succeeds. One written ahead of a failed render would claim the old
  picture was made with the new settings, which is the single way this could rebuild too little. A stamp that
  cannot be written is a warning, not a failure: the picture is good and the only cost is one extra re-render
- Unreadable stamps are treated as changed rather than trusted

## [0.49.0] - 2026-08-11

### Changed

- **`build:all` renders every variant by default.** Four lighting presets × two aim modes — eight passes into
  eight folders under `build/renders/`. These were opt-in flags, and every invocation in the repository passed
  both, so the useful behaviour was the one nobody got by default
- **Render quality is Full HD at 128 samples**, raised from 1600×900 at 64, which was the only quality there was

### Added

- **`--quick-preview` (960×540, 16 samples) and `--high-quality` (3840×2160, 384 samples)** on `scene:render` and
  on `build:all`, which forwards the level to every pass in its sweep. Three levels exist because the same command
  does two jobs: checking a rig is arranged the way you meant, and producing something to look at. An explicit
  `--samples` or `--resolution` wins over a level, so the levels are a shorthand rather than a constraint — that
  matters for the one thing a level cannot say, a 4K frame at 16 samples to check framing. Both levels at once is
  refused rather than given a precedence, since there is no reading of "the least that answers a question, and
  also the most worth spending"
- **`--aim-lines=none|tops|all` on `build:all`**, mirroring `--lighting`: naming one narrows the sweep to it. An
  unknown value is refused *before any stage runs*, because `--dry-run` runs nothing and a real sweep would
  otherwise spend every earlier stage before `scene:render` caught the typo
- `RenderPlan::quality()` resolves a level to its samples and resolution, next to the constants it names, so a
  level cannot drift from the numbers it is meant to stand for

### Removed

- **`--aim-line-variants` and `--lighting-variants`**, rather than kept as no-ops — a flag that silently does
  nothing is worse than Symfony's own "unknown option". `--lighting=X` / `--aim-lines=X` narrow instead

### Notes

- The two changes compound: eight variants at 2.9× a frame makes a full sweep about **23×** what it used to cost.
  Intended, but worth knowing before starting one on a laptop — `--quick-preview` brings the same sweep back under
  today's cost
- **Raising the default does not make the existing renders stale.** Staleness is by mtime against a render's scene
  and scripts, not against the settings it was drawn with, so every PNG on disk is still "current" at the old
  1600×900. One `build:all --force` re-renders them. Folding the settings into the freshness key is TODO 20

## [0.48.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A tier could not be flanked from below when the promotion used up every spare cabinet.** The rule asks "if I
  take these Flexys away, is what is left underneath wide enough to hold the row up" — and when it takes *all* of
  them there is no row of them left, so `lastRowWidth` answered `0` and that read as "standing on nothing". The
  row is in fact standing on the **bottom row**, which at 3.054 m is wider than the row being promoted. It now
  hands the arrangement to `StackChecks::supportChecks`, which knows what is actually underneath and passes it
  with 4 mm to spare. This is what kept the Achenbachs in a row of their own above a 1.202 m pair of Flexys

### Added

- **`scenes/stacked-two-flat-center.yaml`** — the same 24 cabinets as `stacked-two-center` with the Achenbachs
  sharing the second row: `1× flexy + 3× achenbach + 1× flexy`, a flat wall of `3.054 / 3.062` in two rows
  instead of a stepped `1.832 / 2.424 / 1.840` in three
- Both are shipped because neither is better. The interface height decides which: asking for 2.0 m keeps the
  Achenbachs separate and the tops at 2.277 m, over a standing crowd; asking for 1.6 m lets two Flexys move up
  beside them and drops the tops to 1.677 m. Six Flexys either form a row under the Achenbachs or lend two of
  themselves to flank them, and there is no third arrangement

## [0.47.1] - 2026-08-11

### Changed

- `scenes/end-fire-lattice.yaml` reshaped from `[2, 3, 2]` to `[3, 2, 2]` — three columns across, two deep, still
  two tiers up. Footprint 1.81 × 2.16 m where it was 1.20 × 3.36, and twelve Flexys either way. Depth is what buys
  rear rejection, so two deep cancels less than three would; the twelve cabinets go on width and height instead

## [0.47.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A surface below an overhang was being treated as a hazard instead of a safety net.** It can only ever catch a
  cabinet that tilts; it cannot make one less stable than the same cabinet cantilevered over thin air. 0.46.0's
  settle-angle rule refused a Flexy row standing on a mixed Flexy-and-SKRAM bottom row while allowing the
  identical row on a lone SKRAM — the same cabinets, the same support, the same 49.9 % bearing, and the only
  difference was that something harmless sat 151 mm below. The settle angle is gone
- **The bearing floor moved from a half to a third**, which is where the cases actually are. A half falls exactly
  between the two arrangements it must separate: a Flexy on a SKRAM with the rest hanging outward bears 49.9 %,
  and a 2-way perched on a 163 mm shoulder bears 1.2 %. Forty times apart, and a half refused both

### Changed

- **`stacked-two-center`'s bottom row is now `2× flexy + 1× skram + 2× flexy`** — Flexys and the SKRAM side by
  side, upright, one SKRAM per stack. It also stopped being a tower: **3.280 m** tall against 0.46.0's 4.856 m,
  in four tiers rather than six
- `stacked-center` is 4.072 m instead of 4.690 m, and every rig still places what it did

## [0.46.0] - 2026-08-11

### Added

- **`src/Scene/Stability.php` — a row is one body.** A tier tips when its *combined* centre of mass, weighted by
  the `weight_kg` every spec carries, falls outside what carries it. The rule this replaces refused a row by half
  a millimetre, because "half the outer cabinet off the edge" turns out to *be* the per-cabinet centre-of-mass
  rule: 916.5 mm out against a support edge at 916.0
- **A settle angle replaces the bearing fraction as the refusal.** A cabinet whose own weight is off its support
  and whose overhang catches a lower surface tilts until it touches — `atan(drop / overhang)`, refused past 5°.
  It is the only measure that separates the cases: 1.7° for a Flexy left 19 mm proud, 27° for one half off with a
  151 mm drop, 19.5° for a 2-way on a 163 mm shoulder. A footprint fraction reads all three as "about half off".
  **The gate matters as much as the formula** — a cabinet whose centre is over its support sits flat whatever is
  beside it, and without that a 20 mm sliver over a 19 mm step reads 43.5°
- **A device is never split so that a row ends up one wide** when a wider row still fits the stated width. Three
  Achenbachs at two per row are dealt `2 + 1`, and a one-wide sub tier is refused as a pillar — while all three in
  one row are 1.840 m and fit the stage with two metres to spare. The search's row count is a preference, not a
  constraint, so it gives way to its own rule

### Changed

- **The SKRAMs now split one per stack in the upright two-stack rig as well as the turned one**, which is what
  the whole change was for. `stacked-two-center` and `stacked-two-turned-center` are both 12 + 12 cabinets with a
  SKRAM in each stack, and they are genuinely different rigs rather than two names for one
- `stacked-center` places all 25 cabinets — the SKRAMs are no longer dropped from the one-stack rig
- **The cost is height.** Splitting the SKRAMs upright is only possible on narrow rows, so `stacked-two-center`
  comes out 4.856 m tall on a 0.61 m base against the turned rig's 3.399 m. The turned form is the better rig;
  the upright one now exists and is honest about its shape
- `ShippedScenesTest` asserts that every cabinet has *something* under it in both axes rather than half of it.
  The tilt is the solver's question and is pinned in `GravityTest`: re-deriving it from world boxes alone needs
  "the tier immediately below", and a first attempt searched everything downwards, found the floor under a 20 mm
  overhang and called a properly built rig 89° out of level

## [0.45.1] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A generated rig said nothing about a device it kept whole.** `stacked-two-center` shows both SKRAMs in one
  stack and gave no hint that one-per-stack had been tried and refused, which is the single thing anyone asks
  about a split rig. The header now says so, and names the way out: turning them makes the split work, which is
  what `stacked-two-turned-center` does — one SKRAM centred in each stack, twelve cabinets each
- Notes carry their own verb (`LEFT OUT, …` / `KEPT TOGETHER, …`) instead of the writer prefixing everything with
  "LEFT OUT", which read as a contradiction on a device that was kept rather than dropped

## [0.45.0] - 2026-08-11

### Added

- **`scenes/full-rig-arc-turned.yaml`** — the last rig without a turned counterpart. The wall goes
  4.678 × 1.182 against 3.646 × 1.526, which cuts two ways and the file says so: the Achenbach row now sits well
  inside the wall rather than 27 mm proud of it, and the arc's bottom edge drops from 2.126 m to 1.782 m, below
  head height. A better-supported wall aimed lower, not a free win. **Its fills needed nothing restated** — the
  arc moved and `align.outside` re-solved the 20 mm, which is exactly what the hand-picked `width_m: 2.60` could
  not do
- **`scenes/end-fire-turned.yaml`** — the four-by-three end fire on its side, mirrored. Rolling is about the
  front-to-back axis, so the 1.20 m that tunes the column to about 71 Hz is untouched; only the wall's shape
  changes
- **`scenes/end-fire-lattice.yaml`** — end fire as a **block**: `count: [2, 3, 2]`, two across, three deep, two
  tiers up. Twelve Flexys, so unlike the four-by-three form it is buildable as written. An end-fire array scales
  in x and z and is tuned in y, and one lattice says all three
- **`scenes/detail-check-turned.yaml`** — the contact sheet on its side, which is where a wrong `rotate_deg`
  hides: upright, an error in which face is which looks plausible; rolled, it puts the horn mouth at the floor.
  The SKRAM's own orientation is still unconfirmed against the real cabinet, and this is the cheapest way to
  eyeball it

## [0.44.0] - 2026-08-11

### Added

- **Near-field fills go to the outer stacks, on the inner side, aimed at the near focus.** Two 2-ways across three
  stacks come out one, none, one — a top too few to give every stack one is a small box and belongs at the edges of
  the rig, where a *sub* too few to go round belongs in the middle with the weight. The widest top is the long
  throw and keeps the placement's aim; every narrower one is fill and takes `aim: near`
- **A fill is solved `align.outside` the nearest long-throw run on its own side.** What a nominal gap cannot do:
  two tops aimed at one focus from different x take different *yaws*, the outer one turns more, and it turns *into*
  its neighbour. At the far focus that ate 7.9 mm of the stated 20 in the one-stack rig and bit **1.7 mm** in a
  three-stack rig's right stack; at the near focus, toed in 36°, it bit **117 mm**
- `align.side` — a lone cabinet has no offset sign to read, so nothing else can say which way outboard is

### Changed

- **`inset_m` is a minimum, not a target.** Cabinets already further out are left where they are rather than
  pulled back in: a fill gravity re-seated onto a shoulder for its bearing sits 517 mm clear, and dragging it to
  20 mm would undo a repair made for a reason
- A top tier emits its **long throw first**, because `outside` can only name a placement that already exists. Only
  the order changes, not which segment is which
- The one-stack rig's fills move 12.4 mm out. `StackTest` recorded that toe-in was already eating the stated 20 mm
  down to **7.9 mm** of real air; it is now 20 mm of real air
- All six `stacked-*` rigs regenerated

## [0.43.0] - 2026-08-11

### Added

- **`align.outside` — the room past a placement's outer faces.** `across` and `inside` are both widths a tier has
  to *span*; this is a clearance it has to *keep*, beyond somebody else's edges. It is the third thing a fill can
  be solved against and the one `align` could not express, which `full-rig-arc` had carried as a comment for as
  long as the file existed. Same `StepSolver` bisection against the same rotated boxes, with a second objective
  rather than a second solver: the free span between my outermost cabinets, less the obstacle's extent, halved
- Refused rather than solved when the cabinets already clear by more than asked — pulling them *in* would need a
  bracket below the starting parameter, and every mode's parameter is bounded below by zero

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`full-rig-arc`'s `width_m: 2.60` was 18 mm looser than its own comment claimed.** The file said "2.60 puts
  them about 20 mm clear"; measured, that spacing left **37.8 mm**. It now states `outside: tops, inset_m: 0.020`
  and the solve finds the spacing, so the fills sit 18 mm further in — and the last stated number in the scene is
  gone

### Changed

- `SceneCompiler::spanOf()` split into a shared `placedFor()` walk plus two objectives, so the clearance solve
  measures exactly the geometry the width solve does

## [0.42.0] - 2026-08-11

### Changed

- **`src/Scene/StackChecks.php` extracted from `StackSolver`** — 1110 lines down to 850, with the 257 lines that
  judge a solved rig in a file of their own. The seam is clean: the two share nothing but a `list<Tier>` and the
  `Stack` it came from, one searching for an arrangement and the other judging one. Its docblock now states what
  each of the four checks actually catches, because more than once a rule has been written here to catch something
  another already covered from a different angle — bounds are arithmetic, `supportChecks` measures a *tier*
  against the tier below, `bearingProblems` a *cabinet* against what it personally landed on, and
  `pillarProblems` the shape of the whole rig
- Two tests dropped their `ReflectionMethod` now that the checks they exercise are public

No behaviour change: the whole suite passes untouched, which is the only verification an extraction needs.

## [0.41.0] - 2026-08-11

### Added

- **`stack.mirror` — one stack of a side-by-side pair is now the mirror image of the other.** An unmirrored pair
  was the same rig built twice: both SKRAM mouths facing the same way, both tops rows in the same left-to-right
  order, and the two 2-ways therefore on the same side of their stacks rather than both facing the middle. It
  measures identically to a mirrored pair, which is why nothing caught it. Correct-by-default for any
  `--stacks=N`, not an opt-in
- `Tier::flipped()` — reverse a row's segments and hand every quarter turn the other way. Sibling of
  `Tier::mirrored()`, which splits a row at its own middle instead; a symmetric row is its own mirror image, so
  the flip only shows where a row is lopsided
- `StackEntry::$aim` — a tier may name a focus of its own. A stack applies one aim to every top it carries, which
  is wrong for the row every rig builds: the long throw wants the far focus and the fills are near-field. Since a
  mixed row expands into one placement per segment anyway, each can carry its own
- **All six generated rigs** (TODO 2): one, two and three stacks, upright and on their sides —
  `stacked-center`, `stacked-turned-center`, `stacked-two-center`, `stacked-two-turned-center`,
  `stacked-three-center`, `stacked-three-turned-center`. The three-stack rigs place all 25 cabinets

### Changed

- `stacked-center.yaml` no longer pins `count: 4` on its Achenbachs, so it tracks the spec — and
  `stacked-6-achenbach-center.yaml`, which only existed because the pin froze the original, is deleted. One
  generated rig per arrangement
- **The one-stack rig now leaves the SKRAMs out at a 3.70 m stage.** Six Achenbachs make a row exactly 3.700 m
  wide, so including the SKRAMs narrows the Flexy row beneath it to 2.424 m and a 3.700 m row overhangs it by
  638 mm each side. Not a regression in the solver — the stage is the constraint. `--max-width=5.50` takes all 25
  in one stack, and the three-stack rig takes all 25 at 3.70

## [0.40.1] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`scenes/full-rig-arc.yaml` stood four Achenbachs where six are owned** — the one hand-written three-tier rig
  0.40.0 missed, because it was the only one asking for the real count rather than over-claiming, and correcting
  the spec turned that from right into short. It said so in three places too ("Four is what we own, so four is
  what stands here"). All five hand-written rigs now stand six
- Its notes pointed at `full-rig-all-speakers.yaml`, deleted several releases ago, and claimed that scene
  refuses the SKRAMs — the solver finds a place for them

### Changed

- The Achenbach row in `full-rig-arc` goes from 2.460 m to 3.700 m, so it sits 27 mm proud of the 3.646 m sub
  wall each side — the same 27 mm `full-rig-all-tops` carries, with the outer cabinet keeping 95.5 % of itself
  on the wall. The arc above is untouched: the row's top face is 2.126 m whether four or six stand in it.
  **Nothing warns about the overhang**, and the file now says why — the support check belongs to `stack:`, and a
  hand-written row is never support-checked; `ShippedScenesTest`'s bearing sweep is what covers it

## [0.40.0] - 2026-08-11

### Added

- **`scenes/stacked-6-achenbach-center.yaml`** — the corrected one-stack rig with all six owned Achenbachs,
  generated bare (no `count` override) so it tracks the spec going forward

### Changed

- **`scenes/stacked-three-center.yaml` and `scenes/stacked-two-center.yaml`** regenerated with the real
  Achenbach count: each stack now gets two more, since `intdiv(6, 3)` and `intdiv(6, 2)` both split evenly
  and no longer trip the odd-remainder special case

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`specs/speakers/achenbach-18.yaml` `quantity` was 4; six are actually owned.** Every bare `stack.from`
  entry reads its count from this spec, so `stacked-center.yaml`, `stacked-three-center.yaml` and
  `stacked-two-center.yaml` were each two Achenbachs short of the real inventory
- `scenes/stacked-center.yaml` pinned to an explicit `count: 4` override on its Achenbach entry so it keeps
  shipping today's four-Achenbach rig unchanged, now that the spec it used to read six from bare
- Three hand-written scenes still said "Six Achenbachs against four owned" in their notes. Six is the whole
  holding now, so calling for six is no longer an over-claim

## [0.39.0] - 2026-08-11

### Added

- **`scenes/stacked-two-center.yaml` and `scenes/stacked-three-center.yaml`** — the two- and three-stack
  all-speaker rigs. Two stacks: identical mirrored halves of eleven, one SKRAM centred in each, 22 of 23
  cabinets and 5.46 m wide. Three: the SKRAM pair in the middle stack as asked, all 23 placed, 5.94 m wide
- **`--stacks=N` mirrors if it can.** Two ways of dealing the inventory out are tried — every device split
  evenly, or a device kept whole in the middle stack when there are too few to go round — and the one that
  stands up **more cabinets** wins, an even split breaking a tie. Scoring by cabinets rather than by "did it
  solve" is what makes it work: an even split that cannot be carried does not fail, it silently returns a rig
  without the offending device, so a naive fall-back would take a mirrored 20-cabinet rig over a 22-cabinet one
- **Turning the horn subs is what lets the SKRAMs be split.** Upright they cannot be: a SKRAM is 610 mm and a
  Flexy 591, so a row above an odd-count row lands on the *joints* below and gets 49.9 % of itself on the taller
  cabinet, cantilevered over a 151 mm drop. On its side a SKRAM is 914 × 610 and carries a Flexy row squarely
- The remainder of an even split is left out and **named**: three M2122s over two stacks are 1 + 1 with the third
  reported, because 2 + 1 makes a stereo pair that is not a pair
- The generated-rig sweep covers the turned two- and three-stack rigs

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- `inventoryFor()`'s closure did not capture the flag it branched on, so both split strategies behaved
  identically. PHP only warns about an undefined variable in a boolean, and `!$undefined` is `true`

## [0.38.0] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Multi-stack scenes did not rebuild as the rig they reported.** A `stack:` block carries constraints and a
  device list, and the compiler re-solves it on every build against each spec's own `quantity` — so a rig whose
  header described two stacks of eleven was *built* with both stacks holding all twenty-three cabinets: two
  3.6 m walls 0.5 m apart, **561 mm inside each other**. Every `--stacks` and `--per-owner` scene ever generated
  was wrong when built. The writer now states each stack's share as `count:`; an unsplit rig keeps the shorthand
- **A sub tier narrowed to a single column is refused.** The interface chase had no floor, so on a pile it could
  not otherwise lift the search kept narrowing and `--per-owner` gave `sdwa5` a rig 1.8 m across and 4.9 m tall.
  Every existing rule passed it, because a column is never more than half a cabinet wider than the column
  beneath it. `--per-owner` now gives that owner a 2.46 / 3.04 / 3.04 / 1.54 m rig reaching 2.44 m
- **The generated-rig bearing sweep multiplied its two axes.** A Flexy is 964 mm deep and a SKRAM 813, so a Flexy
  standing squarely on a SKRAM covers 84 % of its own depth however well it is centred, and the product read
  43 % for a properly stacked cabinet. Measured per axis now — cabinets of different depths stack in every rig
  there is; what matters is that neither axis is more than half off
- **The outboard top seating could seat its groups into each other**, on a support whose runs sit closer together
  than the segments being given to them. It falls back to the contiguous row, which is the honest answer for a
  layout that exists as a repair
- **A cabinet with nothing under it at all is now an error** rather than a silent drop to the floor — inside the
  tier below. Unreachable, but it was held by the tier-width rule refusing the shapes that cause it, which is two
  rules agreeing rather than the invariant being kept

### Added

- **`ShippedScenesTest` sweeps every rig `scene:stack` can produce**, not just `scenes/*.yaml`: seven invocations
  generated by the real command, written by the real writer, loaded back and put through the separating-axis and
  bearing checks. It found four of the five defects above on its first run
- **An all-quarter-turn `roll_cycle` derives its own spacing**, laying out bodies at a uniform pitch rather than
  origins — the same derivation `roll_mirror` uses. A cycle mixing `0` with `90` still cannot (two body widths,
  no uniform pitch), and a stated `step_m` always wins

### Changed

- `scenes/full-rig-quarter-turned.yaml` drops its hand-stated `step_m: 0.02` and two levels of nesting for one
  row of six. **Geometry identical to nine decimal places** — verified against the previous build plan

## [0.37.0] - 2026-08-11

### Added

- **`roll_mirror` on `row` / `lattice`** — a mirrored quarter-turned wall: the half past the middle rolled the
  stated turn, the half before it its mirror image, symmetric about the rig's centre line. `90` or `270` only;
  `step_m` and `roll_cycle` are both refused alongside it. **Same envelope as the alternating pattern** —
  6 rolled Flexys measure 4.678 m either way, because within a half two same-rolled bodies need `W + gap` and at
  the seam they need only `gap`. Only the handedness differs
- **Spacing that derives itself, where `roll_cycle`'s could not.** A rolled cabinet is not centred on its own
  origin — geometry runs from its bottom-centre, so at 90 the body lands entirely right of it and at 270 entirely
  left. `roll_cycle` steps origins uniformly, which is why `full-rig-quarter-turned` has to state `step_m: 0.02`
  by hand and warns that a derived gap "drives adjacent cabinets 591 mm into each other". `roll_mirror` lays out
  the bodies and puts each origin where its own body needs it
- **`roll_mirror` as a stack entry key**, and `scene:stack --roll-mirror=ID` (repeatable). The solver then fits
  and stacks that device by its rolled dimensions throughout: four rolled Flexys fill a 3.70 m stage where six
  standing up do, and a tier of them lifts what is above by 591 mm rather than 763
- `src/Scene/RolledBox.php` — one memoised source for what a rolled cabinet measures and where its body sits,
  taken from the same rotated box the compiler places it with. Swapping width and height by hand is near enough
  for a plain box and wrong for a Tecnare, whose shell is a chamfered trapezoid
- `scenes/full-rig-mirrored-subs.yaml` — `full-rig-quarter-turned` with the wall mirrored instead of
  alternating. Identical footprint (4.68 × 0.96 m), identical tops, identical foci; four of the twelve subs
  change roll and position

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Bearing counted only one support.** It took the overlap with the single highest thing underneath and called
  that the whole bearing, so a cabinet spanning two neighbours of equal height read 35 % where it was really
  93 % — and arrangements that were perfectly well carried were refused. Every support level with the landing
  now counts, within the same centimetre the overhang rule already calls "what feet and working gaps absorb"

### Changed

- `Tier` segments carry a roll, and `Tier` measures through `RolledBox` rather than nominal dimensions. Rigs
  with no roll stated are unchanged — `stacked-center.yaml` and the four hand-written `full-rig-*` scenes all
  regenerate byte-identical
- Two tests pointed at `scenes/full-rig.yaml`, which was deleted from the working tree; they now use
  `full-rig-all-tops`

## [0.36.0] - 2026-08-11

### Added

- **A sub tier narrower than the one below it is flanked from below, to close the step.** The rule that builds
  the mixed bottom row asks whether a row would be narrower than the row coming to stand *on* it — a support
  question, which is why it only ever fired at the bottom. Asking whether a row is narrower than the row it
  stands *on* covers the rest of the wall. Four Achenbachs on six Flexys is 2.460 m on 3.646 m: perfectly
  carried, and a 593 mm shoulder each side. A Flexy either side of them makes it 3.682 m, and the all-speaker
  rig comes out 3.684 / 3.646 / 3.682 — four tiers and 38 mm of variation, where it was five tiers and 1.260 m.
  How many pairs to promote is the converging-widths criterion the bottom row's flanks already use, and the two
  decisions now know about each other: left alone the bottom row grew to three pairs and 4.906 m on the
  assumption that all eight remaining Flexys would stand in one 4.868 m row
- **Bearing is checked per cabinet.** How much of a cabinet is over the thing it landed on, as a fraction of its
  own width; under half is an error. This is a real gap, not a belt-and-braces check: comparing tier widths only
  ever sees a *row* hanging off a row, and the shipped-scene sweep only asks whether there is anything underneath
  at all. A flanked Achenbach row is 163 mm taller at its shoulders, and a contiguous top row across that step
  clips a shoulder by 5.6 mm — whereupon falling lifts a whole 2-way onto **1.2 % of its own footprint**, and
  both existing checks pass it
- **The top tier is re-seated fills outboard when the ordinary row would hang**: end segments centred on the end
  supports, everything between them centred on what is left. The Tecnares land on the Achenbachs, the two 2-ways
  out on the Flexy shoulders they would otherwise have caught. Only as a repair — a tier that is already carried
  keeps its layout, so no rig that stands up today is restyled
- `src/Scene/Gravity.php`, holding falling and the bearing figure. It is used by both `Stack::expand()` and
  `StackSolver`, so the solver rejects exactly the arrangement the expansion would build
- `ShippedScenesTest` measures bearing rather than mere presence. The tightest shipped case is a yawed Tecnare at
  57 % of its rotated bounding box

### Changed

- `scenes/stacked-center.yaml` regenerated: four tiers instead of five, 3.400 m tall instead of 4.000 m, all 23
  cabinets. The committed file had also gone stale against 0.35.1 and still described the 6.128 m bottom row
- `scene:stack --stacks=2` now leaves one SKRAM out of each half rather than building it. A single SKRAM flanked
  into a bottom row leaves the row above it on 49.9 % of its own width, just past the half-a-cabinet line — which
  is why the two-stack plan keeps the pair together

## [0.35.1] - 2026-08-11

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The mixed bottom row grew far wider than the rows above it.** Without a `max_width_m` the flanks were bounded
  only by the available cabinets, so they ate eight of the twelve Flexys and left a 6.128 m bottom row carrying a
  2.424 m one. Mixing exists to remove an inverted step, and that rule decided *whether* to mix but not *how
  much*: it now stops as soon as the row is no longer narrower than what stands on it. Unbounded, the rig is a
  pyramid again — 4.906 / 3.646 / 2.460 / 2.511. A stated `max_width_m` usually bites first, so bounded rigs are
  unchanged

## [0.35.0] - 2026-08-11

### Changed

- **Gravity: each cabinet in a stack lands on whatever is under it**, not on the height of the tallest cabinet
  in the row below. A tier is now expanded as one placement per *run* — adjacent cabinets sharing a device and
  a support — so a tier on level ground is still one row and only a stepped one splits. `Stack::runsFor()`
- **Mixed rows of different heights are legitimate again.** This is the arrangement the mixed bottom row exists
  for: two SKRAMs in the middle of a Flexy row. It was banned in 0.32.0 after that row left four of six Flexys
  floating 151 mm up — but the floating came from resting the whole row above at the taller height, not from
  the mixing. Banning it removed the symptom and the feature with it
- **All twenty-three cabinets go into one stack again**, and nothing is left out. `scenes/stacked-center.yaml`
  is regenerated: `2× flexy + 2× skram + 2× flexy` on the floor, two 4-wide Flexy rows, the Achenbachs, and
  every top in one row
- The stepped-row warning is gone. It said the tier above "rests on the tall cabinets and bridges the short
  ones", which was true of the old placement and is exactly what gravity fixed

### Removed

- `Stack::supportEnvelope()`, superseded by each run knowing its own support

## [0.34.1] - 2026-08-11

### Removed

- TODO entry for incremental renders, which shipped in 0.34.0

## [0.34.0] - 2026-08-11

### Added

- **Incremental builds: every stage skips what is already current.** `scene:build` leaves a `.blend` alone
  when nothing it is built from has moved, and `scene:render` leaves a PNG alone when its `.blend` has not.
  A rebuild after touching one scene now costs that scene and its renders instead of the whole library — which
  at eight variants a scene was 72 frames of Blender for nothing
- `src/Build/Staleness.php` — one freshness rule shared by all three stages, replacing the copy that lived in
  `ModelBuilder`. An output is stale when it is missing or older than any input
- `scene:build --force` and `scene:render --force`, matching `models:build --force`
- `tests/Build/StalenessTest.php`

### Changed

- **`build:all --force` reaches every stage.** It used to pass `--force` only to `models:build`, so a forced
  run still reused stale scenes and renders — the one situation where somebody has explicitly asked for
  everything to be redone
- `scene:build` watches **the model of every cabinet a scene places**, not just the scene file. Measuring a
  cabinet rebuilds its model, which reassembles every scene standing on it, which redraws those renders —
  the whole cascade falls out of comparing neighbours, with nothing to remember

## [0.33.0] - 2026-08-11

### Added

- **`stack.from` entries can carry per-tier options.** A bare device id is still the shorthand; the mapping form
  takes `count`, `align` and `mix_with`. `src/Scene/StackEntry.php`, `src/Spec/ArrayReader::entryList()`
- **`count`** over-books deliberately — six Achenbachs against the four owned, which a stack could not express
  before. Reported by the existing `over_inventory` check, so nothing new warns
- **`align` per tier** instead of one setting for the whole rig. It chooses *which* alignment a tier uses; the
  rule that only a tier carrying nothing may be spread is unchanged
- **`mix_with` per tier**, lifting mixing off the bottom row — it fires wherever the device that asked for it
  sits. A mix that cannot be honoured is refused, not silently dropped
- **`scene:stack --per-owner`** — one stack per `owner`, side by side. No new spec field: for this collective
  who owns a cabinet already *is* the split between the rigs, so a `system:` field would have duplicated
  `owner` value for value
- **`--stacks=N`** splits each group into N stacks (a stereo pair), remainder to the earlier stacks so three
  M2122s over two is 2 + 1 and never 1 + 1 with the third dropped. **`--clearance=M`** is the air between them
- `src/Scene/StackBlock.php` — one solved stack on its way to a file, so the writer can emit several
- `tests/Scene/StackEntryTest.php`

### Changed

- **`interface_height_m` is an optimum, not a requirement.** Missing it is a warning naming how far short it
  fell; it used to be an error. Refusing outright made small rigs unbuildable for no good reason — four
  Achenbachs one-wide reach 2.400 m and two-wide only 1.200 m, and neither is absurd
- **Support outranks the interface.** An arrangement with an unsupported tier is never chosen while a supported
  one exists, even if the unsupported one would have reached the height. Chasing a tall interface used to
  narrow the rows until the tops overhung a one-wide column
- A cabinet that cannot be carried is **left out and named** in the generated scene's header, rather than the
  whole rig being refused. The two SKRAMs are the case
- When nothing stands up at any row width, the error now names the **widest** attempt — the most favourable
  case — rather than the narrowest

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A step in the top row was warned about falsely.** A step only matters if something stands on the row, and
  the tops row always mixes an 0.960 m M2122 with an 0.836 m 2-way with nothing above it. That warning fired on
  every rig we own
- An unreachable interface no longer claims to name the inventory's ceiling when it is really the ceiling of
  arrangements that still stand up

## [0.32.0] - 2026-08-11

### Added

- **`audio.passband_hz` on a device spec** — `{ low_hz, high_hz, driven_from_hz, provenance }`. What a cabinet
  covers, and where it is high-passed in practice when that is deliberately not its low corner. `provenance` is
  required, as it is for a baffle layout: a frequency is trivial to invent and silently decides the order every
  generated rig comes out in
- Filled in for the three subs from the owner's own operating practice: SKRAM 15–120 Hz, Flexy 38–200 Hz,
  Achenbach 35–1500 Hz **driven from 38**. The tops carry none, because nothing needs one — subs always go
  below tops and the tops share a single row ordered by width
- A shipped-scene test that **every cabinet above the floor has something under it**. The sibling of the
  overlap check, and it caught a real one immediately (see Fixed)

### Changed

- **`scene:stack` orders tiers by frequency**, lowest driven corner first, instead of by cabinet width. Width
  was a plausible-looking proxy that got it backwards: the Achenbach is 0.600 m against the Flexy's 0.591, so
  it sorted first and four Achenbachs ended up carrying twelve Flexys. Ties break on the high corner, which is
  exactly the Flexy-versus-Achenbach case — both driven from 38 Hz, and the one that stops sooner belongs lower
- **Every top goes in one row**, widest in the middle, rather than a tier per device. Nothing stands on a top,
  so width is the only thing it costs — and a 2-way stacked on a tilted M2122 was a fill hovering over the
  middle of the rig
- **`max_width_m` is a maximum, not a target.** The row count now narrows until the tops clear the interface,
  so a 10 m stage no longer makes a 2 m interface unreachable — which it did, because every device fitted in
  one row and left two sub tiers at 1.363 m
- `align` on a stack spreads **only the top tier, and only as wide as the tier carrying it**
- An unreachable interface height now reports the **ceiling of the inventory** rather than the height of the
  widest attempt: "2.126 m" read as though one more tier would fix it
- `--subs`/`beside` removed. What is in a rig is chosen with `--from`
- `scene:stack` de-duplicates on **solved geometry** rather than on the emitted file, so alignments that come
  out as the same rig are written once

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Cabinets could float, and did.** A row containing cabinets of different heights has two top faces, so the
  tier above rests on the tall ones and hangs over the short ones — the sub wall of `full-rig-arc` had four of
  its six second-row Flexys **151 mm in the air** over a mixed SKRAM/Flexy row. Mixing now requires matching
  heights, and since no two of our five cabinets share one, the SKRAMs are simply not part of a stacked rig
- **`align` spread load-bearing tiers**, putting two Flexys 6.76 m apart with the middle Tecnare floating over
  the gap between them
- An overhang large enough that more than half the outboard cabinet is off its support is now an **error**
  rather than a warning — the case that produced a 2.511 m tops row on a 1.240 m SKRAM row
- `scenes/full-rig-all-speakers.yaml` removed: identical geometry to the generated `stacked-center`, verified
  cabinet by cabinet

## [0.31.1] - 2026-08-11

### Removed

- `scenes/aimed-close.yaml` — the full rig aimed at a 2 m / 1 m focus instead of 10 m / 1.8 m
- `scenes/driver-detail.yaml` — a contact sheet of three baffles

## [0.31.0] - 2026-08-11

### Added

- **`bin/console scene:stack` — scene files written from constraints instead of typed out.** `stack:` already
  solved a rig, but somebody had to write the scene first; this is the step before that. One scene per
  arrangement that works, and a stated reason for every one it left out
- How many scenes you get is a parameter rather than a decision baked in: `--align` (repeatable),
  `--subs=mixed|beside|both`, `--from`, the `stack:` bounds, `--at`, `--id`. `--align=block --subs=mixed` is
  exactly one scene; the default is three. Going over `--max-scenes` is **refused, not truncated** — a silent
  cap reads as "that is every possibility" when it is not
- Every candidate is **compiled before it is written**, arrangements that resolve to the same rig are written
  once, and an existing scene is never clobbered without `--force`. Output is a `stack:` block rather than the
  expanded tiers, so a generated scene re-solves every build instead of freezing today's answer
- The default `--from` is every speaker, **widest first within each band**, subs before tops — the order that
  stacks without inverting. It produces a cleaner pyramid than listing the Flexys first

### Changed

- **A tier may be mixed, and the bottom one sometimes has to be.** Only two SKRAMs exist, so a row of nothing
  but SKRAMs is 1.240 m — narrower than the 2.460 m Achenbach row that would stand on it. The solver puts the
  two SKRAMs in the middle of the bottom row and flanks them with Flexys: 3.684 m, and the rig is a pyramid.
  Strictly **only to remove an inverted step** — the looser rule "mix whenever a device is in short supply"
  would have dragged `full-rig-stacked`'s Achenbachs below its Flexys, since the Achenbach is the widest sub
  we own at 0.600 m against the Flexy's 0.591
- A mixed tier expands into one placement per segment — `main/1a`, `main/1b`, `main/1c`, letters so they cannot
  be confused with the numeric copy suffixes — and whatever stands on it rests on its **tallest** segment,
  because that is the top face `on:` reads
- **Rows are balanced rather than greedy.** Eight leftover Flexys at six-per-row come out 4 + 4, not 6 + 2:
  the same number of rows, but a 1.222 m row could not carry the Achenbachs above it and a 2.424 m one can
- `scenes/full-rig-all-speakers.yaml` — all 23 cabinets in one stack, which was not expressible before

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A stack could produce a rig that cannot be built, silently.** Asked for all 23 cabinets it put a 2-wide
  SKRAM row under a 4-wide Achenbach row — 610 mm of Achenbach hanging in mid-air at each end. Nothing noticed:
  `on:` only reads a top face, and `ShippedScenesTest` only catches cabinets *inside* each other, never one
  standing on air. There is now a support check, reported as a **warning** with the overhang in millimetres,
  alongside one for a mixed row's step (151 mm between a SKRAM and a Flexy) — both things a crew shims rather
  than refuses, and neither silent
- **`scene:build` and `scene:render` treated any violation as fatal**, so the first warning-severity scene
  violation swallowed the entire build report — which is where the numbers are. Both now filter with
  `Violation::errorsIn()`, exactly as their own spec checks twenty lines above already did
- `scenesDir()` was defined identically in `SceneBuildCommand` and `SceneRenderCommand`; moved to
  `BaseCommand` rather than adding a third copy

## [0.30.0] - 2026-08-10

### Added

- **`stack` on a placement: a rig solved from constraints instead of a tier per row written by hand.**
  `{ from, max_width_m, min_width_m, max_height_m, interface_height_m, gap_m }`. `from` lists the cabinets
  low frequency first and takes the counts from each spec's `quantity`; the solver deals them into rows
  bottom-up and expands into ordinary placements — `main/1`, `main/2`… — each standing `on` the one below
- **The sub/top interface is the constraint worth having.** `interface_height_m` (default **2.0 m**) is how
  high the sub stack's top face has to reach so the tops fire over a standing crowd rather than into it.
  Against what we own, two Flexy tiers reach 1.526 m and miss, and two Flexy tiers plus an Achenbach row
  reach 2.126 m and clear — which is exactly what `full-rig-three-tier` arrived at by hand, so the
  constraint reproduces a stack we already trust
- Either a width bound or the interface height is enough on its own; both together is a solve that can
  fail, and it fails with the number it reached beside the number it needed rather than shipping a near
  miss. `min_width_m` and `max_height_m` are the other two bounds
- **Nothing in the solver assumes a common module**, which is the real work: five cabinets, five widths
  (0.4656 / 0.500 / 0.591 / 0.600 / 0.610) and five heights (0.600 / 0.763 / 0.836 / 0.914 / 0.960), no two
  of them multiples of anything. Every row count is worked out per cabinet and every height is summed
  rather than multiplied — tested over the whole inventory twice, with and without the SKRAM, because the
  SKRAM is the widest thing we own and the second tallest and so is what a tier boundary has to bend around
- `scenes/full-rig-stacked.yaml` — `full-rig-three-tier` with nobody choosing the tiers. It agrees with the
  hand-written scene on the Flexy rows and the interface height, and corrects it on one point: that scene
  calls for six Achenbachs and only four exist
- `align` on a stack applies to every tier but the bottom one, whose edges become the envelope — so a whole
  constraint-solved rig can also be justified, and `full-rig-stacked`'s four rows all run -2.1250 .. +1.5210
- **`align` on a placement: a tier spread across a width instead of a step somebody bisected by hand.**
  `{ mode, width_m | across | inside, inset_m }`. `mode: block` justifies a tier until its outer edges land
  on the width; `center` is the natural spacing every row already had; `stereo` splits the count into two
  columns pushed apart, keeping each column's own spacing
- The reason it cannot be arithmetic: **an aimed cabinet's outer edge does not follow from its width.**
  Aiming toes it in, a toed-in cabinet occupies more x than it is wide, and how far it toes in depends on
  where it ended up — so aligning one tier's edge with another's is a fixed point. It is worse for a
  trapezoid: a Tecnare toed in 11.43° reaches 0.2206 m off centre on its *back* bottom corner, against the
  0.250 m half-width the sum would use, so the naive answer is wrong in both directions depending on the
  cabinet
- The fixed point is bisected against the same rotated boxes the compiler already uses for contact, camera
  framing and the build report — the trial layout goes through the very same `orientationFor()` and
  `worldBox()` as the final one, so the solve and the geometry cannot hold different opinions about where an
  edge is
- `across` and `inside` are separate words because they are separate objects: `full-rig-stereo`'s tops are
  4.678 m *across* and 3.628 m *inside*. A tier standing beside another wants the first; a fill going
  between its outer cabinets wants the second
- Refusals rather than silent near-misses, all naming the number they reached and the one they needed: an
  envelope narrower than the cabinets stacked on one spot, an inset that eats the envelope, a forward or
  self reference, `step_m` alongside `align`, an `arc`/`line_array`/nested group that has no step to solve

### Changed

- `scenes/full-rig-stereo.yaml` no longer carries **0.8156**, **2.1185** or **2.9709**, and
  `scenes/full-rig-all-tops.yaml` no longer carries **1.887**. All four are solved, and the old values are
  now the regression test that the solver reproduces them — to within the 4-decimal rounding of the numbers
  themselves (the exact fixed points are 2.118461, 2.970946 and 1.886983)
- A placement now **rejects unknown keys**. It used to accept anything, so `algn:` read as "not aligned" and
  `aim_lies:` as "follow the scene mode" — both rendering perfectly plausibly with nothing to see. Same
  argument `arc` and `lattice` already made for their own keys

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- `scenes/full-rig-quarter-turned.yaml`'s near-fill step was **1.887 copied from the upright rig, where it
  did not belong**. Its subs lie on their sides, so its tops stand 344 mm lower and toe in by a different
  angle; the step that really leaves 20 mm there is 1.8918. The scene had ~5 mm less clearance than its own
  comment claimed. Solved per scene, the two no longer share a number they never shared geometry for

## [0.29.0] - 2026-08-09

### Added

- **`join` on a baffle feature: two horns that share one mouth.** The Tecnare's two 12″ horns are
  connected on the real cabinet — the wall between them stops behind the baffle, so the front is one
  opening and the two throats only part company inside. Nothing in the spec could say that. Two mouths
  moved until they touch still read as two holes with a line between them, and the wall carries on to the
  throats; the model showed the pair with 12 mm of baffle standing between them
- The field is a relation, `join: { with: <earlier horn>, depth_m: … }`, stated on the later of the two the
  way `inside` names the horn it sits in. `depth_m` is the length of the wall that is *missing*, measured
  from the baffle inwards
- Down to that depth the pair is cut as **one common section**, not as two cavities with the strip between
  them knocked out. Cutting only the strip is the obvious reading and it is wrong: every horn's
  cross-section narrows towards its throat, so two of them cut separately turn inwards near the side walls
  and never meet there. The renders showed it immediately — a shelf at each end of the wall, at every depth
  tried. Cut as one section the walls run unbroken from one horn's far edge to the other's
- The section is measured off **both** horns at every depth, on their own flare laws, roundness and the
  same cutter span the walls were cut with, rather than extruded straight back from the mouth plane. The
  gap between two exponential horns widens as they narrow: a straight prism would have undercut the flare
  walls sideways by 39 mm at 60 mm deep and left a 4.5 mm fin of the wall it was removing standing in front
  of its own nose. Thin fins are also the worst input a boolean solver can get
- The cutter's far end **rolls off over a 15 mm radius** instead of stopping square, so the wall runs into
  the horns' side walls through a fillet. That junction is the part of a joined pair you look straight at,
  and a square end left it as a hard inside corner no cabinet has
- This is the first geometry in the library where two cutters overlap, which is what `_carve`'s
  `use_self = True` was set for in 0.24.0. The cutter stops 0.1 mm short of both horns' side walls on
  purpose: reaching them exactly would pair two coplanar, oppositely wound faces, which is the one surface
  arrangement the EXACT solver handles worst

### Changed

- **The Tecnare's two LF horns are joined, 0.190 m of the 0.200 m they are deep.** The owner's account is
  that the pair is connected, not that the wall ends at a measured depth, so this is that account taken as
  far as the geometry allows: at 0.200 no wall would be left at all, which is a different cabinet — one
  horn with two throats — and not something this spec can say. What survives is a 10 mm lip, 37 mm thick,
  right at the two drivers; the baffle reads as one 0.45 × 0.572 opening. Estimated like every other size
  in this layout. Judged from renders at 18, 60, 100, 150 and 190 mm, which is what the setback needed
- `specs:validate` rejects a `join` that describes no cabinet: one on a cone or on a `mesh_override` spec
  (there the CAD cut the holes and there is no baffle of ours to open up), one naming itself, an unknown
  feature, a later one, a cone or a nested plug, a `depth_m` of zero or one reaching the shallower horn's
  throat, and a pair whose mouths overlap or line up on neither axis
- `TODO.md` 2.1.1 deleted
- `TODO.md` 1.1 (tetris stacking) written out: it is driven by a **constraint** rather than by a tier list —
  `max_width_m`, or a stated height at the sub/top interface so the tops fire over the crowd, plus
  `min_width_m` and `max_height_m` as the other two bounds. Recorded with the arithmetic that matters: two
  Flexy tiers reach 1.526 m and miss a ~2.0 m interface, two plus an Achenbach row reach 2.126 m and clear,
  which is what `full-rig-three-tier` arrived at by hand
- The same item now names the reason it is real work: our five cabinets have five different widths and five
  different heights, none a multiple of any other, so nothing can assume a grid. Two acceptance scenes are
  listed for it — the whole inventory without the two SKRAM (21 cabinets), and with them (23), the SKRAM
  being both the widest cabinet we own and the second tallest
- `TODO.md` 5 deleted: `tools/check-glb.py` no longer fails on `tecnare-m2122`, fixed in 0.28.2. Its
  misnested sub-item, the audio routing table, is now an item of its own

## [0.28.3] - 2026-08-04

### Added

- **`tests/Scene/ShippedScenesTest.php` — every shipped scene checked for cabinets inside each other.** CI
  already proved that all eighteen *compile*; nothing proved any of them could be *built*. The one bug that
  slips past a render is interpenetration, because from a three-quarter camera a cabinet buried in another
  one looks like a cabinet in front of it — which is exactly how `two-foci.yaml`'s fills shipped 0.41 m
  inside the sub wall in 0.21.0. That was found with a throwaway script; this is the same sweep as a test
- Separation is measured with a **separating-axis test on each cabinet's own eight corners**, and that is
  the whole reason the test is worth having. Comparing axis-aligned bounding boxes — the cheap version —
  reports `full-rig-arc`, `sub-wall-lattice` and `flown-array` as broken when they are not: a yawed
  cabinet's bounding box is far larger than the cabinet, so an arc's neighbouring seats always look like
  they overlap. A check that fails on a third of the library gets switched off
- A test that the check catches a deliberate intersection, so the eighteen passes mean something

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A hang's joints were solved at plumb and its elements then tilted individually, which drove
  `flown-array`'s cabinets 21.7 mm into one another.** Found by the sweep above on its first run. `aim`
  resolves a hang as one rigid body, so by the time the chain is laid out its top element is already 14.263°
  down — and a joint is not scale-free in the angle: the one that closes between 0° and 2° is not the one
  that closes between 14.263° and 16.263°. The chain is seeded with the placement's own tilt now, so the
  joints are solved at the angles the array actually reaches. Worst joint: 21.7 mm → 9 µm
- **And the chain now swings with the hang's yaw.** The remaining 3.5 mm after the fix above: joints are
  solved in the elevation plane, which has no x in it, so their offsets came out along the world's y while
  every element was yawed 11.1° towards the focus. A frame does not do that. `PlacementCopy::yawedBy()`
  turns a rigid group's offsets, gated on `Group::decidesPitch()` — which only `LineArray` answers yes to,
  and for exactly this reason. A yawed `row` is deliberately left alone: a straight line of toed-in cabinets
  is what a toed-in sub wall is
- `flown-array` therefore leans back along its aim instead of dropping straight down, and its bottom sits at
  2.195 m rather than 2.13 m. Still walkable underneath
- The `line_array.splay_deg` nose check counts the aim's tilt too — 14° of aim plus 76° of splay stands an
  element on its nose just as surely as 90° of splay does

### Changed

- The splay is accumulated on its own rather than subtracted back out of the running tilt. Both describe the
  same hang, but `14.263 + 2 − 14.263` is `1.9999999999999982`, and these increments end up in a committed
  build plan where a gap the scene wrote as 2° has to read as 2°

## [0.28.2] - 2026-08-04

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The chamfer was pushing geometry outside the declared bounding box**, which is the one promise the asset
  library rests on, and `tools/check-glb.py` had been failing on `tecnare-m2122` because of it. The chamfer
  was added as a *live* modifier **before** the baffle openings were carved into the same shell, so when the
  exporter evaluated the stack the bevel rounded the carved mouths' rims as well as the cabinet's own
  corners. Where three of those rims met on the mid horn it pushed **two vertices of 2636** 0.9 mm in front
  of the baffle plane. Applied immediately instead — the same idiom the handle booleans one function up
  already use — so the openings are cut into an already-chamfered shell, which is the right order
  physically as well. Depth and height are now exact
- The cost, stated because it was the previous behaviour's stated reason: the `.blend` no longer carries an
  editable bevel modifier

### Changed

- **`tools/check-glb.py` is asymmetric now, and the equality it used to check was wrong.** Easing a corner
  can only remove material, and on a **tapered** cabinet the widest point *is* a corner — so a 500 mm-front
  trapezoid with 10 mm eased edges genuinely measures 497 mm across, and the model was right where the check
  was not. A box is unaffected: its side faces stay put and only the corners between them go. Overshoot now
  fails at 0.1 mm as before, because nothing may lie outside the declared box; undershoot is allowed up to
  the chamfer
- `chamfer_m` travels in the glTF metadata so the checker can reason about it, alongside `origin`, which is
  there for the same purpose
- `docs/conventions.md` said the bounding box "always equals width × depth × height", which is knowably
  false for a chamfered trapezoid. It now says nothing ever lies *outside* the box, and says which shape is
  legitimately smaller and by how much

## [0.28.1] - 2026-08-04

### Changed

- **Every visible material now takes `appearance.color`.** The horn flares were `#3a3a3c` against a `#141414`
  cabinet — deliberately lighter, "so the mouth reads as an opening with something inside it" — and the
  handles were `#1a1a1a`. On the 18sound that horn is 0.362 m across a 0.466 m cabinet, so it dominated the
  baffle and read as a differently-coloured panel rather than as a flare. It was never spec-specific: the
  Tecnare's three flares had it too, and all five specs have shared one `#141414` shell for some time
- `metallic` goes with the colour rather than staying: it changes apparent shade more than roughness does, so
  a metallic handle at the cabinet's own colour still would not have matched it. `roughness` is kept, because
  it changes how sharp a highlight is rather than what colour a surface is
- The parts keep their own materials, so per-device colour (`TODO.md` 3.2 — flexy bracings green) can
  differentiate them again without restructuring anything

## [0.28.0] - 2026-08-04

### Added

- **`scenes/full-rig-stereo.yaml` — the widest image the inventory allows.** `full-rig-quarter-turned` with
  every tier pushed apart until its **outer edges line up with the sub wall's** rather than sitting centred
  and narrow on top of it. The sub wall on its side is the widest thing we can build at 4.678 m, so it sets
  the envelope and everything above fills it: the Achenbach row spread to 0.8156 m of step (216 mm of air
  between each), the tops out to 2.1185 m, the fills following them
- **Spreading the middle tier is the mechanism, not a compromise.** The tops stand on the Achenbachs, so the
  row has to reach out to where the tops need to be — which is why the Achenbachs get gaps
- Outer tops move from ±1.55 m to ±2.12 m, a **37% wider** image, at the cost of a thinner middle: three tops
  2.1 m apart leave a wider hole between their patterns, and the centre of the room is covered by the middle
  cabinet alone. Three versions of the same 23 cabinets now exist, narrowest to widest, so that trade can be
  looked at rather than argued about

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- Nothing new, but worth recording: the near-fills follow the tops. With the tops out at 2.1185 the old
  1.887 step left them floating mid-gap instead of tucked against the outer tops, so it is re-solved to
  2.9709 — again against the rotated boxes, and again 20 mm exactly

### Notes

- **Every step in that file is solved rather than derived, and `TODO.md` 1.2 now exists because of it.**
  Aligning an *aimed* cabinet's outer edge cannot be computed from its width: aiming toes it in, a toed-in
  cabinet occupies more x than it is wide, and how far it toes in depends on where it ended up — so it is a
  fixed point, not a formula. Three numbers were bisected out by hand (0.8156, 2.1185, 2.9709) and every one
  goes stale the moment a cabinet is measured or a focus moves. That is the third time this trap has bitten:
  it overlapped two cabinets by 88 mm in `full-rig-all-tops` and put the fills 0.41 m inside the sub wall in
  `two-foci`. The TODO proposes named layouts — `center`, `block`, `stereo`, borrowing the text vocabulary —
  resolved against the rotated boxes, which would remove the whole class

## [0.27.0] - 2026-08-04

### Added

- **`scenes/full-rig-quarter-turned.yaml` — the same rig with the subs on their sides**, twelve Flexys in
  three groups of four, every one rolled a quarter turn and alternating 270/90. Everything above the subs is
  identical to `full-rig-all-tops.yaml`, so it is a straight comparison: the wall goes from 3.65 × 1.53 m
  standing up to **4.68 × 1.18 m** on its side. Wider and lower — the Achenbach row now sits comfortably
  inside the wall instead of overhanging it, and every top drops 344 mm, which flattens their down-tilt
- Twelve cabinets in **one placement, three levels of nesting**: a back-to-back pair, two tiers of that pair
  to make a group of four, three of those groups side by side. The deepest nest any shipped scene uses, and
  the first to use `roll_cycle` with quarter turns rather than `[180, 0]`

### Notes

- **A quarter turn puts a cabinet entirely to one side of its own origin**, because geometry runs from the
  bottom-centre — to the right at 90, to the left at 270. Two cabinets whose origins nearly coincide, rolled
  opposite ways, therefore fall to opposite sides and meet back to back rather than overlapping. That pair is
  what this wall is built from, and its `step_m: 0.02` is 20 mm of air rather than a spacing
- **The order of the cycle matters, and that is not obvious.** `[270, 90]` opens the pair outward from its
  shared origin and the two cabinets touch exactly; `[90, 270]` closes it inward and overlaps them by exactly
  the 20 mm step. Same two angles, opposite result
- **The pair's spacing cannot be derived, unlike every other wall in the repository.** A `row` of four with
  `roll_cycle: [90, 270]` and a derived `gap_m` looks obviously right and drives adjacent cabinets **591 mm**
  into each other, because derived spacing assumes a cabinet occupies a box centred on its position — true
  for every upright cabinet, false for one on its side. Only the pair needs stating; the two levels above it
  derive from what the pair actually occupies

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The near-fills in `two-foci.yaml` were inside the sub wall.** At `y = -0.6` they overlapped it by
  **0.41 m**: the wall is 0.964 m deep so its front face is already 0.482 m forward of its own centre line,
  and a fill aimed at a 2 m focus is turned far enough that its box reaches 0.42 m behind its position. Moved
  to `y = -1.05`, which leaves 40 mm of air in front of the mouths. Shipped wrong in 0.21.0 and invisible in
  a three-quarter render, which is how it survived

## [0.26.0] - 2026-08-04

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **We own twelve Flexys, not fourteen.** `quantity: 14` came from `Hardware Overview.xlsx` and was simply
  wrong. Correcting it is one character; what it forced is the interesting part

### Changed

- **Every sub wall is now two rows of six, and derives its own spacing.** The seven scenes that built a
  fourteen-cabinet wall would otherwise all have reported "uses 14, we own 12", so they are cut to 6 + 6 —
  and converted from `repeat: { count: 7, step: [0.611, 0.0, 0.0] }` at a hand-computed left edge to
  `row: { count: 6, gap_m: 0.02 }` on the wall's centre. Cutting the count the other way would have meant
  typing `-1.8295` into five files; deriving it means the wall follows the spec and the next correction is a
  `count:` edit. `gap_m: 0.02` reproduces the 0.611 m step from the cabinet's own width, so no position
  moves for any reason other than the two missing cabinets
- Every wall is now **15 cabinets, 1224.0 kg, 3.65 × 0.96 m** where it was 17, 1394.0 kg and 4.26 m. The
  tops that sat over the wall's outer subs moved with them, to −1.8295 and +1.2255; the three scenes whose
  tops are an `arc` on the rig centre did not move at all
- The Achenbach row's hand-computed left edge went the same way. It is unchanged in position — a centred
  `row` of six with a 20 mm gap resolves to exactly the `at: [-1.852, 0.0]` plus `step: 0.62` it stated
- **`docs/scenes.md`'s `repeat` section is now about the choice rather than about walls**, since no wall uses
  `repeat` any more. It points at `scenes/skram-detail.yaml`, where a stated step *is* the decision, and says
  which way the dependency runs: a stated step means the scene needs correcting when a cabinet is measured, a
  derived one means the wall follows the spec
- The library is **23 cabinets, 1686 kg, 8.2 m³** (was 25, 1856 kg, 9.0 m³)

### Added

- **`scenes/full-rig-all-tops.yaml` — every top we own on one rig.** The three-tier stack with Sepp's two
  18sound 2-ways added inside the outer M2122s as near-field fill. Two named foci rather than any stated
  angle: the M2122s take `aim: far` down the room, the 2-ways `aim: near` for the people against the stage
  who sit underneath the M2122s' pattern rather than in it. The M2122s stay spread at their 1.55 m step,
  because wide spacing is a coverage decision and an `arc` would pull them onto their own taper. 23 cabinets,
  1606 kg, and the tops are exactly the whole top inventory — three M2122s and two 2-ways
- **The near fill's spacing had to be solved against the turned boxes, not the widths, and the gap between
  those two answers is 108 mm.** Both cabinets are aimed, so both are toed in — the outer top by 8.4° and
  the fill by 20.8°, because a 2 m focus is a hard turn — and a yawed cabinet occupies more x than it is
  wide. Half-widths plus a 20 mm gap gives a step of 2.0944 and drives them **88 mm into each other**; the
  real answer is 1.887, which leaves 20 mm to the outer top and 323 mm to the middle one. Worth writing down
  because the arithmetic looks completely right

### Notes

- The Achenbach row (3.70 m) is now **wider than the wall it stands on** (3.646 m), overhanging 27 mm each
  side. Nothing structurally — cabinets overhang in reality — but it is why the three-tier scenes report the
  row's width as their footprint and not the wall's, and the comment claiming the row is the narrower of the
  two has been corrected

## [0.25.0] - 2026-08-04

### Added

- **Coverage cones from `audio.coverage_deg`.** `Coverage`'s own docblock has promised "it drives the
  optional coverage cones" since it was written, and the angles have been crossing into Python in every
  build plan the whole time with nothing reading them. Stating the angles now draws the pattern
- **10 m of throw, reusing the default focus distance.** A cone is an angle, so something has to choose a
  length, and that is the one number in the library that already means "out where aiming matters" — so a
  cone reaches exactly as far as a scene's default aim and "does the pattern cover the dancefloor" reads
  directly against it. A 60° × 40° Tecnare spreads 11.55 m across and 7.28 m high by the time it gets there
- `Coverage::spreadAt()` and `tests/Spec/CoverageTest.php` — the one part of a cone that can be checked
  without opening Blender, so the trigonometry is unit-tested even though the bpy code cannot be
- The decision stays in PHP: `BuildPlan` emits `coverage_throw_m` and `coverage_spread_m` beside the angles,
  the same way `mark_estimated` is decided in PHP and merely consumed. No new spec field and no CLI flag —
  the cone is built iff the angles are there, exactly the way a missing `baffle_layout` simply builds nothing

### Changed

- **The cone is a wireframe, not a solid.** Solid, ten metres of it swallows the half-metre cabinet it
  belongs to, which defeats the point of drawing it; as a wireframe it is something to sight along
- **It is render-invisible, and that is load-bearing rather than cosmetic.** `export_glb` passes
  `use_renderable=True` precisely so markers stay in the `.blend` and out of the `.glb`, and
  `tools/check-glb.py` compares the exported bounding box against the declared dimensions to 1e-4 m — a
  visible cone would fail that on every axis of every model that had one. Verified: the Tecnare's `.blend`
  carries an 11.547 × 10.000 × 7.279 m cone and its exported box is still its own 0.500 × 0.960 × 0.520
- It lives in `blender/lib/drivers.py` rather than with the other markers in `geometry.py`, because the
  ring-and-shell machinery there is what draws it and a dispersion pattern is the audio side of a cabinet
  rather than part of its box. New `sdwa5-coverage` material; the material table in `docs/conventions.md`
  gains it, and the `sdwa5-cone`/`sdwa5-horn` rows it had been missing since the baffle work

### Notes

- The apex sits at the middle of the baffle, which is a simplification worth naming: a real pattern comes
  from the drivers, spread across the baffle and crossing over at different distances. The cone answers
  "roughly where does this cabinet throw", not "what does the summed response do"
- Only `tecnare-m2122` states coverage angles today, so this is a no-op for the other four specs

## [0.24.0] - 2026-08-04

### Added

- **`fly` — a third way to say where a placement's base is.** `at` is a ground position and `on` is the top
  of an earlier placement; between them they cover everything that stands up and nothing that hangs. That
  gap is why `line_array` shipped unusable: its elements grow *downwards* from their anchor, so anchoring
  one on the floor put it under the floor
- **`fly.point` names the hardware, which is what makes this more than an absolute z.** A cabinet does not
  hang from its own bottom-centre, and `rigging.points` already says where it does hang from — so naming one
  lets a scene state the thing that is true, that *this* point is at 6 m, and the slot is worked out from it.
  An M2122's `top-left` sits 0.960 m up and 0.185 m left of centre, so hung at 6 m over `x = -3.0` the
  cabinet's slot lands at 5.040 m and its centre-line at −2.815. Rigging positions are already in the
  measuring frame, the same frame a slot position is in, so it is a plain subtraction — `geometry.origin`
  stays out of it and the same spec remains usable both ground-stacked and flown, which committing a spec to
  `origin: rigging-point` would not have allowed
- **Weight per suspension point in the report**, which is the number checked against a truss's capacity.
  `fly.id` groups it, so two hangs off one bar add up rather than reporting separately. Absent entirely when
  nothing is flown, so every ground-stacked scene's report is byte-identical
- **A hang that reaches through the floor is reported, with how far by.** It is the one arrangement that can
  be told to sit above the floor and still end up below it, and it is exactly the condition that made
  `line_array` unusable — so `fly` polices itself rather than leaving it to a render
- `scenes/flown-array.yaml` — two J-splayed hangs of four M2122s off one 6 m bar, the first scene to use
  `line_array` at all. `src/Scene/Fly.php`, `tests/Scene/FlyTest.php`

### Changed

- **A hang is aimed once, at its anchor, and the splay adds to that one angle.** A line array is one rigid
  body: every element shares its attitude and differs only by the accumulated splay. Aimed per element
  instead — which is right for a row of tops and was what the code did — each element turns towards the
  target on its own and the splay cancels out exactly. The four-element J in the new scene came out at
  14.26°, 12.63°, 12.87°, 16.10°: not even monotonic, when a J array's whole point is that it opens
  downwards. It now reads 14.26°, 16.26°, 20.26°, 27.26° — one base tilt plus 2°, 4° and 7° — and all four
  share one yaw. `Group::decidesPitch()` is what tells the two cases apart
- **A flown cabinet is not lifted onto a slot.** `zLift()` exists so a tilted or upside-down cabinet still
  rests on the thing it stands on; a hang has no such thing, and the hardware decides where it is. `fly` now
  implies this for the whole placement, not just for `line_array` elements — a single flown top is flown too

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A group could put part of itself below its own base with nothing to lift it back.** `PlacedDevice::zLift()`
  does this for one rotated cabinet; a `roll_cycle` on a lattice whose cell is more than one tier tall maps
  the cell's offsets `z → −z` and buried the lower tier. Same argument, one level out, and exempt for a hang —
  which belongs below its anchor by construction
- **`GroupStack` measured a nested hang as if it stood on the floor.** `boxOf()` and `cabinetBox()` both built
  their measuring boxes without the `seated` flag, so a `line_array` inside a lattice was sized one cabinet
  tall instead of the whole array's height, and the lattice above spaced its cells on a height the hang does
  not have — two hangs 0.96 m apart rather than 2.88 m

## [0.23.0] - 2026-08-04

### Added

- **`build:all` — the stage order, written down.** Every stage already refuses to run on stale input, but
  nothing knew the order, so getting from an edited spec to a new render meant remembering five commands and
  which of them the edit had invalidated. It delegates through the application rather than reimplementing, so
  each stage's own staleness rules, reporting and refusals are the ones that apply, and a failing stage stops
  the run because everything after it would be building on what just went wrong
- **`--lighting-variants` and `--aim-line-variants`**, which are what make it more than a shell alias: four
  lighting presets against with-and-without aim lines is eight passes into eight folders, from one command.
  `--dry-run` lists the stages and the variant folders and runs nothing, which is also the only way to test
  any of it where there is no Blender
- **`scene:render --out-dir`** — a directory to write into, keeping the `<scene>-<camera>.png` names, and
  unlike `--out` it works for a whole run. There was previously *no* way to render every scene somewhere
  else: `--out` is refused for more than one scene
- `src/Command/BuildAllCommand.php` and `tests/Command/BuildAllCommandTest.php`

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Two variants of one scene used to overwrite each other**, both the picture and the plan beside it. Neither
  filename mentioned the lighting or the aim mode, so `-l studio` followed by `-l stage` left one PNG and one
  plan that did not describe it. The plan name now carries both; the picture is separated by its folder
- `--out` together with `--out-dir` is refused rather than one of them quietly winning

## [0.22.0] - 2026-08-04

### Added

- **`aim_lines` as a scene option, and a per-group override.** A scene whose whole point is where things aim
  should not need a flag remembered on the command line: `aim_lines: tops` at scene level says it, and
  `aim_lines: true` or `false` on a placement disagrees with the mode for that group — so a sub can be shown
  and a top hidden. `scenes/two-foci.yaml` uses both, because two groups aiming at two different points is
  invisible in a still render otherwise

### Changed

- **`--aim-lines` overrules the scene only when it is actually typed.** Whether the flag was given has to be
  told apart from the value it defaults to, and the option's default cannot answer that — bare `--aim-lines`
  already yields nothing, which is how it means `tops`. `hasParameterOption()` answers it
- `--aim-lines=none` beats every placement, which keeps it the way to get a clean picture of a scene that
  normally draws them. It is the one thing a placement cannot overrule, and the only place the command line
  wins outright
- The resolved flag rides on `PlacedDevice`, so `RenderPlan` still reads nothing but placed devices

## [0.21.0] - 2026-08-04

### Added

- **More than one focus per scene, referenced by name.** A rig usually needs two: the tops throw down the
  room and the near-fills cover the people against the stage, and one point cannot be both. `focus: { near:
  {…}, far: {…} }` plus `aim: near` says so in a line, and `scenes/two-foci.yaml` is that rig with no angle
  stated anywhere
- One focus or a map of them are told apart by **shape alone** — if every value under `focus` is itself a
  mapping it is a map, otherwise it is the single unnamed one, which keeps the name `focus` so `aim: focus`
  still means it. Every scene written before names existed is unchanged. Same shorthand-or-expanded test
  `ArrayReader::isSection()` already makes for `provenance`
- `ArrayReader::keys()`, for a block whose field *names* are the scene's data rather than part of the schema

### Changed

- A focus stays a decision about the **room** rather than about a cabinet, which is why they are named at
  scene level rather than written into each placement: two clusters sharing one near-field point is the
  normal case, and duplicated numbers drift apart silently
- **Every distance is still measured from the whole rig's front face**, not from each group's own. Measuring
  per group would mean "2 m out" and "10 m out" came from two different places and neither number could be
  read off the file — if the fills stand 0.8 m behind the sub wall, a group-relative 2 m focus is 2.8 m from
  the audience. It would also reintroduce, once per group, the circularity the rig-wide front-face pre-pass
  exists to avoid
- `SceneSpec::$focus` is now `$focusByName`; `Placement::$aimAtFocus` (a bool) is now `$aimFocus` (a name)

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`aim: focuss` meant *not aimed*** — silently, with the cabinet left firing straight ahead and nothing in
  the output to show it. An unknown focus name is now a violation that lists the ones the scene defines

## [0.20.0] - 2026-08-04

### Added

- **`line_array` — a hang, chained rather than fanned.** A sibling of `arc` and not a rolled version of it,
  because the difference is structural: an arc rests on one centre of curvature shared by every cabinet,
  which is what makes its wedge argument work and its radius a single maximum, while an array is a chain
  where every gap has its own angle and there is no shared centre to take a maximum over
- `splay_deg` takes one angle for every gap or **one per gap**, which is what a J array is — `[1, 2, 3, 5, 8]`
  opens up towards the front rows, and writing them out is the whole point
- **The joint hinges where a frame would pin it, and which edge that is follows from the geometry.** A
  downward-curving array pins the rear edge, one curving up pins the front, and at a wedge's own taper both
  give the same answer because the faces meet flat. Solved rather than assumed for a quantified reason:
  pinning the front edge of a downward curve drives a 0.52 m deep cabinet **45 mm** into its neighbour — the
  vertical twin of the concave interpenetration `Arc` already warns about, and just as plausible in a render
- Contact is solved on `Outline::elevation()`, which Stage 1 built for exactly this: an arc's flush splay is
  the angle between the plan outline's two *side* edges, and an array's is the angle between the side
  outline's *top and bottom* edges — `atan((height − front_height) / depth)`, 17.10° for a 0.96 m box with a
  0.80 m front. Verified against the elements' own corners by separating-axis distance: exact contact at
  every joint for a constant splay, a J, and a flat stack
- `src/Scene/LineArray.php` and `tests/Scene/LineArrayTest.php`

### Changed

- **`PlacementCopy` gains a pitch increment, separate from its rotation**, and the reason is worth stating:
  composing an array's splay as a rotation would put it *outside* the placement's yaw, and
  `Rx(σ)·Rz(ψ)·Rx(θ)` is not `Rz(ψ)·Rx(θ + σ)`. An arc's yaw is a turn of the cell; an array's splay **is**
  the cabinet's own tilt, since every element of a hang shares the hang's yaw and differs only in how far it
  is tilted. So it adds to the pitch and leaves the aim, the roll and the yaw where they are
- **`PlacedDevice` gains `seated`, and a flown cabinet is not lifted.** `zLift()` exists so a tilted or
  upside-down cabinet still rests on its slot, which is right for anything standing on something and wrong
  for a hang — each element is tilted differently, so lifting each one back onto its own slot would pull the
  array apart at every joint

### Notes

- **No shipped scene uses `line_array`, because there is no way to hang one yet.** Elements take negative z
  and `at`/`on` can only name a ground position or the top of something, so an array written today ends up
  under the floor. The geometry and the schema are done and unit-tested; a fly-point anchor is a separate
  feature and is now on `TODO.md` next to reporting weight per flown point

## [0.19.0] - 2026-08-04

### Added

- **`lattice` — a 1-, 2- or 3-D grid spaced from the size of whatever it replicates.** This is the group
  that stops a scene carrying arithmetic somebody did by hand. `full-rig-mirrored.yaml` states
  `at: [-2.135, 0.0]` and `step: [0.611, 0.0, 0.0]` twice, and all three numbers are derived: seven 591 mm
  Flexys with a 20 mm working gap make a 4.257 m wall, centred on `x = -0.302`, left edge at −2.135. The
  new `scenes/sub-wall-lattice.yaml` states none of them and resolves to the same fourteen positions, so
  measuring a Flexy moves the wall instead of quietly invalidating it
- **`row` — a lattice with one open axis**, which is the case that dominates; `axis` defaults to `x`. Not a
  second class, because naming the axis outright is also what makes `roll_cycle` unambiguous for free
- **`in` — groups nested inside groups, to any depth.** The sibling key is the cell and `in` is what it is
  nested inside, read inside-out. An outer group spaces itself on the whole inner arrangement's extent, so
  two tiers of a three-wide fan step by the fan and a row of three 2×2 blocks steps by the block — the
  numbers nobody should have to work out. Every scene written before this is untouched rather than merely
  still parsing, because `in` is simply absent
- **`roll_cycle` and `cycle_axis` — alternate cells turned over**, which is a mirrored horn wall as one
  placement instead of two. It composes with `roll_deg` rather than special-casing, so `[0, 180]` gives
  0, 180, 0, 180… and `roll_deg: 90` alongside it gives 90, 270, 90, 270… — both rows the rig needs, from
  one mechanism. `cycle_axis` is required when more than one axis has cells: a cycle down x instead of z on
  a seven-by-two wall turns every *column* over instead of every tier, which is fourteen cabinets wrong and
  renders perfectly plausibly. Values must be quarter turns, because the cell arrives as an axis-aligned
  box and only a quarter turn can be applied to one exactly
- **`step_m`, with 0 meaning "derive this axis"** — a step of zero is meaningless for a count above one, so
  it is a safe way to say it. `scenes/end-fire.yaml` uses it: subs stepping 1.20 m front to back, which is a
  decision about frequency (a quarter wavelength at 71 Hz) rather than about geometry, with x left to work
  itself out
- `gap_m` accepts one number or three. One number means **x and y only**, and that is the real decision: a
  gap on x is air beside a cabinet, which is normal, while a gap on z is air *under* one, which nobody wants
  by accident
- `src/Scene/{Group,GroupStack,GroupReader,Repeat,Lattice,Axis,UnresolvableRotationException}.php`,
  `scenes/{sub-wall-lattice,end-fire}.yaml`, `tests/Scene/{LatticeTest,GroupStackTest}.php`, and
  `Orientation::after()`/`fromMatrix()`

### Changed

- **Expansion happens through one interface instead of one method.** `repeat` and `arc` were the only two
  ways to make copies and they were mutually exclusive, both expanded in a single method in the compiler.
  That holds while "a placement is one group" holds, and stops holding the moment a *cell* is itself an
  arrangement, because then the same expansion has to happen at every level and the levels have to
  multiply. Verified against all eleven shipped scenes: **121 cabinets, zero differences** in position,
  orientation, top height or bounding box
- **`PlacementCopy` carries one rotation rather than a loose yaw.** Composition is matrix multiplication:
  rolling a group over reverses the yaw of everything inside it (`Ry(180)·Rz(θ) = Rz(−θ)·Ry(180)`), which
  is physically what turning an arrangement upside down does and arithmetically not addition. Its `index`
  becomes a `path`, so a nested id says which tier before which cabinet
- **The outer turn moves the inner arrangement's offsets, not only its rotations** — a group is a rigid
  body, and nesting one places it rather than scattering its parts. Put a touching pair inside a convex fan
  of Tecnares and the pair's 0.52 m step has to run along the *cabinet's* x; left in the world's, the second
  cabinet of each pair lands **155 mm** behind its own seam, which reads as an arc bug rather than a nesting
  one
- `repeat` is kept as its own group rather than folded into `lattice`, which can express the same thing. Its
  anchor is the **last** copy, so `on:` a sub row stacks on the far end of it, and seven shipped scenes
  depend on that; a lattice anchors on the middle cell, which is what lets a single cabinet be swapped for a
  group. One class cannot own both rules without a flag whose only purpose is remembering which spelling it
  was written as
- Two group keys on one placement, `in` with nothing to nest, and a mistyped group name inside `in` are all
  refused when the scene is read. The last one matters: an unrecognised name would otherwise build an empty
  wrapper and silently drop a whole level of the nest
- `build: clone` is now `build: self-built`. The build kind and the block naming the original had the same
  word for two different things, and `self-built` against `own-design` says what actually differs — whose
  drawing it was built from. `clone_of` keeps its name, because what it names really is an original

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- Composed offsets are rounded to the nearest picometre. `sin(180°)` is 1.2e-16 rather than zero in binary,
  so a cycled row picked up about 1e-16 m of height per cabinet — nothing physically, and a seventeen-digit
  number in a build plan that should read `0`

## [0.18.0] - 2026-08-04

### Added

- **`splay_deg: 0` is a straight row — spaced and centred from the cabinet itself.** A row is the arc of
  infinite radius, so it is the same contact solve rather than a second mechanism: as the splay closes,
  the arc's spacing converges on the cabinet's own width (0.4957 m at 1°, 0.49996 m at 0.01°, against
  0.500 m), and `centreRadiusM()` now says `INF` rather than reporting a large number that happens not to
  overflow. This is the arithmetic scenes currently carry by hand — `full-rig.yaml`'s `at: [-2.135, 0.0]`
  and `step: [0.611, 0, 0]` are seven 591 mm Flexys plus a 20 mm gap, centred on `x = -0.302`
- **`arc.gap_m`** — the working gap every sub row in the repository already has, stated instead of baked
  into a step. Defined by growing the outline before the contact solve, so it means the same thing at
  every angle: a splayed seam opens by the gap measured *across* the seam
- **A cabinet with nothing to taper resolves to a row on its own.** A plain box, or a trapezoid whose back
  is as wide as its front, has no tightest *bend*, but two of them side by side are already in full face
  contact — which is the tightest convex arrangement there is. It used to be an error demanding an explicit
  angle
- **`roll_deg: 90` works.** Contact is solved on the plan outline of the *turned* cabinet, so an arc no
  longer refuses anything but upright or turned over. On its side an M2122's outline is a plain
  0.960 × 0.520 rectangle — the taper has rotated into the vertical, where the plan view cannot see it — so
  its flush arrangement is a straight row spaced by its **height**, which is what two cabinets on their
  sides actually present to each other. An arc still wants a multiple of 90: in between, the outline's two
  flanks point at different centres of curvature, nothing closes both seams at once, and a solve that
  quietly leaves 14 mm of air down every joint is worse than one that refuses
- `src/Scene/Outline.php` — the cabinet's silhouette in one plane, as a convex hull, in the two planes
  contact happens in: plan for cabinets meeting side to side, elevation for meeting top to bottom. The
  corner sets move onto `DeviceSpec` as `shellCorners()` and `contactCorners()`, so the footprint and the
  contact solve read one definition of the cabinet's shape and can disagree only about the grille frame
- `Orientation::matrix()` and `eulerXYZ()`, `tests/Scene/OrientationTest.php` and
  `tests/Scene/OutlineTest.php`

### Changed

- **A rotation is now composed in the order a scene decides it** — roll the cabinet in its own frame, then
  tilt it down, then aim it (`Rz·Rx·Ry`) — and converted to the euler triple Blender applies (`Rz·Ry·Rx`)
  at the boundary. The two orders agree only while roll is a multiple of 180, which is why the old code
  could flip the sign of the pitch at exactly 180 and be right, and had nothing to say about 90. The
  build plan carries both: `pitch_deg`/`roll_deg`/`yaw_deg` as the scene stated them, and
  `rotation_euler_deg` as Blender wants them
- `Orientation::pitchTowards()` loses its roll parameter. In this order `Ry(roll)` leaves the −Y axis
  fixed, so roll cannot change where a cabinet points and cannot change the angles that aim it either
- **The tightest radius is solved as an angular width about the centre of curvature**, pairwise and in
  closed form, instead of fitting the outline into half a wedge. Measuring it both ways round rather than
  assuming the outline is symmetric about its own axis is what lets a cabinet be rolled: on its side the
  plan outline sits entirely to one side of its origin and there is no half-wedge to fit it into. Verified
  against the previous closed form and, independently, by separating-axis distance between neighbours'
  real corners — exact contact at roll 0, 90, 180 and 270, in both modes
- The flush angle is read off the outline's flanks — the arrangement that puts a whole face in contact is
  the one whose flank, extended, passes through the centre of curvature — rather than off
  `width − back_width` over `depth − inset`. Same 17.35° for an upright M2122, same 16.95° without its
  grille frame, and it survives a roll, which the old derivation could not
- 121 cabinets across the ten shipped scenes are unchanged. Nine values in `full-rig-arc` move by up to
  1e-14 — `atan2(dx, dy)` where the old code divided first, the same angle rounded differently

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **A tilted arc that was also turned over was solved as if it were the right way up.** Tilt swings the
  front-top edge forward and rolling the cabinet 180° swings it the other way, so a mirrored tilted group
  needs 73.7 mm *more* radius at 4.4° than an upright one — and got 73.7 mm less, with the cabinets driven
  into each other in a render that looks entirely plausible. No shipped scene tilts a rolled row, which is
  why nothing showed it
- **Stated pitch and aimed pitch meant opposite things on a rolled cabinet.** `pitch_deg: 5` with
  `roll_deg: 180` aimed at the ceiling while `aim:` on the same cabinet aimed at the floor, because the
  roll was applied after the tilt and turned nose-down into nose-up. Nose-down is now nose-down whichever
  way up the cabinet is
- `Orientation::isUpright()` had no callers and is gone

## [0.17.1] - 2026-07-30

### Changed

- **The Tecnare's mid horn is 0.12 m deep rather than 0.18 m.** At 0.18 it read as a tunnel beside the two
  LF horns rather than as a horn mouth
- **The two 1" HF phase plugs now share the mid horn's 2.25 : 1 mouth** — 0.135 x 0.06 instead of square,
  which is the same proportion as the mid's 0.45 x 0.20 at the same mouth area the plugs had before, so
  only their shape changed and not how much of the 12" cone behind them they cover
- `TODO.md`: two items still named `tecnare-m2122-clone`, a spec deleted in 0.14.0, and quoted the
  18sound's superseded 30 kg. Both now describe what is actually open — weighing the two estimates, and
  confirming the self-built Tecnare against the factory pair the merged spec speaks for


## [0.17.0] - 2026-07-30

### Added

- **`arc` on a placement — a group of cabinets seated on a circular arc**, which is what a cluster of tops
  actually is. Until now the only way to make copies was `repeat`, a linear step vector that leaves every
  cabinet pointing the same way, so the three tops in every rig scene were three hand-written placements.
  `arc: {mode: convex, count: 3}` needs nothing else: **a tapered top's taper is its splay angle**, since
  that is the one angle at which two of them sit side by side with their side faces fully in contact. For
  an M2122 that is 17.35° and a 1.65 m radius, both derived from the cabinet
- `mode` is `convex` (fronts fanning outward, centre of curvature behind, **back** edges touching) or
  `concave` (fronts inward, centre in front, **front** edges touching). It has no default — an arc bent
  the wrong way is a quiet, serious mistake
- `splay_deg` or `radius_m` open the arc up from the tightest; `radius_m` is the arc the front faces sit
  on, the same physical thing in both modes. Counter-intuitively a *larger* convex radius is a *flatter*
  fan, so it is bounded above rather than below
- An arc combines with `aim: focus`: **the arc owns the yaw, the focus owns the down-tilt**, resolved per
  cabinet. `Orientation::pitchTowards()` is new for that, and it measures the tilt along the cabinet's own
  axis rather than the straight line to the target — the outer boxes of a three-wide arc need 4.75° rather
  than 4.48°, and a five-wide cluster splayed 30° needs **2.28×** the tilt
- `scenes/full-rig-arc.yaml`, and `src/Scene/{Arc,ArcMode,PlacementCopy}.php`. `ArrayReader::unknownKeys()`
  makes `arc` the one block that rejects an unknown key, because `step` instead of `splay_deg` would
  otherwise fall back to the default angle and silently move every cabinet in the group

### Changed

- **Contact is solved on the plan-view outline of the *tilted* cabinet, including its grille frame**, not
  on the nominal trapezoid. Both terms matter and neither is cosmetic: the frame is a full-width slab
  across the front, so the taper only runs over `depth − inset` and at the bare trapezoid's 16.95° the
  built meshes overlap by 3.5 mm; and tilt swings the front-top edge forward, so a concave cluster solved
  flat drives its cabinets **20 mm into each other** at 4.4°. Tilt still breaks full-face contact — the
  seam opens into a V, ~22 mm at the top of an M2122 — which is expected and left alone
- **The rig's front face now accounts for every rotation that does not depend on the focus**, arc yaw
  included. A concave arc's outer cabinets stand well in front of its middle one, and measuring them as
  unrotated boxes put the front face 62 mm too far back, landing the focus that much further out than the
  scene asked for. No shipped scene's aiming figures move — verified against `scene:build --dry-run`
  before and after
- Expansion happens in one place. `repeat` and `arc` both produce `PlacementCopy` objects, so the
  placement loop and the front-face walk no longer carry a copy of the arithmetic each

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`PlacedDevice::box()` treated every trapezoid as a full-width box**, against this class's promise of an
  exact rotated footprint. A concave arc came out 9% too wide, and `aimed-close`'s reported footprint drops
  from 4.36 m to 4.26 m now that its toed-in tops are measured on their real taper
- **`aimedAt()` inverted the down-tilt of a cabinet rolled 180°**, aiming it nose-*up* by exactly the angle
  it should have been nose-down. No committed scene triggered it — the mirrored sub rows are not aimed —
  but an arc plus roll would have
- A yaw of `-0.0` serialised as `-0` into the build plan, so a concave arc's middle cabinet would have
  shown up as a diff every time a scene was rebuilt

## [0.16.0] - 2026-07-30

### Added

- **`scenes/full-rig-three-tier.yaml`** — the mirrored Flexy sub wall, a row of six Achenbach 18s on top
  of it, and the three M2122 tops above that, each aimed at the crowd focus. The extra tier lifts the tops
  from 1.53 m to 2.15 m, which takes their down-tilt from 1.1° to 4.4° and their toe-in from ±9.92° to
  ±8.41° — both computed from `aim: focus`, with no angle written in the scene
- The tops sit over the outer Achenbachs rather than over the ends of the sub wall, because the middle row
  is 3.70 m against the wall's 4.26 m and the old positions would have left them overhanging its edges
- `docs/scenes.md` uses the new scene to show what raising a rig does to its aiming, next to the existing
  numbers for the same focus at 1.53 m

### Notes

- The scene calls for **six Achenbachs and only four exist**, so `scene:build` warns that it exceeds the
  inventory. That is deliberate — it is the configuration that was asked for, and the file says in a
  comment how to cut it back to four. It also depends on borrowed gear, which `scene:build` reports too

## [0.15.3] - 2026-07-30

### Changed

- **All five Tecnare horns are square-mouthed and round-throated**, confirmed by the owner against the
  cabinet — so the mid horn's round throat, added in 0.15.0 as an inference from its 2″ compression-driver
  bolt flange, is now an observation, and the two 1″ HF phase plugs get the same treatment instead of
  staying plain four-sided flares
- `docs/sources.md` separates what is now known about this layout from what is not: the **shapes** are
  confirmed, the **sizes** — every mouth, centre height, throat, depth and flare law — are still derived
  from the outer dimensions and the driver sizes, which is why the layout's provenance stays `estimated`

## [0.15.2] - 2026-07-30

### Changed

- **The 18sound's 56 mm front lip is confirmed against the real cabinet** by the owner, so its deep-set
  horn and driver are a real feature of that cabinet and not a modelling artifact. The open question in
  `TODO.md` is closed, and the confirmation is independent support for the CAD's 426.8 mm depth over the
  drawings' 335 mm — the same conclusion the panel dimensions already reached, now from a second direction.
  `provenance.dimensions` stays `plans`: a confirmed lip is not a tape measure over the whole cabinet

## [0.15.1] - 2026-07-30

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **The Achenbach's cone sat 18 mm too deep and was 37 mm too wide.** Both numbers were guesses, and
  measuring the FreeCAD mesh replaced them: the baffle carrying the cut-out is the cabinet's front-most
  panel, so `inset_m` is 0, not one panel thickness; and the cut-out is Ø416 mm, not the driver's nominal
  18″ (457 mm) frame diameter. The oversized cone had its rim buried in the baffle, so it read as a disc
  set back in a hole instead of a driver filling one. Its layout provenance goes from `estimated` to
  `plans`
- The 18sound's `inset_m` is the measured 0.0558, not the rounded 0.056

### Changed

- `docs/sources.md` records what the 18sound's mesh actually says about its front: the baffle is 55.8 mm
  behind the front-most point because the top and bottom panels project forward on a curved edge, which is
  why its openings look deeper than every other cabinet's. Its features are flush with that baffle — the
  baffle is what is deep. Whether the lip is real is now an open question in `TODO.md`, because it is also
  where this cabinet's disputed depth lives
- `scenes/eighteensound-detail.yaml`, since that step only reads from the side or up close

## [0.15.0] - 2026-07-30

### Added

- **`throat_profile`** — a horn's mouth and throat can now be different shapes, and the flare morphs
  between them along its length. `profile: pyramid` with `throat_profile: elliptical` is a horn with
  straight edges on the outside and a round throat, which is what a compression-driver horn is: the
  throat is a round bolt flange, so it cannot be square. Defaults to the mouth's shape, so a horn with
  one cross-section throughout is unchanged
- Rings on a morphing flare are oversampled to a multiple of `2 × sides`, so the polygon's corners and
  the middles of its walls both land on sample points — without that the flat end comes out faceted.
  Only horns that ask for two shapes pay the extra polygons

### Changed

- **The 18sound's horn is elliptical**, not the octagon its baffle cut-out is — owner, against the
  cabinet. The cut-out in the CAD is unchanged, so the model now shows a round horn behind an octagonal
  hole, which is what the cabinet looks like
- **The Tecnare's two LF horns are straight-edged at the mouth and round at the driver** — owner, against
  the cabinet. The mid horn gets the same treatment by inference from its 2″ compression-driver throat,
  which is recorded as inference rather than observation in the spec and in `docs/sources.md`

## [0.14.0] - 2026-07-30

### Added

- **`audio.layout` — the openings on a cabinet's front baffle.** Until now the CAD meshes cut the holes
  and left nothing behind them, so any view that saw into a cabinet saw an empty box. A layout lists
  driver cones and horn flares in the **baffle frame** (origin at the centre of the front face), each
  with its own `depth_m`, and the whole layout carries its own `provenance` — these are the easiest
  numbers in the repo to invent
- **Driver cones** built as a lathed shell: frame lip, surround half-roll crested level with the baffle,
  the cone proper, and a dust cap doming forward at its centre. The crest is what makes it read as a
  driver rather than as a funnel
- **Configurable horn mouths and flares.** `profile: pyramid` with any `sides` (4 for the usual
  rectangular flare, 8 for an octagon) or `profile: elliptical`; `flare: linear` for a straight-walled
  conical horn or `flare: exponential`, where the area grows exponentially with depth as most real horns
  do. Both laws meet the declared mouth and throat exactly, so the choice changes the walls and never the
  sizes, and the defaults (`pyramid`, 4 sides, `linear`) reproduce the previous geometry
- `driver_in` on a horn puts a cone at its throat and bores the driver chamber through to it — what a
  horn-loaded 12″ actually looks like
- `inside: <id>` nests a feature at another horn's throat, facing forward, which is how "the HF horn sits
  inside the LF horn as a phase plug" stays in the data instead of in two hand-matched sets of coordinates
- Layouts for the three cabinets whose drivers are visible from outside: the Achenbach's 18″ cone
  (`estimated`), the 18sound's octagonal horn and 15″ cone (`plans` — both openings are dimensioned in
  the kit drawings), and the Tecnare's mid horn over two 12″ LF horns with a 1″ phase plug in each
  (`estimated`, nothing measured). The Flexy and SKRAM get none: their drivers sit deep in a folded horn
  path and are not visible
- `sdwa5-cone` and `sdwa5-horn` materials
- New validator rules for all of the above, and a `scenes/tecnare-detail.yaml` for inspecting the one
  cabinet whose baffle is entirely generated

### Changed

- **The three Tecnare tops are one spec at quantity 3.** The two factory cabinets and the self-built copy
  are geometrically identical, so modelling them twice was wasted work. `tecnare-m2122-clone` is gone and
  the five scenes that placed it now place `tecnare-m2122`. The cost is that `build` and `provenance` can
  only describe one of the two cases, so the self-built one is recorded in the spec's notes as prose
  rather than as data — split the spec again if that starts to matter. Weights and totals are unchanged:
  68 kg × 3 is what 68 × 2 plus the clone's 68 already was
- A generated cabinet's baffle openings are **cut into the shell**, so a horn's flare is carved out of the
  cabinet and its own material forms the walls — which is what a wooden horn is. A cabinet with a
  `mesh_override` already has its holes, so nothing is cut and only the parts behind them are added
- The generated grille panel is skipped when a spec has a layout: a solid panel across the front would
  hide every horn. The frame bars stay

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`models:build` reported success on a build that had crashed.** Blender can exit 0 after a Python
  error, leaving the previous build's files in place, so checking that the outputs exist passed on stale
  ones. They now have to be newer than the run that claimed to write them

## [0.13.1] - 2026-07-30

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **Aim lines drew a floor marker under a ray that never reached the floor.** A nearly level ray meets
  the floor a very long way out — the 10 m focus gives ~1.1° of tilt from 2 m up, which needs **103 m** —
  so the 40 m cap truncated it in mid-air while `hits_floor` still said true, and the marker landed on
  the floor beneath a line that stopped above it. Beyond the cap the ray is now truncated with no marker,
  so a marker always means the ray genuinely lands there. Reported from the render, where the
  disconnected dots were visible

## [0.13.0] - 2026-07-30

### Added

- **`scene:render --aim-lines`** — draws a glowing rod from the centre of each cabinet's front face along
  the direction it points, stopping where it meets the floor and leaving a marker there. `--aim-lines`
  covers tops, `--aim-lines=all` includes the subs. Off by default
- The rod follows each cabinet's **actual** front axis rather than the point it was told to aim at, so it
  turns "these all aim at one place" from a claim into something visible — and a mistake in the aiming
  shows up instead of being drawn over
- Camera framing widens to include the rays when they are on, since where they converge and land is the
  point of asking for them
- `PlacedDevice::frontFaceCentre()` and `frontDirection()`; four new tests (suite now 143)
- `scenes/aimed-close.yaml` — the same rig aimed at 2 m / 1 m instead of 10 m / 1.8 m: 18–22° of tilt and
  ±36° of toe-in. Kept as a contrast case, with a note that it is not a setup anybody would build

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- `README.md` was stale: total weight 1834 → **1856 kg**, the 18sound row still had the superseded
  0.420 × 0.800 × 0.335 and 30 kg, and SKRAM's height and depth were the wrong way round. The current-state
  table now also says **which devices have real CAD and which are still generated blocks**, which is the
  question most often asked of this repo
- `README.md` gained `ddev mesh-convert`, the `meshes/` directory and the `--aim-lines` flag; `docs/scenes.md`
  documents aim lines; `docs/inventory.md` carries the 18sound's corrected 41 kg
- `TODO.md`: splayed sub arcs no longer need schema work — `pitch_deg`, `roll_deg` and `aim: focus` cover it

## [0.12.0] - 2026-07-30

### Added

- **`pitch_deg`** on a placement — down-tilt, so tops can be aimed into an audience instead of over it
- **`aim: focus` and a scene-level `focus` block** — `{ distance_m: 10.0, height_m: 1.8, x_m: … }`. Every
  cabinet that aims at the focus works out **its own yaw and down-tilt** from where it actually stands,
  which is what "all the tops point at the middle of the dancefloor" means in practice. Stated as a
  distance and a height rather than absolute coordinates, because that is how the decision is really made
- **`aim_at: [x, y, z]`** for naming a point outright
- `scenes/full-rig-aimed.yaml` — the mirrored wall with all three tops aimed at one point: symmetric
  ±9.92° toe-in and ~1.1° down-tilt, resolved per cabinet
- `src/Scene/Orientation.php` and `src/Scene/Focus.php`; 8 new tests (suite now 141)

### Changed

- **Rotation handling is now exact.** `PlacedDevice` rotates the cabinet's eight corners through pitch,
  roll and yaw in Blender's own order, and everything derived from that — the lift back onto its slot,
  the height for `on` stacking, the report's footprint and the camera framing — reads the same box. The
  previous code approximated a yawed cabinet as `max(width, depth)` across and ignored pitch entirely
- Distance to the focus is measured from the rig's **front face**, computed from ground positions and
  unrotated depths only, so it cannot become circular with the aiming it feeds

### Notes

- ~1.1° of down-tilt at the default focus is correct, not a bug: a top whose middle is 2 m up, aiming at
  1.8 m from 10 m away, drops 20 cm over that run. A nearer or lower focus steepens it

## [0.11.0] - 2026-07-30

### Added

- **`achenbach-18` and `eighteensound-2way-15` now use their own CAD**, converted from the FreeCAD
  models in Drive with `ddev mesh-convert`. Four of six devices carry real geometry now: the Achenbach
  contributes its driver cut-out and corner braces, the 18sound its octagonal horn cut-out, Ø353 mm
  driver hole and two Ø100 mm ports
- The Achenbach's CAD measures 600 × 700 × 600 mm, matching the spec **exactly** — a third independent
  confirmation after the panel geometry and lsv-achenbach.de's published panel sizes

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **`eighteensound-2way-15` outer dimensions were wrong: 0.4656 × 0.836 × 0.4268 m, not
  0.420 × 0.800 × 0.335.** The published drawing's numbers are *partial*: its dimension lines sit
  inside the overhanging top and bottom panels (parts F and E), so 420 is the baffle width, 800 the
  side-panel height, and 335 a depth that stops short of the back. The CAD agrees with the drawings
  wherever they measure the same feature — its baffle is 418.3 mm and its side panels exactly 800 mm —
  and is 46 mm wider, 36 mm taller and ~92 mm deeper overall
- The same spec's weight estimate recomputed for the larger box and the CAD's 18 mm panels (the note
  specifies 15 mm Baltic birch, so this build deviates): **41 kg**, up from 30. Library total is now
  1856 kg

### Notes

- `mesh-convert` finished the Achenbach in seconds this time, having timed out past ten minutes before
  the solids filter was added in 0.9.0 — it was meshing datum planes and intermediate features

## [0.10.0] - 2026-07-30

### Added

- **`roll_deg` on a placement** — turns a cabinet over about its own front-to-back axis, so 180 leaves
  it facing forward but upside down. That is how horn-loaded subs are stacked in mirrored pairs, with
  two mouths meeting to behave as one larger one
- `scenes/full-rig-mirrored.yaml` — `full-rig.yaml` plus that one line, so the two can be compared
- The compiler **lifts a rolled cabinet back onto its slot**: geometry runs z = 0 to height in a
  cabinet's own frame, so turning it over would otherwise sink it through the floor. The lift is
  computed from the rotated corners, so it is right for any angle, not just 180
- `on` stacking, the scene report's footprint and the camera framing all use the **rolled** extent, so
  a cabinet on its side counts as tall as it is wide and anything stacked on it still lands correctly
- Four tests covering the lift, stacking onto a rolled cabinet, the swapped extent at 90°, and the
  footprint. Suite is now 133

### Notes

- Which row to flip is not obvious: a Flexy's mouths sit in the *lower* part of its face, so the
  **bottom** row is the one to turn over. Flipping the top row instead drives the mouths apart —
  documented in `docs/scenes.md`, having been rendered wrong the first time

## [0.9.1] - 2026-07-30

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- **SKRAM orientation.** Its CAD is authored for machining (the Fusion files are named "CAM"), so it
  arrived lying on its back with the vents pointing away. `rotate_deg: [0, 0, 180]` stands it up and
  turns it round: the front now shows the vent array with the 21″ cone visible through it, and the
  small recessed rectangles are handles on the top and sides — not vents, as first assumed
- **SKRAM height and depth were the wrong way round**: 0.914 m high × 0.813 m deep, not the reverse.
  The cut list supports it — its largest panel is 914 × 813, which fits sides of 914 high by 813 deep.
  `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` says the opposite, and is noted in the spec as
  contradicting it; a tape measure settles it. **This changes stacking heights**, so it matters

## [0.9.0] - 2026-07-30

### Added

- **`ddev mesh-convert <file>`** — meshes `.FCStd`, `.step` and `.iges` into something Blender can
  read, which was the bottleneck for every remaining cabinet. FreeCAD runs in a throwaway container;
  the command is a ddev **host** command because `bin/console` runs in the web container, which has no
  docker, and baking FreeCAD into the ddev image would add over a gigabyte for a rarely-used step
- `tools/freecad-export.py` — exports **solids only** (a FreeCAD document is full of datum planes and
  axes, and meshing those inflates the bounding box until the dimension check fails for no real
  reason), prefers a finished `PartDesign::Body` over its intermediate pads, and prints the bounding
  box in millimetres so `units` and `rotate_deg` can be chosen without guessing
- **SKRAM now uses Josh Ricci's own CAD.** The SKRAM DIY Package's `STEP Files/SKRAM 3D.step` — a
  52-solid full assembly — converted and wired up: recessed hatches with bolt holes, handle grooves
  and internal bracing instead of a block
- `scenes/skram-detail.yaml`

### Changed

- `docs/sources.md`: SKRAM moves from "no source anywhere" to Ricci's CAD, with what the package
  actually contains (full and per-panel STEP, 29 DXF, Fusion, SolidWorks, cut sheet)
- SKRAM's `mesh_override` carries `tolerance_m: 0.011`: the CAD measures 619.6 × 812.8 × 924.4 mm —
  height exactly 32″, but width and depth each 10 mm over the published 24″ and 36″. The nominal
  figures stay in the spec because the cut list corroborates them, and the tolerance admits the CAD
  until somebody measures the built cabinet

### Known issue

- The SKRAM mesh's **orientation is unconfirmed**. Its dimensions verify, but which face carries the
  horn mouth has not been checked against a real cabinet — `rotate_deg: [90, 0, 0]` currently puts the
  open chambers upwards, which is probably wrong. Tracked in `TODO.md` item 4.2

### Notes

- Tecnare's own downloads were checked directly: data sheets, manuals, rigging guides and firmware
  only — no CAD, DWG, DXF, STEP, EASE or GLL for any product, discontinued ones included. The M2122
  can only be measured
- `Achenbach 18` identified as an [LSV Achenbach](http://lsv-achenbach.de/plaene/plan_sub18.htm)
  design: bassreflex, RCF L18P300/L18P200, 155 l net. Its published panel sizes (564 × 700 sides,
  564 × 564 front/back) match what was extracted from the FreeCAD file, independently confirming
  0.600 × 0.600 × 0.700 m. Plans are PDF only
- Eighteen Sound offers only a PDF for the 15″ 2-way — most of their kits ship a blueprint archive,
  this one does not

## [0.8.0] - 2026-07-30

### Added

- **The Flexy has real geometry.** `flexy-folded-horn-hybrid` now uses the design's own CAD via
  `mesh_override` — four folded-horn mouths and the throat flares instead of a black box. Found in a
  second, previously unexamined CAD folder in Drive (`Medien/Bildbearbeitung/Merch/CAD/`) as a 1:10
  print model: watertight, 2817 faces, and only 3 mm narrower than the spec, so it passes the default
  tolerance untouched
- `scenes/detail-check.yaml` — one of every device in a row. Render it with `-l flat` after touching
  the geometry builder and every chamfer, grille and horn mouth is visible side by side
- `meshes/README.md` — what override meshes are, why they are not committed, the exact `rclone`
  command to fetch each one, and how to export a `.FCStd` from FreeCAD
- `docs/sources.md` gained a per-device 3D-geometry table: the whole Shared Drive was swept for 3D
  formats, so it records what exists, what is unusable and what has no source at all

### Changed

- **A declared-but-absent override mesh is now a warning, not an error.** Override meshes are
  third-party CAD this repository deliberately does not commit, so a spec naming a file the current
  checkout lacks is normal: `specs:validate` warns, and the build falls back to the generated block.
  Previously this made the whole library invalid for anyone without the file
- `Violation` carries a severity, and commands refuse on errors while still printing warnings
- `tools/check-glb.py` honours the override's own `tolerance_m` instead of the tight default, and
  reports the worst axis. It was failing every override that was not sub-0.1 mm exact, which made it
  useless for exactly the models it most needed to check
- **Framing fits the bounding box as the camera sees it, not the bounding sphere.** A sub wall is wide
  and shallow, so its sphere is far larger than its silhouette and the old fit pushed the camera back
  until the rig was a smudge in the middle of the frame. Every render is now noticeably tighter
- glTF `extras` records the override's basename, units and tolerance — the basename only, because a
  local absolute path has no business travelling inside a `.glb`

## [0.7.0] - 2026-07-30

### Added

- **`scene:render`** — renders an assembled scene to `build/renders/<id>-<camera>.png`. A preview now
  costs one command and no Blender knowledge; the 17-cabinet rig takes about 8 seconds on the
  container's CPU
- **Camera presets** (`-c`): `three-quarter` (default), `front`, `side`, `top`, `crowd`. Each is a
  *direction*, not a position — the distance is computed from the scene's own size, so the same preset
  frames a single floor monitor and a fourteen-wide sub wall equally well, and no scene ever needs a
  camera placed by hand. (This release fitted the bounding sphere; 0.8.0 replaced that with a box fit.)
- **Lighting presets** (`-l`): `studio` (default), `stage` (warm key, coloured rims), `daylight` (sun
  and sky — where most SdWa5 events are), `flat` (even and shadowless, for inspecting a chamfer or a
  grille inset). Light positions are multiples of the scene radius and area-light power scales with its
  square, so a big rig is not dark at the settings that light one cabinet
- `--samples`, `--resolution`, `--no-ground`, `--out`, and `--presets` to list what is available
- `src/Render/` (`CameraPreset`, `LightingPreset`, `RenderPlan`) and `blender/render_scene.py`. The
  framing maths lives in PHP so it is unit-testable — camera framing is exactly the kind of thing that
  drifts silently when nobody can assert on it
- `tests/Render/RenderPlanTest.php` — 12 tests covering bounds, framing distance scaling with scene
  size, per-preset placement, eye-height handling and light power scaling. Suite is now 127 tests
- `docs/scenes.md` gained a full Rendering section; `docs/blender.md` no longer says lighting has to be
  set up by hand

### Changed

- The `crowd` preset frames deliberately tighter than 1:1 (margin 0.92): standing in front of a 4 m sub
  wall it fills your view, and a shot that politely fits it all in undersells it

## [0.6.0] - 2026-07-30

### Added

- **`scene:build`** — a PA setup written as YAML in `scenes/` becomes `build/scenes/<id>.blend` with
  every cabinet placed at true scale. A setup that worked is now a commit rather than somebody's
  memory, and next year's variation is a diff
- `scenes/full-rig.yaml` — the whole PA in about twenty lines: a 14-cabinet sub wall as two stacked
  rows of seven, plus the three M2122 tops. 17 cabinets, 1394 kg, 2.486 m tall, 4.26 × 0.96 m footprint
- **`on:` stacking** — a placement sits on an earlier one and its height is worked out from the specs,
  so no z coordinate is ever written into a scene. When somebody finally measures the Flexys, every
  stack in every scene corrects itself instead of quietly becoming wrong
- **`repeat: { count, step }`** — a sub wall is two lines instead of fourteen entries. A repeated row
  can be stacked on another repeated row
- Scene report, printed before Blender runs: cabinet count, total weight, tallest stack, floor
  footprint from cabinet extents, and per-device and per-owner breakdowns. Plus three warnings that
  are cheap here and expensive on site — using **more cabinets than the inventory has**, depending on
  **borrowed gear** (any device whose `owner` is not `sdwa5` — what that field was added for), and
  positions resting on **un-measured** cabinets
- `--dry-run` reports without touching Blender, which is how CI checks scenes
- `src/Scene/` (`Placement`, `SceneSpec`, `SceneLoader`, `SceneCompiler`, `PlacedDevice`,
  `SceneReport`) and `blender/build_scene.py`. All the arithmetic lives in PHP so stacking and
  repetition are unit-testable without Blender
- `docs/scenes.md`; `ArrayReader::numberList()`; 22 new tests (suite now 116)

### Changed

- Each device's collection is appended **once** and instanced per placement, so a 14-cabinet wall
  costs one copy of the geometry — the shipped 17-cabinet scene is under 100 KB
- `scene:build` refuses to assemble a scene from stale or missing models, and from invalid specs
- CI now also runs `scene:build --dry-run`

## [0.5.0] - 2026-07-30

### Added

- **`mesh_override` now works.** A spec can point at a real mesh and it replaces the generated shell
  outright — grille and handle recesses included, since real CAD models those better than the builder
  can. `blender/lib/mesh_import.py` imports `.obj`, `.glb`, `.gltf`, `.stl`, `.ply` and `.blend`
- `mesh_override` accepts a bare path, or an expanded form with `units` (m/cm/mm), `rotate_deg` and
  `tolerance_m`. Real CAD arrives in whatever units and orientation its author used, so the spec has
  to say which rather than the builder guessing
- **The spec stays the authority:** after importing, scaling and rotating, the builder measures the
  mesh and fails the build if any axis is off by more than the tolerance, printing all three numbers.
  A silently mis-scaled cabinet still looks like a cabinet and would quietly poison every setup built
  from it
- `src/Spec/MeshOverride.php`; `SpecValidator` rejects a missing path, a format Blender cannot import
  (naming `.FCStd` explicitly, since that is the likely mistake here), unknown units and a negative
  tolerance
- `/meshes/` added to `.gitignore` — override meshes are third-party files and stay out of git

### Changed

- The Flexy's CAD mesh was tested against this: it imports correctly with `units: mm` and
  `rotate_deg: [90, 0, 90]`, and its depth and height match the spec exactly, but it is **18 mm
  narrower** than the cabinet — a second, independent confirmation that the mesh omits something.
  Its `mesh_override` therefore stays `null` until that is reconciled, and the finding is recorded in
  the spec and in `TODO.md`
- `docs/spec-format.md` gained a "Mesh overrides" section; `docs/conventions.md` no longer describes
  `mesh_override` as unimplemented

## [0.4.0] - 2026-07-30

### Changed

- **`provenance` is now tracked per field** — `provenance.dimensions` and `provenance.weight`, with a
  single value still accepted as shorthand for both. They diverge in practice: a hanging scale
  settles a cabinet's weight in a minute, while taping fourteen subs is an afternoon. With one field
  for both, a weighed-but-unmeasured cabinet still reported `plans` and the easy half of the work
  showed no progress at all
- `catalog` reports "Dimensions measured: n of m" and "Weights measured: n of m" as separate counts,
  and the Provenance column shows `plans/estimated` when the two differ
- `eighteensound-2way-15`, `achenbach-18` and `tecnare-m2122-clone` now declare
  `weight: estimated` against `dimensions: plans`/`datasheet`, which is what was true all along and
  previously only visible in prose
- Only an estimated **shape** gets the orange viewport tag; an estimated weight does not distort the
  model, so it is reported by `catalog` rather than drawn
- `SpecValidator` checks the clone/`clone_of` rule against each provenance field separately
- `flexy-folded-horn-hybrid`: width refined to 0.591 m
- Stale `tree/master` links updated to `tree/main` after the parent repo's branch rename

### Added

- `src/Spec/ProvenanceSet.php` with `weakest()`, `isFullyMeasured()` and a compact `label()`
- `ArrayReader::isSection()` — needed for fields that accept either a scalar shorthand or an
  expanded mapping
- `tests/Spec/ProvenanceSetTest.php`; suite is now 92 tests

## [0.3.0] - 2026-07-30

### Added

- `specs/speakers/tecnare-m2122-clone.yaml` — three M2122-shaped tops exist, not two: two genuine
  Tecnare cabinets plus one self-built. Kept as its own spec rather than raising the factory pair's
  quantity, because build, provenance and weight all differ and a setup should be able to tell which
  cabinet is which. `Hardware Overview.xlsx` counts only the factory pair
- Inventory totals are now 6 specs, 25 cabinets, 1834 kg, 8.96 m³

### Changed — the tops row

- **The alignment mode now decides the ORDER of the tops row, not only its spacing.** `stereo` puts the long throws
  at the outer ends with the near-field fills inboard of them, nearest the centre line — the broadest stereo image
  the envelope allows, with the fills covering the middle ground between the clusters. `center` and `block` keep the
  long throw centred with the fills outboard, which is the mono answer. The two orders are mirror opposites and
  `stereo` is consequently its own rig now: it used to resolve identically to `center` and be deduplicated away
- **A stereo tops row is now pushed apart until it spans its support**, which is what makes the image broad rather
  than merely correctly ordered. The slack is handed out between neighbouring groups so each cluster keeps its own
  spacing and only the air between them grows; the outer tops' edges land on the sub wall's edges and no further,
  since past that they are over nothing. Five tops on our own rig went from spanning 2.011 m to 3.200 m. This is the
  `align cannot spread a mixed row` limitation worked around where it matters: there is nothing to *solve* here —
  {@see Alignment} exists for landing an aimed edge exactly on an envelope, and moving clusters apart can only
  increase the clearance an aimed cabinet needs
- **An odd top goes to the centre line in stereo, not to one side**, so the two clusters stay equal. Three M2122s
  and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`. A true palindrome needs every group's count
  even or exactly one odd; with two odd groups the centre holds one of each and only that block is uneven

### Fixed

- `flexy-folded-horn-hybrid`: width corrected to **0.590 m** (owner-confirmed, and what the
  comparison spreadsheet said all along). The CAD mesh in Drive reads 0.573 m, so it omits ~17 mm of
  the real cabinet; height and depth still come from the mesh, which agrees with the spreadsheet's
  rounded figures while being more precise

### Changed

- The two remaining estimated weights are now **calculated rather than guessed**, with the working
  written into the specs: plywood surface area × density, plus internal panels, drivers and hardware.
  The whole Shared Drive (1166 files) was searched for weight data and has none, and Eighteen Sound
  publishes no finished-cabinet weight for a DIY kit — a hanging scale is the only remaining source
- `docs/sources.md`: per-device rows updated; the unsourced weights are now a table with their basis
- `docs/inventory.md`, `README.md`, `TODO.md`: totals, the Tecnare split, and the measuring backlog

## [0.2.0] - 2026-07-30

### Added

- The real inventory, read out of the SdWa5 Shared Drive (`Hardware/Hardware Overview.xlsx` and
  `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx`): 5 enclosures, 24 cabinets, 1766 kg, 8.5 m³
  - `specs/speakers/flexy-folded-horn-hybrid.yaml` — 14×, 0.573 × 0.763 × 0.964 m, 85 kg; dimensions
    measured off the design's own CAD mesh
  - `specs/speakers/skram.yaml` — 2×, 0.610 × 0.813 × 0.914 m, 90 kg; dimensions cross-checked
    against both the cut list in Drive and Josh Ricci's published 24″ × 32″ × 36″
  - `specs/speakers/tecnare-m2122.yaml` — 2×, tapered 0.500 m front / 0.345 m rear × 0.960 × 0.520 m,
    68 kg, 60° × 40°; from the Tecnare L2122LT datasheet. First spec to use `shape: trapezoid`
  - `specs/speakers/eighteensound-2way-15.yaml` — 2×, 0.420 × 0.800 × 0.335 m; dimensions read off
    the FRONT and SIDE VIEW drawings of Eighteen Sound's 15″ 2 Ways Kit application note
  - `specs/speakers/achenbach-18.yaml` — 4×, 0.600 × 0.600 × 0.700 m; derived from the panel geometry
    in the FreeCAD model in Drive
- `owner` spec field (default `sdwa5`) — some cabinets at an SdWa5 event are lent by a member, and a
  setup that quietly depends on borrowed boxes can fall apart. `catalog` gained an Owner column and
  reports units and weight per owner: sdwa5 18 units / 1506 kg, sepp 6 units / 260 kg
- `docs/sources.md` now records, per device, exactly which file each number came from

### Changed

- `SpecValidator`: `provenance: datasheet`/`plans` only requires `clone_of` for a **clone**. Factory
  gear cites its own datasheet, so demanding an "original" there forced a fiction — found while
  entering the Tecnare, which is a bought cabinet
- `SpecValidator`: rigging-point bounds are not checked when a dimension is impossible. An inverted
  bounding box made every point look out of bounds and buried the one error worth fixing
- `docs/inventory.md` rewritten: what is in Drive, what is in the specs, what is still missing, and
  the configured read-only `rclone` remote
- `README.md`, `TODO.md`: current state is a real inventory rather than photo estimates; the measuring
  backlog is now item 1

### Removed

- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — the photo-estimated placeholders,
  superseded by the five real specs

### Notes

- Nothing has been **measured** yet: every dimension describes a design or a datasheet, and the
  weights for `eighteensound-2way-15` and `achenbach-18` are outright estimates. `catalog` reports
  the un-measured count on every run
- SKHORN, GHORN and OTHORN appear in the comparison spreadsheet with full dimensions but were
  evaluated, not bought — deliberately absent from `specs/`

## [0.1.0] - 2026-07-30

### Added

- Initial repository: spec-driven parametric 3D models of SdWa5 speakers and stage equipment, for
  Blender event previews and PA setup planning
- `specs/` — one YAML file per device as the single source of truth for dimensions, weight, rigging
  points, and the provenance of every number. Most SdWa5 cabinets are DIY clones of commercial
  designs, so `build`/`clone_of`/`provenance` record which original a cabinet copies and whether its
  numbers come from a datasheet, build plans, a measurement or a guess
- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — worked examples estimated from
  `~/Documents/SdWa5/2024-07-17_pa-lautsprecher-foto.jpeg`, marked `provenance: estimated`
- `bin/console` — Symfony Console application with `specs:validate`, `models:build`,
  `library:build` and `catalog`
- `src/Spec/` — spec value objects, `SpecLoader`, and `SpecValidator` enforcing the shared
  conventions: positive dimensions, chamfer and grille inset within bounds, unique ids matching
  their filenames, valid subtype per category, `clone_of` present for clones, rigging points inside
  the cabinet, taper dimensions stated explicitly per shape
- `src/Build/` — `BuildPlan` (the PHP → Python hand-off), `BlenderRunner` (headless invocation with
  `--factory-startup` for reproducibility), `ModelBuilder` (up-to-date checks, output verification)
- `src/Catalog/CatalogRenderer` — equipment table with total weight, total volume and the count of
  devices that have never been measured
- `src/Process/` — `ProcessRunner`, `ProcOpenProcessRunner` and `BinaryChecker`, ported from the
  sibling `yt-dlp-tools` project
- `blender/build_model.py`, `blender/build_library.py` and `blender/lib/` — geometry, the shared
  material set, metadata and export. Box, trapezoid and wedge shells, chamfer, recessed grille
  behind a frame, handle recesses cut as applied booleans, rigging markers, and an orange tag on
  estimated cabinets
- `blender/build_library.py` also marks every device as a Blender collection asset with stable
  catalog UUIDs, so the library is draggable from the Asset Browser
- `tools/check-glb.py` — verifies an exported model against its own glTF `extras`: bounding box
  equals the declared dimensions, and the cabinet sits on the floor
- `tests/` — 83 PHPUnit tests mirroring `src/`, including validator rejection cases and the Blender
  invocation via a fake process runner; no Blender needed
- `.ddev/` — PHP 8.3 CLI project with Blender 4.3.2 installed into the web image, pinned to the
  version Debian 13 ships so container-written `.blend` files open on the host
- `.github/workflows/tests.yml` — PHPUnit and `specs:validate` on every push
- `docs/` — conventions, spec format, pipeline, Blender usage, measuring checklist, inventory and
  Drive access, sources and licensing, plus the generated `docs/catalog.md`
- `README.md`, `TODO.md`, `CHANGELOG.md`

### Notes

- Canonical model format is glTF 2.0 (`.glb`), chosen because it is Blender-native and the model
  format embedded in GDTF/MVR — the entertainment industry's scene exchange standard, which already
  covers truss and rigging and has audio speakers on its roadmap
- Nothing generated is committed: `build/` is gitignored and reproducible from the specs
