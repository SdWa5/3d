<?php

declare(strict_types=1);

/*
 * Ranks the test classes and the single cases that carry a suite's time, read from a JUnit file.
 *
 * TOOL-22 started from the fact that nobody knew which tests the 32 minutes went to. PHPUnit prints a total and
 * nothing else, while `--log-junit` records every case's own time. This turns that file into the two tables that
 * answer the question, so the next regression is a ranking away rather than a guess.
 *
 * The times are what each case took on its own. Under paratest several classes run at once, so their sum is CPU
 * time spent in tests and exceeds the wall clock. Under plain PHPUnit the sum is close to the wall clock, because a
 * case that forks through `Parallel::map()` is timed from the parent and its children are inside that figure.
 *
 *     ddev exec vendor/bin/phpunit --log-junit build/phpunit-junit.xml
 *     ddev exec php tools/phpunit-timing.php build/phpunit-junit.xml [--top=15]
 */

$file = null;
$top = 15;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--top=')) {
        $top = max(1, (int) substr($argument, 6));
    } else {
        $file = $argument;
    }
}

if (null === $file || !is_file($file)) {
    fwrite(STDERR, "usage: php tools/phpunit-timing.php <junit.xml> [--top=N]\n");
    exit(2);
}

$xml = simplexml_load_file($file);
if (false === $xml) {
    fwrite(STDERR, "{$file} is not readable XML\n");
    exit(2);
}

/** @var array<string, array{time: float, cases: int}> $classes */
$classes = [];
/** @var list<array{name: string, time: float}> $cases */
$cases = [];
$total = 0.0;

foreach ($xml->xpath('//testcase') ?: [] as $case) {
    $class = (string) ($case['class'] ?? $case['classname'] ?? '?');
    $time = (float) $case['time'];
    $total += $time;

    $classes[$class] ??= ['time' => 0.0, 'cases' => 0];
    $classes[$class]['time'] += $time;
    ++$classes[$class]['cases'];

    $cases[] = ['name' => short($class).'::'.$case['name'], 'time' => $time];
}

uasort($classes, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);
usort($cases, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

printf("%d cases in %d classes, %s summed over cases\n\n", count($cases), count($classes), clock($total));

printf("%-10s %6s %6s  %s\n", 'time', 'share', 'cases', 'class');
foreach (array_slice($classes, 0, $top, true) as $class => $row) {
    printf("%-10s %5.1f%% %6d  %s\n", clock($row['time']), share($row['time'], $total), $row['cases'], short($class));
}

printf("\n%-10s %6s  %s\n", 'time', 'share', 'case');
foreach (array_slice($cases, 0, $top) as $row) {
    printf("%-10s %5.1f%%  %s\n", clock($row['time']), share($row['time'], $total), mb_strimwidth($row['name'], 0, 140, '…'));
}

function short(string $class): string
{
    return preg_replace('/^App\\\\Tests\\\\/', '', $class) ?? $class;
}

function clock(float $seconds): string
{
    return $seconds >= 60
        ? sprintf('%dm %04.1fs', intdiv((int) $seconds, 60), fmod($seconds, 60))
        : sprintf('%.2fs', $seconds);
}

function share(float $part, float $whole): float
{
    return $whole > 0 ? 100 * $part / $whole : 0.0;
}
