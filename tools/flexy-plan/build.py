"""Build the EN or DE Flexy build plan from the original drawing set.

    .venv/bin/python build.py original.pdf <out.pdf> <en|de> [--rev C] [--date 2026-10-17]

Always regenerate from the original. Each original page gets one overlay with the revised title block, the material
and the notes, and a 26th sheet with the parts list and the cut list is appended. Overlay coordinates are
landscape-A3 points; `y()` turns the top-origin values pdftotext reports into reportlab's bottom origin.
"""

import argparse
import io

import parts
import qrcode
from pypdf import PdfReader, PdfWriter
from reportlab.lib.colors import HexColor, black, white
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas

H = 841.92
FONTS = '/usr/share/fonts/truetype/liberation/'
pdfmetrics.registerFont(TTFont('S', FONTS + 'LiberationSans-Regular.ttf'))
pdfmetrics.registerFont(TTFont('SB', FONTS + 'LiberationSans-Bold.ttf'))
RED = HexColor('#d00000')
GREY = HexColor('#808080')

MODEL = 'https://raw.githubusercontent.com/SdWa5/3d/main/published/flexy-folded-horn-hybrid/flexy-folded-horn-hybrid.glb'
VIEWER = 'https://3dviewer.net/#model=' + MODEL
REPO = 'https://github.com/SdWa5/3d/tree/main/published/flexy-folded-horn-hybrid'
CSV_URL = 'https://github.com/SdWa5/3d/blob/main/published/flexy-folded-horn-hybrid/flexy-cutlist.csv'
OLD = 'https://3dviewer.net/#model=https://github.com/Oletarier/bassw-rfel-folded-horn/blob/main/subwoofer%20v28.obj'

TEXT = {
    'en': {'dept': 'Dept.', 'created': 'Created by', 'tref': 'Technical reference', 'approved': 'Approved by',
               'dtype': 'Document type', 'dstatus': 'Document status', 'title': 'Title', 'dwg': 'DWG No.',
               'rev': 'Rev.',
               'date': 'Date of issue', 'sheet': 'Sheet', 'scale': 'Scale {} on A3', 'noscale': 'No scale, A3',
               'doctype': 'Build plan', 'status': 'revised', 'optional': 'OPTIONAL', 'hint': 'NOTE',
               'material': 'Material',
               'board': 'multiplex plywood',
               'print': 'Print on A3 at 100 % (no fit to page). All dimensions in mm.',
               'cover_material': 'Material: 18 mm multiplex plywood throughout',
               'cover_parts': 'Parts list and cut list: sheet 26',
               'model': '3D model (3dviewer.net):', 'repo': 'Model files (GitHub):', 'old': 'Original CAD (v28):',
               'parts_title': 'Parts list', 'parts_cols': ('Sheet', 'Part', 'L × W', 'Qty', 'Note'),
               'parts_foot': 'All parts 18 mm multiplex plywood. L × W are the outer sizes of the sheets, bevels '
                          'included. The lid has no blank of its own.',
               'optional_part': 'optional',
               'cut_title': 'Cut list for cutlistoptimizer.com',
               'cut_sub': 'Import the CSV, sizes rounded up to whole mm. Optional parts are disabled.',
               'stock_title': 'Recommended stock sheets, 18 mm',
               'stock_row': '{} × {} mm: at least {} sheets ({} with the optional parts)',
               'stock_foot': 'A lower bound from the blank area of {} m² ({} m² with the optional parts). '
                          'Kerf and nesting add to it, so the optimizer gives the real count.',
               'notes': {7: 'The frame may also be built from four separate battens (offcuts).',
                      9: 'Braces: may also be made of metal, for example.',
                      14: 'The lids are cut out of the top panel (sheet 6).',
                      5: 'Same blank as the top panel (sheet 6).',
                      6: 'The cut-out piece becomes the lid (sheet 14).'}},
    'de': {'dept': 'Abteilung', 'created': 'Erstellt von', 'tref': 'Technische Referenz', 'approved': 'Freigegeben von',
               'dtype': 'Dokumentart', 'dstatus': 'Dokumentstatus', 'title': 'Titel', 'dwg': 'Zeichnungsnr.',
               'rev': 'Rev.',
               'date': 'Ausgabedatum', 'sheet': 'Blatt', 'scale': 'Maßstab {} auf A3', 'noscale': 'Ohne Maßstab, A3',
               'doctype': 'Bauplan', 'status': 'überarbeitet', 'optional': 'OPTIONAL', 'hint': 'HINWEIS',
               'material': 'Material',
               'board': 'Multiplex',
               'print': 'Auf A3 in 100 % drucken (nicht „an Seite anpassen“). Alle Maße in mm.',
               'cover_material': 'Material: durchgehend 18 mm Multiplex',
               'cover_parts': 'Teileliste und Zuschnittliste: Blatt 26',
               'model': '3D-Modell (3dviewer.net):', 'repo': 'Modelldateien (GitHub):', 'old': 'Original-CAD (v28):',
               'parts_title': 'Teileliste', 'parts_cols': ('Blatt', 'Teil', 'L × B', 'Stk.', 'Hinweis'),
               'parts_foot': 'Alle Teile aus 18 mm Multiplex. L × B sind die Außenmaße der Blätter, Winkel '
                          'eingeschlossen. Der Deckel hat keinen eigenen Zuschnitt.',
               'optional_part': 'optional',
               'cut_title': 'Zuschnittliste für cutlistoptimizer.com',
               'cut_sub': 'Die CSV importieren, Maße auf ganze mm aufgerundet. Optionale Teile sind deaktiviert.',
               'stock_title': 'Empfohlene Plattenformate, 18 mm',
               'stock_row': '{} × {} mm: mindestens {} Platten ({} mit den optionalen Teilen)',
               'stock_foot': 'Untergrenze aus der Zuschnittfläche von {} m² ({} m² mit den optionalen Teilen). '
                          'Schnittfuge und Verschachtelung kommen hinzu, die echte Zahl liefert der Optimierer.',
               'notes': {7: 'Der Rahmen kann auch aus vier einzelnen Latten (Verschnitt) gebaut werden.',
                      9: 'Verstrebungen: können z. B. auch aus Metall gefertigt werden.',
                      14: 'Die Deckel werden aus der oberen Platte (Blatt 6) ausgeschnitten.',
                      5: 'Gleicher Zuschnitt wie die Deckplatte (Blatt 6).',
                      6: 'Das ausgeschnittene Stück ergibt den Deckel (Blatt 14).'}},
}

