# Signal chain

What drives what, at which level, behind which crossover. The half of a PA that
[`specs/`](../specs) does not describe, because every spec in there describes a box and its geometry.

This document holds two things: **the rule for how a rig's DSP and amplifier settings are written down**, and
**the rigs that have been written down that way**. The first is short. The second grows by one section per
event.

## The table is generated, not copied

The question this answers is whether a limiter table should be one reusable file that gets edited for each
event, or a template that gets copied per event. **Neither, and the Drive already ran the experiment for
both.**

`Audio Routing.xlsx` sits in the rclone account's My Drive with **four files named `AmpLimiterCalc.csv`**
beside it. Re-measured on 2026-09-08 they carry three distinct sizes, 7700, 7718 and 7432 bytes, with the last
appearing twice at the same timestamp. That is what copying a template produces: three files that each claim
to be the current settings, no way to tell which one the rig is actually set to, and no way to diff them
against anything. A single reusable table is not better, only differently wrong. It holds exactly one event,
so standing up the next rig overwrites the last one, and when a room repeats a year later its settings are
gone.

**So the table is output.** Three layers, and only the middle one is written by hand per event:

| Layer | Changes | Lives in |
|-------|---------|----------|
| **Master data** — per speaker group its impedance, RMS rating and delay; per amplifier the gains its DIP switches offer | when a driver is swapped or a cabinet is rebuilt | `specs/`, once `SPEC-13` and `SIG-3` land. Today still the `Drivers` and `Amp_GainSelector` sheets in Drive |
| **The patch** — which cabinets are there, how many hang on each amplifier channel, which DSP output drives which group | once per event | a section in this file, and the counts in [`rosters/`](../rosters) |
| **The table** — thresholds, headroom, delays, the routing matrix | never by hand | derived from the two above |

The arithmetic that turns the first two into the third is four lines and is now
[`src/Signal/LimiterSetting.php`](../src/Signal/LimiterSetting.php), with the spreadsheet's own figures as its
test cases:

```
Vspk = sqrt(ohm × watt)          the RMS voltage the cabinet is rated for
Vin  = Vspk / 10^(gain / 20)     the DSP output voltage that drives the amp to exactly that
thr  = 20 × log10(Vin / 0.775)   the same voltage as a dBu threshold, which is what a DSP asks for
head  = dsp_max_dbu − thr        what is left before the DSP itself clips
```

**A row of the table is therefore never typed.** What is typed is a patch, and the patch is short enough to
read in one screen. The command that walks a patch and writes the workbook does not exist yet; that is `SIG-1`
in [`../TODO.md`](../TODO.md), and until it does the tables below were produced by the class above and checked
by hand.

### Two things the arithmetic gets asked wrong

**Paralleling cabinets does not move the threshold.** Two 8 Ω cabinets on one amplifier channel are a 4 Ω load
drawing twice the power, but each cabinet still sees the same voltage, and voltage is the whole of what a
limiter clamps. So the impedance and power in the calculation are always *one cabinet's*, never the channel's
total. Passing the channel total instead sets the threshold 3 dB low and quietly costs the rig half its
output. What the extra cabinets do change is whether the amplifier can still deliver that voltage into the
lower load, which is a question about the amplifier and is not answered here.

**A higher amplifier gain is not a better setting.** Gain moves the threshold down one for one, so every 3 dB
step up on the DIP switch throws away 3 dB of the DSP's usable output range and runs that group correspondingly
closer to the converter's noise floor. The setting to look for is the *lowest* step that still leaves a stated
margin — 6 dB in everything below. `Amp_GainSelector.csv` in Drive recommends the opposite, 41 dB for the
TIP10000q and 44 dB for the MM14K, on the grounds that a lower setting "cannot reach BR RMS limit". For the
rigs recorded here that reasoning does not hold, and the sweep under each rig shows why.

## MARK Salzburg, 2026-09-19

