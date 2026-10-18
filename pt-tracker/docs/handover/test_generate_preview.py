"""Check rendered PDF content and pagination, without connecting to Stage-Ready."""

import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest

import pdfplumber
from pypdf import PdfReader

from generate_preview import build_preview


HERE = Path(__file__).resolve().parent


def normalized(value):
    return re.sub(r"\s+", " ", value).strip()


def note(body="A complete coaching note.", title="Training notes", period="JAN - OCT 2026"):
    return {"title": title, "period": period, "body": body}


class HandoverPreviewTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="stage-ready-handover-")
        self.addCleanup(self.temp.cleanup)
        self.output = Path(self.temp.name) / "preview.pdf"

    def check_pdf(self, notes, layout):
        reader = PdfReader(self.output)
        total = len(reader.pages)
        self.assertGreaterEqual(total, 3)
        for index, page in enumerate(reader.pages, 1):
            page_text = normalized(page.extract_text())
            self.assertIn("Stage-Ready", page_text)
            self.assertIn("Fictional client and data", page_text)
            self.assertIn(f"{index:02d} / {total:02d}", page_text)
            self.assertNotIn("PT Tracker", page_text)

        bodies = {index: [] for index in range(1, len(notes) + 1)}
        order = [segment["note"] for segment in layout]
        self.assertEqual(order, sorted(order), "Each note must finish before the next starts")
        previous_bottom = {}
        with pdfplumber.open(self.output) as pdf:
            for segment in layout:
                page_number = segment["page"]
                self.assertIn(segment["note"], bodies)
                self.assertGreaterEqual(page_number, 4)
                self.assertLessEqual(page_number, total)
                self.assertGreaterEqual(segment["top"], 223)
                self.assertLessEqual(segment["bottom"], 761.01)
                self.assertGreaterEqual(
                    segment["top"], previous_bottom.get(page_number, 223)
                )
                previous_bottom[page_number] = segment["bottom"]
                x0, y0, x1, y1 = segment["body_box"]
                self.assertLess(x0, x1)
                self.assertLess(y0, y1)
                self.assertGreaterEqual(y0, segment["top"])
                self.assertLessEqual(y1, segment["bottom"])
                for field in ("title_box", "period_box"):
                    metadata_box = segment[field]
                    self.assertLess(metadata_box[1], metadata_box[3])
                    self.assertLessEqual(metadata_box[3], y0)
                page = pdf.pages[page_number - 1]
                content = page.crop((x0, y0, x1, y1)).extract_text(x_tolerance=2)
                self.assertTrue(content, "A rendered note segment must contain text")
                bodies[segment["note"]].append(content)

        # Exact equality detects missing, repeated, or reordered prose across pages.
        for index, entry in enumerate(notes, 1):
            self.assertEqual(
                normalized(entry["body"]),
                normalized(" ".join(bodies[index])),
                f"Note {index} changed during PDF pagination",
            )
        return reader

    def test_import_has_no_output_side_effects(self):
        code = f"import sys; sys.path.insert(0, {str(HERE)!r}); import generate_preview"
        result = subprocess.run(
            [sys.executable, "-B", "-c", code],
            cwd=self.temp.name,
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(list(Path(self.temp.name).iterdir()), [])

    def test_zero_notes_does_not_add_empty_pages(self):
        layout = build_preview(self.output, [])
        reader = self.check_pdf([], layout)
        self.assertEqual(layout, [])
        self.assertEqual(len(reader.pages), 3)

    def test_one_short_note_uses_one_extra_page(self):
        notes = [note()]
        layout = build_preview(self.output, notes)
        reader = self.check_pdf(notes, layout)
        self.assertEqual(len(reader.pages), 4)
        self.assertFalse(layout[0]["continued"])

    def test_all_ten_sample_notes_survive_including_continuations(self):
        notes = json.loads((HERE / "sample_notes.json").read_text(encoding="utf-8"))
        self.assertEqual(len(notes), 10)
        layout = build_preview(self.output)
        reader = self.check_pdf(notes, layout)
        self.assertGreater(len(reader.pages), 4)
        self.assertTrue(any(part["continued"] for part in layout))
        self.assertEqual({part["note"] for part in layout}, set(range(1, 11)))

    def test_very_long_note_continues_before_the_next_note(self):
        body = "\n\n".join(
            f"Review {index:03d}: The coach recorded the completed sessions, exercise "
            "setup and the client's comments so that the next review kept its context."
            for index in range(80)
        )
        notes = [note(body), note("This second note must follow the entire first note.")]
        layout = build_preview(self.output, notes)
        reader = self.check_pdf(notes, layout)
        first = [part for part in layout if part["note"] == 1]
        self.assertGreaterEqual(len(first), 2)
        self.assertFalse(first[0]["continued"])
        self.assertTrue(all(part["continued"] for part in first[1:]))
        self.assertGreaterEqual(layout[-1]["page"], first[-1]["page"])
        self.assertIn("(continued)", " ".join(p.extract_text() for p in reader.pages))

    def test_plain_text_markup_and_line_endings_are_preserved(self):
        notes = [note("Coach & athlete: keep <b>literal text</b>.\r\n\r\n"
                      "Record load < 50 kg or > 60 kg as written.\r"
                      "Paragraphs and symbols should survive.")]
        self.check_pdf(notes, build_preview(self.output, notes))

    def test_long_title_and_period_wrap_without_overlapping_the_body(self):
        notes = [note(
            "The complete note body must remain below the wrapped title and period.",
            title="Exercise setup and coaching context " * 5,
            period="Recorded during the growth phase and reviewed with the client " * 3,
        )]
        layout = build_preview(self.output, notes)
        self.check_pdf(notes, layout)
        with pdfplumber.open(self.output) as pdf:
            segment = layout[0]
            page = pdf.pages[segment["page"] - 1]
            for field, key in (("title_box", "title"), ("period_box", "period")):
                rendered = page.crop(segment[field]).extract_text(x_tolerance=2)
                self.assertEqual(normalized(rendered), normalized(notes[0][key]))

    def test_unsupported_characters_are_rejected_before_writing(self):
        with self.assertRaises(ValueError):
            build_preview(self.output, [note("Unsupported symbol: \U0001f3cb")])
        self.assertFalse(self.output.exists())

    def test_oversized_metadata_is_rejected_before_writing(self):
        with self.assertRaises(ValueError):
            build_preview(self.output, [note(title="A very long title " * 500)])
        self.assertFalse(self.output.exists())

    def test_cli_rejects_null_instead_of_loading_sample_notes(self):
        input_path = Path(self.temp.name) / "notes.json"
        input_path.write_text("null", encoding="utf-8")
        result = subprocess.run(
            [sys.executable, "-B", str(HERE / "generate_preview.py"),
             "--notes", str(input_path), "--output", str(self.output)],
            cwd=self.temp.name,
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 2)
        self.assertIn("JSON list", result.stderr)
        self.assertFalse(self.output.exists())

    def test_eleven_notes_are_rejected_before_writing(self):
        with self.assertRaises(ValueError):
            build_preview(self.output, [note() for _ in range(11)])
        self.assertFalse(self.output.exists())

    def test_invalid_note_shapes_are_rejected_before_writing(self):
        invalid = [
            "not a list", {}, [None], [{}],
            [{"title": "", "period": "2026", "body": "Text"}],
            [{"title": "Title", "period": "2026", "body": "   "}],
            [{"title": "Title", "period": "2026", "body": 123}],
        ]
        for notes in invalid:
            with self.subTest(notes=notes):
                with self.assertRaises(ValueError):
                    build_preview(self.output, notes)
                self.assertFalse(self.output.exists())


if __name__ == "__main__":
    unittest.main()
