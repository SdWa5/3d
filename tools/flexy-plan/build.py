"""Build the EN/DE v2 Flexy build plan: python v2.py <src.pdf> <out.pdf> <en|de>.

Always regenerate from the original. Overlay coordinates are landscape-A3 points, bottom-left origin.
"""
import io
import sys

import qrcode
from pypdf import PdfReader, PdfWriter
from reportlab.lib.colors import HexColor, white, black
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader

src, out, lang = sys.argv[1:4]
H = 841.92
pdfmetrics.registerFont(TTFont('S', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'))
pdfmetrics.registerFont(TTFont('SB', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'))
RED = HexColor('#d00000')

MODEL = 'https://raw.githubusercontent.com/SdWa5/3d/main/published/flexy-folded-horn-hybrid/flexy-folded-horn-hybrid.glb'
VIEWER = 'https://3dviewer.net/#model=' + MODEL
REPO = 'https://github.com/SdWa5/3d/tree/main/published/flexy-folded-horn-hybrid'
OLD = 'https://3dviewer.net/#model=https://github.com/Oletarier/bassw-rfel-folded-horn/blob/main/subwoofer%20v28.obj'

T = {
    'en': dict(dept='Dept.', created='Created by', tref='Technical reference', approved='Approved by', dtype='Document type',
               dstatus='Document status', title='Title', dwg='DWG No.', rev='Rev.', date='Date of issue',
               sheet='Sheet', scale='Scale {} on A3', doctype='Build plan', status='revised',
               optional='OPTIONAL', hint='NOTE',
               print='Print on A3 at 100 % (no fit to page). All dimensions in mm.',
               model='3D model (3dviewer.net):', repo='Model files (GitHub):', old='Original CAD (v28):',
               notes={7: 'The frame may also be built from four separate battens (offcuts).',
                      9: 'Braces: may also be made of metal, for example.',
                      14: 'The lids are cut out of the top panel (sheet 6).',
                      5: 'Same blank as the top panel (sheet 6).',
                      6: 'The cut-out piece becomes the lid (sheet 14).'}),
    'de': dict(dept='Abteilung', created='Erstellt von', tref='Technische Referenz', approved='Freigegeben von', dtype='Dokumentart',
               dstatus='Dokumentstatus', title='Titel', dwg='Zeichnungsnr.', rev='Rev.', date='Ausgabedatum',
               sheet='Blatt', scale='Maßstab {} auf A3', doctype='Bauplan', status='überarbeitet',
               optional='OPTIONAL', hint='HINWEIS',
               print='Auf A3 in 100 % drucken (nicht „an Seite anpassen“). Alle Maße in mm.',
               model='3D-Modell (3dviewer.net):', repo='Modelldateien (GitHub):', old='Original-CAD (v28):',
               notes={7: 'Der Rahmen kann auch aus vier einzelnen Latten (Verschnitt) gebaut werden.',
                      9: 'Verstrebungen: können z. B. auch aus Metall gefertigt werden.',
                      14: 'Die Deckel werden aus der oberen Platte (Blatt 6) ausgeschnitten.',
                      5: 'Gleicher Zuschnitt wie die Deckplatte (Blatt 6).',
                      6: 'Das ausgeschnittene Stück ergibt den Deckel (Blatt 14).'}),
}[lang]
T['notes'][10] = T['notes'][11] = T['notes'][9]

# page: (scale, EN part, DE part)
PARTS = {
    2: ('1:10', 'Assembly views', 'Gesamtansichten'),
    3: ('1:5', 'Side section', 'Seitenschnitt'),
    4: ('1:5', 'Panel 555 × 293.12', 'Platte 555 × 293,12'),
    5: ('1:5', 'Bottom panel 964 × 555', 'Bodenplatte 964 × 555'),
    6: ('1:5', 'Top panel with lid opening', 'Deckplatte mit Deckelausschnitt'),
    7: ('1:5', 'Frame 556 × 555', 'Rahmen 556 × 555'),
    8: ('1:1', 'Trapezoid strip 286.74 (2×)', 'Trapezleiste 286,74 (2×)'),
    9: ('1:5', 'Brace 100 × 555', 'Verstrebung 100 × 555'),
    10: ('1:5', 'Brace 127.89 × 555', 'Verstrebung 127,89 × 555'),
    11: ('1:5', 'Brace plate', 'Verstrebungsplatte'),
    12: ('1:5', 'Strip 77.78 × 555', 'Leiste 77,78 × 555'),
    13: ('1:5', 'Panel 296.98 × 555', 'Platte 296,98 × 555'),
    14: ('1:5', 'Lid 496 × 508', 'Deckel 496 × 508'),
    15: ('1:2', 'Trapezoid 322.78 (2×)', 'Trapez 322,78 (2×)'),
    16: ('1:2', 'Strip 60 × 555', 'Leiste 60 × 555'),
    17: ('1:5', 'Panel 344.25 × 555', 'Platte 344,25 × 555'),
    18: ('1:5', 'Baffle with driver cutout', 'Schallwand mit Treiberausschnitt'),
    19: ('1:5', 'Panel 487 × 555', 'Platte 487 × 555'),
    20: ('1:5', 'Panel 555 × 727', 'Platte 555 × 727'),
    21: ('1:5', 'Side wall (2×)', 'Seitenwand (2×)'),
    22: ('1:1', 'Block 140 × 60', 'Klotz 140 × 60'),
    23: ('1:5', 'Panel 174.26 × 555', 'Platte 174,26 × 555'),
    24: ('1:5', 'Strip 555', 'Leiste 555'),
    25: ('1:5', 'Panel 555 × 356', 'Platte 555 × 356'),
}
N = 25


def y(top):
    """Convert a top-origin y (as pdftotext reports it) to bottom-origin."""
    return H - top


def box(c, x0, t0, x1, t1):
    c.setFillColor(white)
    c.rect(x0, y(t1), x1 - x0, t1 - t0, stroke=0, fill=1)


def text(c, x, t, s, size, font='S', color=black, maxw=None):
    while maxw and pdfmetrics.stringWidth(s, font, size) > maxw and size > 5:
        size -= 0.25
    c.setFillColor(color)
    c.setFont(font, size)
    c.drawString(x, y(t), s)
    return size


def label(c, x, t, s):
    """Replace a title-block field label (DE only; the original labels are English)."""
    if lang == 'de':
        box(c, x - 0.5, t - 7.5, x + 72, t + 1.6)
        text(c, x, t, s, 6.7, maxw=70)


def title_block(c, n):
    scale, en, de = PARTS[n]
    label(c, 655, 688.5, T['dept'])
    label(c, 723, 688.5, T['tref'])
    label(c, 819, 688.5, T['created'])
    label(c, 992, 688.5, T['approved'])
    label(c, 819, 716.8, T['dtype'])
    label(c, 992, 716.8, T['dstatus'])
    label(c, 819, 745.3, T['title'])
    label(c, 992, 745.3, T['dwg'])
    if lang == 'de':
        box(c, 1019, 786.6, 1062, 795.5)
        text(c, 1020, 793.4, T['date'], 6.7)
        box(c, 1121, 786.6, 1150, 795.5)
        text(c, 1122, 793.4, T['sheet'], 6.7)
    # values
    text(c, 655, 704, 'SdWa5', 11)
    box(c, 720.5, 691, 815, 707.5)
    text(c, 723, 704, T['scale'].format(scale), 11, maxw=90)
    text(c, 992, 704, 'Stefan Ripper', 13)
    text(c, 819, 732, T['doctype'], 13)
    text(c, 992, 732, T['status'], 13)
    box(c, 818, 747, 987, 776)
    text(c, 819, 760, 'MrFlexySMPs', 14)
    text(c, 819, 776, 'Folded Horn Hybrid', 14)
    text(c, 819, 799, en if lang == 'en' else de, 11, font='SB', maxw=166)
    text(c, 992, 775, f'FLEXY-{n:02d}', 16)
    text(c, 1000, 809, 'B', 11)
    text(c, 1020, 809, '2026-10-16', 11)
    box(c, 1121, 796.5, 1158.5, 812.5)
    sheet = f'{n}/{N}'
    text(c, 1157 - pdfmetrics.stringWidth(sheet, 'S', 14), 810.5, sheet, 14)


def note(c, n):
    if n not in T['notes']:
        return
    text(c, 80, 95, T['hint'] if n in (5, 6) else T['optional'], 22, font='SB', color=RED)
    text(c, 80, 122, T['notes'][n], 16, color=RED, maxw=560)


def qr(c, data, x, top, size):
    img = qrcode.make(data, border=1, error_correction=qrcode.constants.ERROR_CORRECT_M).get_image()
    buf = io.BytesIO()
    img.save(buf, format='PNG')
    c.drawImage(ImageReader(io.BytesIO(buf.getvalue())), x, y(top + size), size, size)


def cover(c):
    # the render ends at ca. 453 pt from the top; the old label and QR sit below it
    box(c, 0, 455, 1190.52, H)
    text(c, 60, 500, 'MrFlexySMPs Folded Horn Hybrid', 28, font='SB')
    text(c, 60, 528, T['doctype'] + ' · Rev. B · 2026-10-16', 14)
    text(c, 60, 556, T['print'], 14, color=RED)
    for i, (lab, url) in enumerate([(T['model'], VIEWER), (T['repo'], REPO)]):
        x = 700 + i * 250
        text(c, x, 480, lab, 11, font='SB')
        qr(c, url, x, 488, 170)
        c.linkURL(url, (x, y(488 + 170), x + 170, y(488)), relative=0, thickness=0)
    for i, (lab, url) in enumerate([(T['model'], VIEWER), (T['repo'], REPO), (T['old'], OLD)]):
        text(c, 60, 700 + i * 16, lab, 8, font='SB')
        size = text(c, 185, 700 + i * 16, url, 8, maxw=990)
        w = pdfmetrics.stringWidth(url, 'S', size)
        c.linkURL(url, (185, y(700 + i * 16) - 2, 185 + w, y(700 + i * 16) + size), relative=0, thickness=0)


def overlay(n, w, h):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=(w, h))
    if n == 19:  # portrait page with the drawing rotated by 90°
        c.translate(0, h)
        c.rotate(-90)
    if n == 1:
        cover(c)
    else:
        title_block(c, n)
        note(c, n)
    c.showPage()
    c.save()
    return PdfReader(io.BytesIO(buf.getvalue())).pages[0]


r = PdfReader(src)
wr = PdfWriter()
for n, p in enumerate(r.pages, 1):
    p.merge_page(overlay(n, float(p.mediabox.width), float(p.mediabox.height)))
    if n == 19:  # drawn turned on a portrait sheet; display it landscape like the rest
        p.rotate(270)
    wr.add_page(p)
wr.add_metadata({'/Title': 'MrFlexySMPs Folded Horn Hybrid – ' + T['doctype'] + ' Rev. B',
                 '/Author': 'Oliver Ripper; Rev. B Stefan Ripper'})
wr.compress_identical_objects(remove_identicals=True, remove_orphans=True)
with open(out, 'wb') as f:
    wr.write(f)