Twelve Flexy, two SKRAM, two Tecnare M2122, four GISEN MM14K, one Tulun/Play/Prokustk TIP10000q, both DSPs.
Counts in [`rosters/sdwa5-mark-salzburg-2026-09-19.yaml`](../rosters/sdwa5-mark-salzburg-2026-09-19.yaml).

**The video mapping rig is what decided the topology.** It needed one DSP output of its own. The 8x8 has
eight, two of which are permanently spent on the stereo bus compressor — they leave the box on OUT1 and OUT2
and come straight back in on IN1 and IN2, and every speaker output is fed from those loop-ins rather than from
the console inputs. Two for the loop, one for video mapping, five left, and the rig needs six groups: Sub FH,
Sub SKRAM, and Tecnare LF and HF each in stereo. **So the DCX2496 was not a preference, it was the missing
sixth output**, and the tops went on it because its four outputs are exactly the bi-amped Tecnare's four ways.

### The patch

```
8x8                                     amplifier
 OUT1  BusComp L        -> loop to IN1   —
 OUT2  BusComp R        -> loop to IN2   —
 OUT3  Sub FH           MM14K #1-#3, six channels, two Flexy per channel (4 Ω)
 OUT4  Sub SKRAM        MM14K #4 channels A and B, one SKRAM each (8 Ω)
 OUT5  -> DCX IN_A      —
 OUT6  -> DCX IN_B      —
 OUT7  video mapping    —
 OUT8  spare            —

DCX2496
 OUT1  Tecnare LF L     TIP10000q channel 1 (4 Ω)
 OUT3  Tecnare HF L     TIP10000q channel 2 (8 Ω)
 OUT2  Tecnare LF R     TIP10000q channel 3 (4 Ω)
 OUT4  Tecnare HF R     TIP10000q channel 4 (8 Ω)
 OUT5  spare
 OUT6  spare
```

**Why the DCX outputs are not in channel order is under the connections below**: it is the amplifier's back
panel, not the processing.

Eleven of twelve amplifier channels used, one MM14K channel free. **The SKRAM take a channel each rather than
sharing one**, which is what spends the spare: both cabinets on one channel would have worked electrically and
left two channels free, and one channel each was chosen instead.

### The table

Thresholds at the recommended gains. `Vspk` and the impedance are one cabinet's figures throughout.

| DSP | Output | Group | Amp | Gain dB | Vspk V | Vin V | Threshold dBu | Ratio | Attack ms | Release ms | Delay ms | DSP max dBu | Headroom dB |
|-----|--------|-------|-----|--------:|-------:|------:|--------------:|-------|----------:|-----------:|---------:|------------:|------------:|
| 8x8 | OUT1_BusComp_L | — | — | — | — | 0.775 | 0.00 | ∞:1 | 1.0 | 3000 | — | 18 | 18.00 |
| 8x8 | OUT2_BusComp_R | — | — | — | — | 0.775 | 0.00 | ∞:1 | 1.0 | 3000 | — | 18 | 18.00 |
| 8x8 | OUT3_SubFH | Sub FH | MM14K | 32 | 120.00 | 3.014 | **11.80** | ∞:1 | 10.0 | 3000 | 0.0 | 18 | 6.20 |
| 8x8 | OUT4_SubSKRAM | Sub SKRAM | MM14K | 32 | 120.00 | 3.014 | **11.80** | ∞:1 | 10.0 | 3000 | 2.0 | 18 | 6.20 |
| 8x8 | OUT5_To_DCX_TopL | — | — | — | — | 0.775 | 0.00 | 1:1 off | — | — | 0.0 | 18 | 18.00 |
| 8x8 | OUT6_To_DCX_TopR | — | — | — | — | 0.775 | 0.00 | 1:1 off | — | — | 0.0 | 18 | 18.00 |
| 8x8 | OUT7_VideoMapping | — | — | — | — | 0.775 | 0.00 | 1:1 off | — | — | 0.0 | 18 | 18.00 |
| DCX | OUT1_TecLF_L | Tecnare LF | TIP10000q | 26 | 69.28 | 3.472 | **13.03** | ∞:1 | 5.0 | 200 | 4.7 | 22 | 8.97 |
| DCX | OUT2_TecLF_R | Tecnare LF | TIP10000q | 26 | 69.28 | 3.472 | **13.03** | ∞:1 | 5.0 | 200 | 4.7 | 22 | 8.97 |
| DCX | OUT3_TecHF_L | Tecnare HF | TIP10000q | 26 | 52.92 | 2.652 | **10.69** | ∞:1 | 1.0 | 120 | 4.7 | 22 | 11.31 |
| DCX | OUT4_TecHF_R | Tecnare HF | TIP10000q | 26 | 52.92 | 2.652 | **10.69** | ∞:1 | 1.0 | 120 | 4.7 | 22 | 11.31 |

