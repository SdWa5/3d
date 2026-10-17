"""The Flexy parts data agrees with the published cut list, which cutlistoptimizer.com can import."""

import csv
import importlib.util
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("parts", ROOT / "tools/flexy-plan/parts.py")
parts = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(parts)
PUBLISHED = ROOT / "published/flexy-folded-horn-hybrid/flexy-cutlist.csv"


class FlexyPlanTest(unittest.TestCase):
    def setUp(self):
        self.data = parts.load()

    def test_published_csv_is_generated_from_the_parts_data(self):
        self.assertEqual(PUBLISHED.read_text(encoding="utf-8"), parts.csv_text(self.data))

    def test_csv_has_the_cutlistoptimizer_columns(self):
        rows = list(csv.reader(PUBLISHED.read_text(encoding="utf-8").splitlines()))
        self.assertEqual(rows[0], ["Length", "Width", "Qty", "Material", "Label", "Enabled"])
        for row in rows[1:]:
            self.assertEqual(len(row), 6, row)
            self.assertIn(row[5], ("true", "false"))

    def test_no_field_holds_a_comma_or_a_quote(self):
        # the importer splits on plain commas and knows no quoting
        for row in parts.csv_rows(self.data):
            for field in row:
                self.assertNotIn(",", field)
                self.assertNotIn('"', field)

    def test_every_part_is_18_mm_with_a_positive_quantity(self):
        self.assertEqual(self.data["material"]["thickness_mm"], 18)
        for part in self.data["parts"]:
            self.assertGreater(part["qty"], 0, part["sheet"])

    def test_sheets_4_to_25_each_hold_one_part(self):
        self.assertEqual([p["sheet"] for p in self.data["parts"]], list(range(4, 26)))

    def test_blanks_round_up_and_leave_out_the_lid(self):
        blanks = {p["sheet"]: p["blank"] for p in parts.blanks(self.data)}
        self.assertEqual(blanks[4], (555, 294))
        self.assertEqual(blanks[24], (555, 44))
        self.assertNotIn(14, blanks)

    def test_optional_parts_are_disabled(self):
        enabled = {row[4]: row[5] for row in parts.csv_rows(self.data)}
        self.assertEqual(enabled["FLEXY-07 Frame"], "false")
        self.assertEqual(enabled["FLEXY-21 Side wall"], "true")

    def test_minimum_sheets_is_an_area_bound(self):
        self.assertEqual(parts.minimum_sheets(self.data, (2500, 1250)), 2)
        self.assertGreaterEqual(parts.area_m2(self.data, optional=True), parts.area_m2(self.data))

    def test_numbers_print_like_the_drawings(self):
        self.assertEqual(parts.number(293.12, "de"), "293,12")
        self.assertEqual(parts.number(43.4, "en"), "43.4")
        self.assertEqual(parts.number(555, "de"), "555")


if __name__ == "__main__":
    unittest.main()
