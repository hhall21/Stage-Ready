# Stage-Ready handover PDF preview

This is the sample design for a client coaching handover. It is a standalone
preview, using fictional data. It is not connected to the coach dashboard or the
application database.

Open [the sample PDF](sample-handover.pdf) to review the design.

The first three pages cover bodyweight milestones, exercise personal bests, and
coach-recorded strategies. Up to ten additional coach notes follow in the same
dark layout and white typography. Pages are added as needed without reducing the
text size. A note longer than one page keeps its number and repeats its heading
with "continued" on the following page. Page totals include the whole report.

Notes that fit on a fresh page stay together. With no additional notes, the
preview has just the original three pages. The sample notes deliberately include
one oversized note to demonstrate continuation.

## Generate the preview

Use Python 3.10 or newer. From this directory, install the preview dependencies
in your Python environment and run:

```sh
python -m pip install -r requirements.txt
python generate_preview.py --output sample-handover.pdf
```

The generator uses standard PDF fonts and accepts an output path. Collaborators
can use their own storage; no Windows fonts or D: drive are required. Python is
only needed to work on this design preview, not to run the Stage-Ready website.

To try different notes, provide a JSON file with up to ten entries:

```json
[
    {
        "title": "Training observations",
        "period": "JAN - OCT 2026",
        "body": "A full coach note.\n\nA second paragraph can follow."
    }
]
```

```sh
python generate_preview.py --notes my-notes.json --output my-preview.pdf
```

Use `[]` for no additional notes. Each entry requires a nonblank title, period,
and body. Input is plain text: markup is printed literally, and paragraph breaks
are preserved. Invalid input is rejected before creating the output.
The preview font supports Western European text. Characters outside its supported
character set are rejected with an error instead of being silently substituted.

The first three pages remain fictional fixtures when custom notes are supplied.
Use fictional notes for design review too; this script is not a client-data export.

## Verify pagination and content

```sh
python -m unittest discover -s . -p "test_*.py"
```

The tests check empty, short, ten-note, and multi-page cases; wrapped headings;
plain-text symbols and line endings; invalid inputs; and correct page totals.
They extract each rendered note body and compare it with the complete input to
detect omitted, duplicated, or reordered text.

After changing the layout, render and inspect every page as well. With Poppler
installed:

```sh
pdftoppm -r 110 -png sample-handover.pdf handover-page
```

Keep renders and verification output in your own temporary storage. On Haydy's
computer, use the Seagate storage paths described in the project preferences.