The video mapping output carries an unprocessed full-range copy of the mix at 0 dBu, taken from both loop-ins,
with no limiter, no delay and no filter. The mapping machine does its own analysis and was to be given the same
signal the PA gets before the PA's own processing.

**The 8x8 was measured on the bench ten days later, and its `DSP max dBu` does not hold as written.** On the
scale its thresholds use it clips at about +12.5 rather than 18, which moves either every 8x8 headroom figure or
every sub threshold. `1:1 off` on OUT5 to OUT7 holds only while their gain stays at 0 dB. Both are under *On the
bench* below.

### The connections

**Line level.** Thirteen long XLR runs and six short ones. The six short ones are the amplifier chain: the MM14K
carries one output per input, so a single DSP output reaches six channels by walking through them rather than
through a splitter.

**One block per device and one row per input**, because that is the order the work happens in: you stand in
front of one box at a time with a cable in your hand. Thirteen cables reach an input. Eight sockets on the two
processors take none.

#### DSP 8x8

| Input | Fed from | Carries |
|-------|----------|---------|
| **IN1** | its own OUT1 | bus compressor return L |
| **IN2** | its own OUT2 | bus compressor return R |
| **IN3** | console L | the mix |
| **IN4** | console R | the mix |
| IN5 – IN8 | — | empty |

Outputs: OUT1 and OUT2 back into IN1 and IN2, OUT3 to MM14K #1, OUT4 to MM14K #4, OUT5 and OUT6 to the DCX,
OUT7 to the mapping machine, OUT8 empty.

**IN1 and IN2 are the whole of the bus compression, and the compressor is this box itself.** A matrix processor
cannot compress a bus ahead of its own crossovers, so the summed mix leaves on OUT1 and OUT2 with the limiter
on those two outputs doing the work and arrives back on IN1 and IN2 as the source every speaker output is fed
from. No outboard box is in the loop; what makes it a loop is two short cables on the back of this one device.
The console therefore never reaches a crossover directly, and **pulling those two cables silences everything
downstream of them**, the video mapping feed included, because that output is fed from the return like every
other one. If the mapping is meant to survive a dead PA it has to come off IN3 and IN4 instead, which is a
matrix change and not a cable change.

The routing matrix in Drive describes three consoles on IN3 to IN8, which was a different night.

#### Behringer DCX2496

| Input | Fed from | Carries |
|-------|----------|---------|
| **IN_A** | 8x8 OUT5 | tops L, unprocessed |
| **IN_B** | 8x8 OUT6 | tops R, unprocessed |
| IN_C | — | empty |

Outputs: OUT1 to OUT4 to the TIP10000q, OUT5 and OUT6 empty. It splits left onto its odd outputs and right onto
its even ones, which is what decides the amplifier's channel order below.

#### GISEN MM14K, four units

Only the first channel of each chain has a cable from a processor. The MM14K carries one output per input, so
OUT3 reaches six channels by walking through them and OUT4 reaches two the same way.

| Unit | Input | Fed from | Carries |
|------|-------|----------|---------|
| #1 | channel A | **8x8 OUT3** | Sub FH |
| #1 | channel B | #1 channel A out | Sub FH |
| #2 | channel A | #1 channel B out | Sub FH |
| #2 | channel B | #2 channel A out | Sub FH |
| #3 | channel A | #2 channel B out | Sub FH |
| #3 | channel B | #3 channel A out | Sub FH |
| #4 | channel A | **8x8 OUT4** | Sub SKRAM |
| #4 | channel B | #4 channel A out | Sub SKRAM |

