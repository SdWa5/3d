<?php

/*
 * The evidence behind SYM-3's "equal air against equal pitch" recommendation, so the numbers in TODO.md can be
 * re-measured rather than trusted.
 *
 * It lays the eight tops out three ways across one envelope and prints the air and the pitch each one produces:
 * equal air with the ends pinned, equal pitch with the ends pinned, and what `Alignment`'s single scalar does
 * today. Widths are copied from the specs and the order is the one `StackSolver::topRow()` deals, so a cabinet
 * being measured or a top being bought means editing `$w` here.
 *
 * Deliberately standalone. It reasons about arithmetic on nominal widths and does not touch the solver, so it
 * says nothing about toe-in — an aimed cabinet occupies more x than it is wide, which moves every number here in
 * the same direction and is what the real solve has to handle. See `Alignment`'s docblock.
 *
 *     docker run --rm --entrypoint php -v "$PWD":/app -w /app ddev/ddev-webserver:... tools/tops-row-spread.php
 */

// The mono tops row as StackSolver::topRow deals it, widths from the specs.
$w = [0.450, 0.450, 0.4656, 0.500, 0.500, 0.500, 0.4656, 0.450];
$g = 0.02;
$W = 4.40;                     // the 4.4 m stage the width ladder already uses
$sum = array_sum($w);
$n = count($w);

printf("row: %d tops, %.4f m of cabinet, natural width %.4f m, envelope %.2f m\n\n", $n, $sum, $sum + ($n - 1) * $g, $W);

// natural centres: edge to edge with a constant gap
$nat = []; $x = -($sum + ($n - 1) * $g) / 2;
foreach ($w as $i => $wi) { $nat[$i] = $x + $wi / 2; $x += $wi + $g; }

// A: equal air, ends pinned to the envelope
$gapA = ($W - $sum) / ($n - 1);
$a = []; $x = -$W / 2;
foreach ($w as $i => $wi) { $a[$i] = $x + $wi / 2; $x += $wi + $gapA; }

// B: equal pitch, ends pinned to the envelope
$pitch = ($W - $w[0] / 2 - $w[$n - 1] / 2) / ($n - 1);
$b = [];
for ($i = 0; $i < $n; ++$i) { $b[$i] = -$W / 2 + $w[0] / 2 + $i * $pitch; }

// C: what `block` does today — scale every natural centre offset by t until the row spans W
$t = ($W - ($w[0] + $w[$n - 1]) / 2 * 0) / 1; // solve below instead
$lo = 1.0; $hi = 10.0;
for ($k = 0; $k < 200; ++$k) {
    $t = ($lo + $hi) / 2;
    $span = ($nat[$n - 1] * $t + $w[$n - 1] / 2) - ($nat[0] * $t - $w[0] / 2);
    if ($span < $W) { $lo = $t; } else { $hi = $t; }
}
$c = array_map(static fn (float $o): float => $o * $t, $nat);

printf("A  equal air   gap %.4f m everywhere\n", $gapA);
printf("B  equal pitch %.4f m everywhere\n", $pitch);
printf("C  block       factor %.4f\n\n", $t);

printf("%-4s %-8s | %-9s %-9s %-9s | %-9s %-9s %-9s\n", 'i', 'width', 'A centre', 'B centre', 'C centre', 'A gap', 'B gap', 'C gap');
for ($i = 0; $i < $n; ++$i) {
    $ga = $gb = $gc = null;
    if ($i < $n - 1) {
        $ga = ($a[$i + 1] - $w[$i + 1] / 2) - ($a[$i] + $w[$i] / 2);
        $gb = ($b[$i + 1] - $w[$i + 1] / 2) - ($b[$i] + $w[$i] / 2);
        $gc = ($c[$i + 1] - $w[$i + 1] / 2) - ($c[$i] + $w[$i] / 2);
    }
    printf(
        "%-4d %-8.4f | %+9.4f %+9.4f %+9.4f | %-9s %-9s %-9s\n",
        $i, $w[$i], $a[$i], $b[$i], $c[$i],
        $ga === null ? '-' : sprintf('%.4f', $ga),
        $gb === null ? '-' : sprintf('%.4f', $gb),
        $gc === null ? '-' : sprintf('%.4f', $gc),
    );
}

$dab = max(array_map(static fn (float $x, float $y): float => abs($x - $y), $a, $b));
$dac = max(array_map(static fn (float $x, float $y): float => abs($x - $y), $a, $c));
printf("\nworst centre disagreement  A vs B %.1f mm   A vs C %.1f mm\n", $dab * 1000, $dac * 1000);

// What equal pitch costs at its TIGHTEST, which is the number that decides whether a rig fits a stage at all.
// A uniform pitch has to clear the widest adjacent pair, so every narrower pair is left holding more air than it
// needs and the row comes out wider than the equal-air packing of the same cabinets.
$pMin = 0.0;
for ($i = 0; $i < $n - 1; ++$i) {
    $pMin = max($pMin, ($w[$i] + $w[$i + 1]) / 2 + $g);
}
$tightPitch = ($n - 1) * $pMin + ($w[0] + $w[$n - 1]) / 2;
$tightAir = $sum + ($n - 1) * $g;
printf(
    "\ntightest row   equal air %.4f m   equal pitch %.4f m (pitch %.3f m)   cost %.0f mm\n",
    $tightAir,
    $tightPitch,
    $pMin,
    ($tightPitch - $tightAir) * 1000,
);
