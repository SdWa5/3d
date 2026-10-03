#!/usr/bin/env python3
"""Write symmetric two-row mock-ups of our Achenbach-event stack as explicit scenes under scenes/_solo/.

Stand-in for a generator gap, because StackSolver builds no interleaved or LF-ordered symmetric two-row candidate.
Each row is listed left to right, and the right edge keeps the 0.24 m gap to PSL the generated rig had.

    python3 tools/two-row-variants.py low-raised-ours [more variants] [--into DIR]

`--into` writes somewhere else than scenes/_solo/, for example to compare a rerun with the committed files.
"""
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GAP = 0.02
PSL_LEFT_EDGE = -0.766  # -1.006 + 0.24, measured from the first mock-up
CLEARANCE = 0.24

DEV = {
    'F': ('flexy-folded-horn-hybrid', 0.591, 0.763, 0.0),
    'A': ('achenbach-18', 0.600, 0.600, -0.132),
    'S': ('skram', 0.610, 0.914, -0.0755),
}
TOPS = {
    'T': ('tecnare-m2122', -0.2223, 'far'),
    'W': ('eighteensound-2way-15', -0.1801, 'near'),
}

VARIANTS = {
    'center': {
        'title': 'lowest-reaching type on the centre line, Achenbach outer columns, Flexy in two 3x2 blocks',
        'rows': ['AFFFSFFFA', 'AFFFSFFFA'],
        # top letter, the index in row 2 it stands on, and optionally 'mid' to sit halfway between its neighbours
        'tops': [('T', 1), ('W', 3, 'mid'), ('T', 4), ('W', 5, 'mid'), ('T', 7)],
        # turn a row-1 Flexy or SKRAM over where the same type stands squarely on it, so the two mouths meet at the seam
        'pair_mouths': True,
    },
    'low': {
        'title': 'lowest-reaching type in the bottom row, SKRAMs beside the centre Flexy',
        'rows': ['FFFSFSFFF', 'AAFFFFFAA'],
        'tops': [('T', 0), ('W', 2), ('T', 4), ('W', 6), ('T', 8)],
    },
    'low-raised': {
        'title': 'the low variant with its Achenbach one row up, on the outer Flexys, as in fav-ours-achenbach-flipped',
        'rows': ['FFSFSFF', 'FFFFFFF'],
        # a third row of groups, each centred over a run of row-2 cabinets given by first and last index
        'upper': [('AA', 0, 1), ('AA', 5, 6)],
        # turn every row-1 cabinet of these types over, mouth up
        'flip_row1': 'F',
        # ('r3', k) stands a top on the k-th third-row cabinet instead of on row 2
        'tops': [('T', ('r3', 0)), ('W', ('r3', 1)), ('T', 3), ('W', ('r3', 2)), ('T', ('r3', 3))],
    },
}

# Our stack alone, without PSL, Innschleife and the backdrop, centred on x = 0.
VARIANTS['low-raised-ours'] = dict(
    VARIANTS['low-raised'],
    title='the low-raised variant with our stack alone, a fifth Achenbach under the centre Tecnare',
    solo=True,
    upper=[('AA', 0, 1), ('A', 3, 3), ('AA', 5, 6)],
    tops=[('T', ('r3', 0)), ('W', ('r3', 1)), ('T', ('r3', 2)), ('W', ('r3', 3)), ('T', ('r3', 4))],
)


def layout(row):
    width = sum(DEV[c][1] for c in row) + GAP * (len(row) - 1)
    xs, x = [], -width / 2
    for c in row:
        w = DEV[c][1]
        xs.append(x + w / 2)
        x += w + GAP
    return width, xs