#### DCX2496 output routing

One row per output, which is the page the device itself is set from. `Source` is the routing; everything right
of it is the processing on that output.

| Out | Source | Speaker | HPF | LPF | Gain | Polarity | Delay | Limiter dBu | Release |
|-----|--------|---------|-----|-----|-----:|----------|------:|------------:|--------:|
| **1** | IN_A | Tecnare LF L | 185 Hz LR24 | 2500 Hz LR24 | 0.0 dB | normal | 4.7 ms | **13.03** | 200 ms |
| **2** | IN_B | Tecnare LF R | 185 Hz LR24 | 2500 Hz LR24 | 0.0 dB | normal | 4.7 ms | **13.03** | 200 ms |
| **3** | IN_A | Tecnare HF L | 2500 Hz LR24 | off | 0.0 dB | normal | 4.7 ms | **10.69** | 120 ms |
| **4** | IN_B | Tecnare HF R | 2500 Hz LR24 | off | 0.0 dB | normal | 4.7 ms | **10.69** | 120 ms |
| 5 | — | — | — | — | — | — | — | — | — |
| 6 | — | — | — | — | — | — | — | — | — |

Odd outputs take IN_A and even outputs take IN_B, which is the device's own left-and-right convention and the
reason the amplifier's channel order is what it is.

**The two crossover corners are the sheet's, not this repository's.** `Drivers` gives the Tecnare LF as
185–2500 Hz and the HF as 2500–20000 Hz, and `specs/speakers/sdwa5/tecnare-m2122.yaml` carries no passband at
all, so there is nothing here to check them against. The cabinet's own passive crossover at about 6.5 kHz sits
inside the HF way and needs nothing from the DCX.

**185 Hz is also the corner against the subs, and the two sides do not quite agree.** The Flexy spec says the
subs run to 200 Hz and marks it `estimated`; the sheet puts the tops' corner at 185. The sub low-pass lives in
the 8x8 rather than here, so the DCX cannot resolve it on its own, and a 15 Hz overlap is audible as a bump
rather than as a hole.

**Four values in this table are conventions rather than sources.** LR24 is the ordinary choice for an active
crossover and is not stated anywhere for this rig. Normal polarity on all four outputs is an assumption that
nobody has checked against the cabinets. 0.0 dB of output gain follows from the limiter doing the level
setting, which is `SIG-6`. And the 4.7 ms still has the DCX's own throughput latency to come off it, which is
`SIG-5`.

**The attack times have no home on this device.** The sheet carries 5.0 ms for the LF way and 1.0 ms for the
HF, and the DCX2496's limiter offers a threshold and a release and nothing else. Those two figures are
therefore recorded and not set.

#### Tulun/Play/Prokustk TIP10000q

| Input | Fed from | Carries |
|-------|----------|---------|
| **channel 1** | DCX OUT1 | Tecnare LF L |
| **channel 2** | DCX OUT3 | Tecnare HF L |
| **channel 3** | DCX OUT2 | Tecnare LF R |
| **channel 4** | DCX OUT4 | Tecnare HF R |

**Deliberately not in the DCX's output order.** A four-channel amplifier pairs its NL4 sockets 1 with 2 and 3
with 4, so whatever shares a socket shares a cable. Taking channels 1 and 2 from DCX OUT1 and OUT3 puts one
whole Tecnare on one socket and each top then needs a single ordinary NL4. Wiring it in output order splits
each cabinet across both sockets and needs two cables per top, or one hand-made one.

#### Video mapping machine

| Input | Fed from | Carries |
|-------|----------|---------|
| line in | 8x8 OUT7 | full range, no processing |

**Speaker level.** Fourteen Speakon runs to the subs, two to the tops.