# sheet: (scale, EN part, DE part)
SHEETS = {
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
    26: (None, 'Parts list and cut list', 'Teileliste und Zuschnitt'),
}
N = len(SHEETS) + 1  # the cover has no title block
LIST_SHEET = 26
LIST_BASE = 24  # the original sheet whose frame and title block sheet 26 reuses; it is an image with no text


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


class Plan:
    def __init__(self, lang, rev, date):
        self.lang, self.rev, self.date = lang, rev, date
        self.t = dict(TEXT[lang])
        self.t['notes'] = dict(self.t['notes'])
        self.t['notes'][10] = self.t['notes'][11] = self.t['notes'][9]
        self.data = parts.load()

    def label(self, c, x, t, s):
        """Replace a title-block field label (DE only; the original labels are English)."""
        if self.lang == 'de':
            box(c, x - 0.5, t - 7.5, x + 72, t + 1.6)
            text(c, x, t, s, 6.7, maxw=70)

    def title_block(self, c, n):
        t = self.t
        scale, en, de = SHEETS[n]
        self.label(c, 655, 688.5, t['dept'])
        self.label(c, 723, 688.5, t['tref'])
        self.label(c, 819, 688.5, t['created'])
        self.label(c, 992, 688.5, t['approved'])
        self.label(c, 819, 716.8, t['dtype'])
        self.label(c, 992, 716.8, t['dstatus'])
        self.label(c, 819, 745.3, t['title'])
        self.label(c, 992, 745.3, t['dwg'])
        if self.lang == 'de':
            box(c, 1019, 786.6, 1062, 795.5)
            text(c, 1020, 793.4, t['date'], 6.7)
            box(c, 1121, 786.6, 1150, 795.5)
            text(c, 1122, 793.4, t['sheet'], 6.7)
        # values
        text(c, 655, 704, 'SdWa5', 11)
        box(c, 720.5, 691, 815, 707.5)
        text(c, 723, 704, t['scale'].format(scale) if scale else t['noscale'], 11, maxw=90)
        text(c, 992, 704, 'Stefan Ripper', 13)
        text(c, 819, 732, t['doctype'], 13)
        text(c, 992, 732, t['status'], 13)
        box(c, 818, 747, 987, 776)
        text(c, 819, 760, 'MrFlexySMPs', 14)
        text(c, 819, 776, 'Folded Horn Hybrid', 14)
        text(c, 819, 799, en if self.lang == 'en' else de, 11, font='SB', maxw=166)
        text(c, 992, 775, f'FLEXY-{n:02d}', 16)
        text(c, 1000, 809, self.rev, 11)
        text(c, 1020, 809, self.date, 11)
        box(c, 1121, 796.5, 1158.5, 812.5)
        sheet = f'{n}/{N}'
        text(c, 1157 - pdfmetrics.stringWidth(sheet, 'S', 14), 810.5, sheet, 14)
        self.material(c)

    def material(self, c):
        """The board in the empty lower-left cell of the title block, large enough to see from across the shop."""
        text(c, 655, 718, self.t['material'], 6.7)
        text(c, 660, 760, '18 mm', 32, font='SB')
        text(c, 660, 788, self.t['board'], 15, font='SB', maxw=150)

    def note(self, c, n):
        notes = self.t['notes']
        if n not in notes:
            return
        text(c, 80, 95, self.t['hint'] if n in (5, 6) else self.t['optional'], 22, font='SB', color=RED)
        text(c, 80, 122, notes[n], 16, color=RED, maxw=560)

    def qr(self, c, data, x, top, size):
        img = qrcode.make(data, border=1, error_correction=qrcode.constants.ERROR_CORRECT_M).get_image()
        buf = io.BytesIO()
        img.save(buf, format='PNG')
        c.drawImage(ImageReader(io.BytesIO(buf.getvalue())), x, y(top + size), size, size)

    def cover(self, c):
        t = self.t
        # the render ends at ca. 453 pt from the top; the old label and QR sit below it
        box(c, 0, 455, 1190.52, H)
        text(c, 60, 500, 'MrFlexySMPs Folded Horn Hybrid', 28, font='SB')
        text(c, 60, 528, f"{t['doctype']} · Rev. {self.rev} · {self.date}", 14)
        text(c, 60, 556, t['print'], 14, color=RED, maxw=620)
        text(c, 60, 592, t['cover_material'], 20, font='SB', maxw=620)
        text(c, 60, 620, t['cover_parts'], 14, maxw=620)
        for i, (lab, url) in enumerate([(t['model'], VIEWER), (t['repo'], REPO)]):
            x = 700 + i * 250
            text(c, x, 480, lab, 11, font='SB')
            self.qr(c, url, x, 488, 170)
            c.linkURL(url, (x, y(488 + 170), x + 170, y(488)), relative=0, thickness=0)
        for i, (lab, url) in enumerate([(t['model'], VIEWER), (t['repo'], REPO), (t['old'], OLD)]):
            self.link(c, 60, 700 + i * 16, lab, url, 185)

    def link(self, c, x, t, lab, url, ux, maxw=990):
        text(c, x, t, lab, 8, font='SB')
        size = text(c, ux, t, url, 8, maxw=maxw)
        w = pdfmetrics.stringWidth(url, 'S', size)
        c.linkURL(url, (ux, y(t) - 2, ux + w, y(t) + size), relative=0, thickness=0)

    def table(self, c, x, top, cols, rows, size, step, right):
        """Columns as (title, x offset); a rule under the header and a light one after every fifth row."""
        for title, dx in cols:
            text(c, x + dx, top, title, size, font='SB')
        c.setStrokeColor(black)
        c.setLineWidth(0.6)
        c.line(x, y(top + 4), right, y(top + 4))
        c.setStrokeColor(GREY)
        c.setLineWidth(0.3)
        for i, row in enumerate(rows, 1):
            t = top + i * step
            for (_, dx), (value, maxw) in zip(cols, row, strict=True):
                text(c, x + dx, t, value, size, maxw=maxw)
            if i % 5 == 0 and i < len(rows):
                c.line(x, y(t + 4), right, y(t + 4))
        return top + len(rows) * step

    def parts_list(self, c):
        t, lang = self.t, self.lang
        text(c, 70, 64, t['parts_title'], 18, font='SB')
        rows = []
        for part in self.data['parts']:
            name = part[lang] + (f" ({t['optional_part']})" if part.get('optional') else '')
            size = f"{parts.number(part['length'], lang)} × {parts.number(part['width'], lang)}"
            rows.append([(str(part['sheet']), 40), (name, 180), (size, 115), (str(part['qty']), 35),
                         (part['note_' + lang], 690)])
        cols = list(zip(t['parts_cols'], (0, 45, 235, 360, 400), strict=True))
        end = self.table(c, 70, 90, cols, rows, 9, 14.5, 1150)
        text(c, 70, end + 20, t['parts_foot'], 9, maxw=1080)

    def cut_list(self, c):
        t = self.t
        text(c, 70, 478, t['cut_title'], 14, font='SB')
        text(c, 70, 494, t['cut_sub'], 8.5, maxw=560)
        rows = [[(v, w) for v, w in zip(r, (40, 40, 30, 75, 160, 40), strict=True)] for r in parts.csv_rows(self.data)]
        cols = list(zip(parts.CSV_HEADER, (0, 45, 90, 125, 205, 370), strict=True))
        end = self.table(c, 70, 512, cols, rows, 7.5, 12.2, 480)
        self.link(c, 70, end + 16, 'CSV:', CSV_URL, 95, maxw=480)

    def stock(self, c):
        t, d = self.t, self.data
        text(c, 660, 478, t['stock_title'], 14, font='SB')
        for i, (w, h) in enumerate(d['stock_mm']):
            row = t['stock_row'].format(w, h, parts.minimum_sheets(d, (w, h)), parts.minimum_sheets(d, (w, h), True))
            text(c, 660, 500 + i * 16, row, 10, maxw=495)
        area = [parts.number(round(parts.area_m2(d, opt), 2), self.lang) for opt in (False, True)]
        top = 500 + len(d['stock_mm']) * 16 + 8
        words, line = t['stock_foot'].format(*area).split(), ''
        for word in words:  # wrap at the column width
            if pdfmetrics.stringWidth(f'{line} {word}', 'S', 8.5) > 495:
                text(c, 660, top, line, 8.5)
                top, line = top + 12, word
            else:
                line = f'{line} {word}'.strip()
        text(c, 660, top, line, 8.5)

    def list_sheet(self, c):
        # clear the base sheet's drawing inside the frame, keeping the frame, the zone marks and the title block
        box(c, 58.5, 30.5, 1161, 680)
        box(c, 58.5, 679, 651, 813)
        self.parts_list(c)
        self.cut_list(c)
        self.stock(c)

    def overlay(self, n, w, h):
        buf = io.BytesIO()
        c = canvas.Canvas(buf, pagesize=(w, h), invariant=1)
        if n == 19:  # portrait page with the drawing rotated by 90°
            c.translate(0, h)
            c.rotate(-90)
        if n == 1:
            self.cover(c)
        else:
            if n == LIST_SHEET:
                self.list_sheet(c)
            self.title_block(c, n)
            self.note(c, n)
        c.showPage()
        c.save()
        return PdfReader(io.BytesIO(buf.getvalue())).pages[0]

    def build(self, src, out):
        pages = list(PdfReader(src).pages)
        pages.append(PdfReader(src).pages[LIST_BASE - 1])  # a second reader, so the copy shares nothing it changes
        wr = PdfWriter()
        for n, p in enumerate(pages, 1):
            p.merge_page(self.overlay(n, float(p.mediabox.width), float(p.mediabox.height)))
            if n == 19:  # drawn turned on a portrait sheet; display it landscape like the rest
                p.rotate(270)
            wr.add_page(p)
        wr.add_metadata({'/Title': f"MrFlexySMPs Folded Horn Hybrid – {self.t['doctype']} Rev. {self.rev}",
                         '/Author': f'Oliver Ripper; Rev. {self.rev} Stefan Ripper'})
        wr.compress_identical_objects(remove_identicals=True, remove_orphans=True)
        with open(out, 'wb') as f:
            wr.write(f)


def main():
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument('src')
    ap.add_argument('out')
    ap.add_argument('lang', choices=sorted(TEXT))
    ap.add_argument('--rev', default='C')
    ap.add_argument('--date', default='2026-10-17')
    args = ap.parse_args()
    Plan(args.lang, args.rev, args.date).build(args.src, args.out)


if __name__ == '__main__':
    main()