def main(name, into):
    v = VARIANTS[name]
    (w1, x1), (w2, x2) = layout(v['rows'][0]), layout(v['rows'][1])
    width = max(w1, w2)
    x0 = 0.0 if v.get('solo') else PSL_LEFT_EDGE - CLEARANCE - width / 2
    def r(value):
        return round(value, 4)

    lines = []
    ids1 = []
    for i, (c, x) in enumerate(zip(v['rows'][0], x1, strict=True)):
        dev, w, h, y = DEV[c]
        pid = 'ours-r1-%d' % (i + 1)
        ids1.append((pid, x - w / 2, x + w / 2, h))
        lines += ['  - id: %s' % pid, '    device: %s' % dev, '    at: [%s, %s]' % (r(x0 + x), y)]
        above = [c2 for c2, xx in zip(v['rows'][1], x2, strict=True) if abs(xx - x) < v.get('pair_tolerance_m', 0.01)]
        if c in v.get('flip_row1', '') or (v.get('pair_mouths') and c in ('F', 'S') and above == [c]):
            lines.append('    roll_deg: 180')
    ids2 = []
    for i, (c, x) in enumerate(zip(v['rows'][1], x2, strict=True)):
        dev, w, h, y = DEV[c]
        lo, hi = x - w / 2, x + w / 2
        under = [s for s in ids1 if min(hi, s[2]) - max(lo, s[1]) > 0.005]
        support = max(under, key=lambda s: s[3])
        pid = 'ours-r2-%d' % (i + 1)
        ids2.append(pid)
        lines += ['  - id: %s' % pid, '    device: %s' % dev, '    at: [%s, %s]' % (r(x0 + x), y),
                  '    on: %s' % support[0]]
    x3, ids3 = [], []
    for letters, first, last in v.get('upper', []):
        lo = x2[first] - DEV[v['rows'][1][first]][1] / 2
        hi = x2[last] + DEV[v['rows'][1][last]][1] / 2
        widths = [DEV[c][1] for c in letters]
        gap = max(0.0, (hi - lo - sum(widths)) / max(1, len(letters) - 1))
        x = (lo + hi) / 2 - (sum(widths) + gap * (len(letters) - 1)) / 2
        for c, w in zip(letters, widths, strict=True):
            dev, _, h, y = DEV[c]
            centre = x + w / 2
            below = min(range(first, last + 1), key=lambda k: abs(x2[k] - centre))
            pid = 'ours-r3-%d' % (len(ids3) + 1)
            lines += ['  - id: %s' % pid, '    device: %s' % dev, '    at: [%s, %s]' % (r(x0 + centre), y),
                      '    on: %s' % ids2[below]]
            x3.append(centre)
            ids3.append(pid)
            x += w + gap

    def support(idx):
        return (x3[idx[1]], ids3[idx[1]]) if isinstance(idx, tuple) else (x2[idx], ids2[idx])

    tx = [support(top[1])[0] for top in v['tops']]
    for j, top in enumerate(v['tops']):
        if 'mid' in top[2:]:
            tx[j] = (tx[j - 1] + tx[j + 1]) / 2
    for j, (t, idx, *_) in enumerate(v['tops']):
        dev, y, aim = TOPS[t]
        lines += ['  - id: ours-top-%d' % (j + 1), '    device: %s' % dev,
                  '    at: [%s, %s]' % (r(x0 + tx[j]), y), '    on: %s' % support(idx)[1], '    aim: %s' % aim]

    with open(ROOT + '/scenes/_solo/ach-stereo-two-rows.yaml') as f:
        source = f.read()
    tail = '' if v.get('solo') else source[source.index('  - id: main-psl'):]
    head = '\n'.join([
        '# Mock-up. Stands in for a generator gap: StackSolver builds no LF-ordered symmetric',
        '# two-row candidate. Written by tools/two-row-variants.py, %s.' % v['title'],
        '#',
        '#   2  %s    %.3f m' % (' '.join(v['rows'][1]), w2),
        '#   1  %s    %.3f m' % (' '.join(v['rows'][0]), w1),
        'id: ach-stereo-two-rows-%s' % name,
        'name: "Achenbach event, stereo, ours in two rows, LF %s"' % name,
        '',
        '# Our tops aim at our own centre, the way a generated systems-apart stack aims at its own front centre.',
        'focus:',
        '  far:',
        '    distance_m: 10.0',
        '    height_m: 1.8',
        '    x_m: %s' % r(x0),
        '  near:',
        '    distance_m: 2.0',
        '    height_m: 1.8',
        '    x_m: %s' % r(x0),
        '',
        'placements:',
    ])
    path = into + '/ach-stereo-two-rows-%s.yaml' % name
    with open(path, 'w') as f:
        f.write(head + '\n' + '\n'.join(lines) + '\n\n' + tail)
    print('%s rows %.3f / %.3f, centre %.4f -> %s' % (name, w1, w2, x0, path))


args = sys.argv[1:]
into = ROOT + '/scenes/_solo'
if '--into' in args:
    at = args.index('--into')
    into = args[at + 1]
    del args[at:at + 2]
for arg in args:
    main(arg, into)