| Amplifier channel | Load | Cabinets |
|-------------------|-----:|----------|
| MM14K #1 A / B | 4 Ω | Flexy 1+2 / Flexy 3+4 |
| MM14K #2 A / B | 4 Ω | Flexy 5+6 / Flexy 7+8 |
| MM14K #3 A / B | 4 Ω | Flexy 9+10 / Flexy 11+12 |
| MM14K #4 A / B | 8 Ω | SKRAM 1 / SKRAM 2 |
| TIP10000q 1+2 | 4 Ω / 8 Ω | Tecnare L, one NL4, LF on 1+/1− and HF on 2+/2− |
| TIP10000q 3+4 | 4 Ω / 8 Ω | Tecnare R, one NL4, same pinout |

Each pair of Flexy is one run from the amplifier to the first cabinet and one link between the two, so eight
long runs and six short ones.

### Mains

CEE 16 A, three phases. **The breaker is the tightest thing in this rig**, tighter than any amplifier and
tighter than the DSP headroom, so the phases are split to balance the amplifiers rather than to keep a rack
together.

| Phase | Carries | Sine max |
|-------|---------|---------:|
| L1 | MM14K #1, four Flexy, and MM14K #4, two SKRAM | 10 800 W |
| L2 | MM14K #2, four Flexy, and the TIP10000q, two Tecnare | 10 300 W |
| L3 | MM14K #3, four Flexy, plus both DSPs and the video mapping machine | 7 200 W |

The DSPs and the mapping machine sit on the lightest phase on purpose, so the thing that has to stay up is not
sharing a breaker with the heaviest switching load.

**What that draws, and the assumptions it rests on.** The sine maximum above is what the limiters allow, which
is not what music draws. At a quarter of it, which is a heavily compressed programme with the limiters working,
L1 takes about 13.8 A, L2 about 13.2 A and L3 about 9.2 A, against 16 A available. At an eighth, which is
ordinary programme, all three sit under 7 A. At a third all three phases trip.

Three figures behind that are **rules of thumb rather than measurements**: 230 V per phase, 85 % amplifier
efficiency, and the quarter-and-eighth fractions themselves. None of them is published for these amplifiers and
none was measured on the night. What the arithmetic is good for is the comparison, not the absolute number —
grouping #1 with #2 instead, which is how the racks are laid out, puts 14 400 W on L1 and trips it at the same
quarter where the split above holds.

**Switch on DSPs first, then the mapping machine, then the amplifiers one at a time.** Off in exactly the
reverse order. Four amplifiers of this size closing onto a 16 A breaker together will trip it on inrush alone,
before any programme is playing.

### Why 32 dB and 26 dB

Threshold in dBu at every step the DIP switches offer. A cell over the DSP's own ceiling is unusable, because
the DSP would clip before the limiter ever engaged.

| Group | DSP max | 23 | 26 | 29 | 32 | 35 | 38 | 41 | 44 |
|-------|--------:|---:|---:|---:|---:|---:|---:|---:|---:|
| Sub FH / Sub SKRAM | 18 | **20.80** | 17.80 | 14.80 | 11.80 | 8.80 | 5.80 | 2.80 | −0.20 |
| Tecnare LF | 22 | 16.03 | 13.03 | 10.03 | 7.03 | 4.03 | 1.03 | −1.97 | −4.97 |
| Tecnare HF | 22 | 13.69 | 10.69 | 7.69 | 4.69 | 1.69 | −1.31 | −4.31 | −7.31 |

**MM14K at 32 dB.** 23 dB is out of reach entirely at 20.80 dBu against a ceiling of 18. 26 dB leaves 0.20 dB
and 29 dB leaves 3.20 dB, neither of which is a margin. 32 dB leaves 6.20 dB and is the lowest step that does.

**TIP10000q at 26 dB.** The LF channel binds, because it is the louder of the two ways. 23 dB would leave it
5.97 dB, just under the margin; 26 dB leaves 8.97 dB, and the HF channel on the same switch then sits at 11.31
dB. If the DIP switches turn out to be per channel rather than per amplifier, HF can go to 23 dB on its own.

**Not 41 and 44 dB as `Amp_GainSelector.csv` recommends.** At 44 dB the subs' threshold lands at −0.20 dBu,
which gives away 18 dB of the 8x8's output range and runs the largest group in the rig that much nearer the
noise floor for no gain anywhere.

**All of this rests on the sheet's 18 dBu for the 8x8, and the bench did not confirm it.** On the scale its
thresholds use, the 8x8 clips at about +12.5 for a sub signal. If that scale is dBu, the subs' ceiling in the
sweep is 12.5 rather than 18. 32 dB then leaves 0.70 dB rather than 6.20 and 35 dB leaves 3.70 dB, so the lowest
step that clears 6 dB is 38 dB with 6.70 dB. If the 8x8 does reach 18 dBu, 32 dB stands and the threshold
entered on the device has to change instead. The next section has both cases.

### On the bench, 2026-09-29

**The 8x8 was measured ten days after the event**, with its outputs looped back into a Behringer UMC1820 and
every amplifier off. trackdsp, the software that drives the 8x8 over USB, records the runs and their raw figures
in [`docs/measurements.md`](https://github.com/GitiGlitzer/dsp_linux_8x8/blob/main/docs/measurements.md). Four
of its findings bear on this rig.

**The 8x8 clips at about +12.5 on the scale its thresholds use, not at 18.** That holds for a 100 Hz signal. For
a 1 kHz signal the same ceiling reads about +14.8, because a threshold holds a 100 Hz signal about 2.2 dB higher
than a 1 kHz one. The lower figure is the one measured where the subs play. Whether the threshold scale is dBu
at the connector was not measured, since no voltmeter was at hand, and the two answers change the table in
different places.

| If | Then |
|----|------|
| **The threshold scale is dBu at 100 Hz** | The 8x8 reaches about 12.5 dBu. Every threshold limits where the table says, but the subs' 11.80 leaves 0.70 dB rather than 6.20 before the 8x8 itself clips, and the lowest MM14K step with 6 dB left is 38 dB. The 18.00 of headroom shrinks to about 12.5 on OUT5 to OUT7, and on OUT1 and OUT2 to about 12.5 at low frequencies and 14.8 at 1 kHz |
| **The 8x8 reaches 18 dBu** | The threshold scale sits 5.5 dB below dBu at 100 Hz, so a threshold of 11.80 holds the subs at about 17.3 dBu. That asks the MM14K at 32 dB for 5.5 dB more than the Flexy's 120 V, which it may not even deliver. The headroom column stands, and every sub threshold has to be entered 5.5 dB below its dBu figure |

**A voltmeter on a 100 Hz sine decides it.** Set Limit at a known threshold on one output, play a sine well
above it and read the AC volts at the output connector. The reading in dBu against the threshold says which row
holds, or how far between them the 8x8 sits. A second reading at 1 kHz covers the bus limiter, which sees the
whole range.

**`1:1 off` is off only at 0 dB of output gain.** Ratio 1:1.0 is the 8x8's factory setting, and at it every
output gain other than 0 dB acts more strongly than set. −6 dB becomes −8.2 dB, and the output mirrors the
signal at 24 kHz minus each frequency, 9.6 dB below it. Above 4 kHz that image lands in the audible range. OUT5
to OUT7 run at 0.0 dB, so the rig as set is clean. Trimming the tops on OUT5 and OUT6 on site would not be,
unless their compressor is first set to Limit or 1:1.1 with the threshold at +20 dB. That setting never
compresses and keeps the gain exact.

**The bus limiter mirrors the whole mix while it works.** Every compressor on the 8x8 does this while it lowers
the gain, whatever its ratio and its times, and the bench measured it at the ratio and the times of OUT1 and
OUT2. The image of each frequency lies at 24 kHz minus that frequency, about 15 dB below the signal at 3 dB of
gain reduction and about 10 dB below it at 6 dB. Every speaker output is fed from the loop-ins and inherits it.
The subs' low-pass takes it out again, while the Tecnare HF way reproduces it for everything in the mix above
4 kHz. A limiter that only catches the odd peak costs nothing. A bus compressor is meant to work, and while it
works it colours the top end.

**The output gain acts before the limiter.** Lowering an output's gain while its limiter worked left the limited
level where it was, so a threshold stays the real output limit whatever the gain. For `SIG-6` that settles what
a `DSP_Output_Gain_Set_dB` equal to the threshold would do on the 8x8. It would drive the programme that much
harder into the limiter without moving the limit. What the sheet meant by the column is still open.

### Open, and checked on site

| Item | State |
|------|-------|
| **Amplifier gain as actually set** | The figures above are what the DIP switches were to be *set to*. What they read before that was not recorded. Every threshold moves one for one with the gain, so a rig set differently has a different table |
| **DCX2496 throughput latency** | The Tecnare now pass through an extra converter pair that the subs do not. Their 4.7 ms alignment delay has to come down by the DCX's own latency, and that figure is not in any source this repository has. **Not applied, and the table above still says 4.7** |
| **Sub SKRAM passband** | The `Drivers` sheet leaves both band columns as a dash. [`specs/speakers/sdwa5/skram.yaml`](../specs/speakers/sdwa5/skram.yaml) says 15–120 Hz and marks it `estimated`. The crossover against the Flexy, which run to 200 Hz, is undecided |
| **`DSP_Output_Gain_Set_dB`** | In the sheet this column is a formula setting it equal to the threshold. An output level is not a dBu threshold, so one of the two is mislabelled. **Carried over unchanged rather than guessed at**. On the 8x8 such a gain would only drive the programme harder into the limiter, since the gain acts before it |
| **The 8x8's threshold scale against dBu** | It clips at about +12.5 on that scale for a 100 Hz signal, where the table assumes 18 dBu. Either the scale is dBu and the subs have 0.70 dB of headroom rather than 6.20, or the 8x8 reaches 18 dBu and every sub threshold holds 5.5 dB above its figure. **A voltmeter on a 100 Hz sine decides it**, see *On the bench* above |
| **Amplifier output voltage** | Whether an MM14K delivers more than 120 V into 4 Ω is in no source this repository has, and the limiter only does anything if it does. The same question is open for the TIP10000q at 4 Ω |
| **The TIP10000q's socket pairing** | The tops are patched on the assumption that its NL4 sockets pair channels 1 with 2 and 3 with 4, which is what makes one cable per Tecnare work. Stated from how four-channel amplifiers are normally built, **not read off this one's back panel** |
| **The Tecnare's own pinout** | One NL4 per cabinet with LF on 1+/1− and HF on 2+/2− is what the owner stated on the night. The cabinets are self-built and no spec here records a connector, so there is nothing to check it against |
| **Mains draw** | 230 V, 85 % amplifier efficiency and the quarter-of-sine-maximum figure are rules of thumb. Nothing was measured, and no manufacturer publishes a draw for these amplifiers. The phase split is sound as a comparison and the absolute amps are not evidence |
| **OUT8 on the output panel** | The output panel of the 8x8's rack has a broken connection on OUT8, found on the bench on 2026-09-29. OUT8 was spare at this event, so nothing above depended on it. It needs repairing before a rig uses all eight outputs |

## Where the master data still lives

`Audio Routing.xlsx`, six sheets, in the rclone account's My Drive rather than the SdWa5 shared drive. Reaching
it needs `--drive-team-drive ""`, because the `SdWa5:` remote is scoped to one team drive:

```sh
rclone copy "SdWa5:Audio Routing.xlsx" . --drive-team-drive ""
```

That remote is `scope = drive.readonly`, so **nothing here can be written back to Drive**. Until `SIG-4` adds
the scope, a regenerated workbook goes into `build/` and a human uploads it.

The sheet disagrees with [`sources.md`](sources.md) in four places and with a CSV sitting beside it in the same
folder about amplifier gain. It is a working sheet rather than a datasheet, so importing any of it carries the
same `provenance` obligation as a dimension does. See the `SIG` section of [`../TODO.md`](../TODO.md).
