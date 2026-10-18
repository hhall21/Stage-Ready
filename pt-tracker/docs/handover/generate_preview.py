"""Portable fictional Stage-Ready handover preview; no app or database access."""

import argparse
from datetime import date, timedelta
import json
from math import sin
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph


W, H = A4
M = 44
CW = W - M * 2
BG = colors.HexColor('#08090B')
PANEL = colors.HexColor('#121417')
WHITE = colors.HexColor('#FAFAFA')
MUTED = colors.HexColor('#BEC1C7')
LINE = colors.HexColor('#35383E')

class NumberedCanvas(canvas.Canvas):
    """Apply final page totals after all automatically flowed notes are laid out."""

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.saved_states = []

    def showPage(self):
        self.saved_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        total = len(self.saved_states)
        for state in self.saved_states:
            self.__dict__.update(state)
            self.setFillColor(WHITE)
            self.setFont('Helvetica-Bold', 8)
            self.drawRightString(W - M, H - 804, f'{self._pageNumber:02d} / {total:02d}')
            canvas.Canvas.showPage(self)
        canvas.Canvas.save(self)



def _markup(value):
    return escape(value).replace("\n", "<br/>")


def _validated_notes(notes):
    if notes is None:
        notes = json.loads(Path(__file__).with_name("sample_notes.json").read_text(encoding="utf-8"))
    if not isinstance(notes, list) or len(notes) > 10:
        raise ValueError("Coach notes must be a list containing at most ten entries.")
    result = []
    for index, note in enumerate(notes, 1):
        if not isinstance(note, dict):
            raise ValueError(f"Coach note {index} must be an object.")
        clean = {}
        for field in ("title", "period", "body"):
            value = note.get(field)
            if not isinstance(value, str) or not value.strip():
                raise ValueError(f"Coach note {index} requires a nonblank {field} string.")
            value = value.replace("\r\n", "\n").replace("\r", "\n")
            try:
                value.encode("cp1252")
            except UnicodeEncodeError as error:
                raise ValueError(f"Coach note {index} {field} contains a character unsupported by the preview fonts.") from error
            if any((ord(char) < 32 and char != "\n") or ord(char) == 127 for char in value):
                raise ValueError(f"Coach note {index} {field} contains an unsupported control character.")
            clean[field] = value
        result.append(clean)
    return result


def build_preview(output_path: Path, notes: list[dict] | None = None) -> list[dict]:
    """Write the three approved sample pages and optional notes; return note segments.

    Segment coordinates use PDF points measured from the page's top-left corner.
    The preview accepts WinAnsi text for its portable standard Helvetica fonts.
    """
    notes = _validated_notes(notes)
    note_width = CW - 55
    note_style = ParagraphStyle("note", fontName="Helvetica", fontSize=9, leading=13,
                               textColor=WHITE, allowWidows=0, allowOrphans=0,
                               splitLongWords=True)
    title_style = ParagraphStyle("note-title", fontName="Helvetica-Bold", fontSize=12,
                                leading=15, textColor=WHITE, splitLongWords=True)
    period_style = ParagraphStyle("note-period", fontName="Helvetica", fontSize=8,
                                 leading=11, textColor=MUTED, splitLongWords=True)

    def metadata(note, continued):
        title = Paragraph(_markup(note["title"] + (" (continued)" if continued else "")), title_style)
        period = Paragraph(_markup(note["period"]), period_style)
        _, title_height = title.wrap(note_width, H * 1000)
        _, period_height = period.wrap(note_width, H * 1000)
        body_offset = 13 + title_height + 5 + period_height + 10
        return title, period, title_height, period_height, body_offset

    # Validate both initial and continuation metadata before writing any output.
    for index, note in enumerate(notes, 1):
        for continued in (False, True):
            if metadata(note, continued)[4] + 12 + 26 > 761 - 223:
                raise ValueError(f"Coach note {index} title and period leave insufficient room for body text.")

    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    c = NumberedCanvas(str(output_path), pagesize=A4)
    c.setTitle("Stage-Ready | Coaching handover - sample template")
    c.setAuthor("Stage-Ready")
    c.setSubject("Fictional sample for layout approval. No real client data.")
    c.setCreator("Stage-Ready handover template preview")

    def text(x, top, value, size=10, font='Helvetica', fill=WHITE, align='left'):
        c.setFillColor(fill)
        c.setFont(font, size)
        y = H - top
        if align == 'right':
            c.drawRightString(x, y, value)
        elif align == 'center':
            c.drawCentredString(x, y, value)
        else:
            c.drawString(x, y, value)
        width = pdfmetrics.stringWidth(value, font, size)
        left = x - width if align == 'right' else x - width / 2 if align == 'center' else x
        assert left >= M - 1 and left + width <= W - M + 1, (value, left, width)


    def para(x, top, width, value, size=10, leading=15, fill=MUTED, font='Helvetica'):
        style = ParagraphStyle('p', fontName=font, fontSize=size, leading=leading,
                               textColor=fill, spaceAfter=0, allowWidows=0, allowOrphans=0)
        p = Paragraph(value, style)
        _, height = p.wrap(width, H)
        p.drawOn(c, x, H - top - height)
        assert top + height < 781, (value, top, height)
        return height


    def line(top, x=M, width=CW, colour=LINE, weight=.65):
        c.setStrokeColor(colour)
        c.setLineWidth(weight)
        c.line(x, H - top, x + width, H - top)


    def panel(x, top, width, height):
        c.setFillColor(PANEL)
        c.rect(x, H - top - height, width, height, fill=1, stroke=0)


    def label(x, top, value):
        text(x, top, value.upper(), 8, 'Helvetica-Bold', MUTED)


    def header(page, section):
        c.setFillColor(BG)
        c.rect(0, 0, W, H, fill=1, stroke=0)
        text(M, 43, 'Stage-Ready', 20, 'Helvetica-Bold')
        text(W - M, 41, 'CLIENT HANDOVER', 8, 'Helvetica-Bold', MUTED, 'right')
        line(59)
        panel(M, 74, CW, 26)
        text(M + 11, 91, 'SAMPLE PREVIEW  /  Fictional client and data', 8, 'Helvetica-Bold')
        line(785)
        text(M, 804, 'ALEX MORGAN  /  ' + section.upper(), 7.5, 'Helvetica-Bold', MUTED)


    def section_title(top, number, title, subtitle):
        label(M, top, number)
        text(M, top + 32, title, 30, 'Helvetica-Bold')
        para(M, top + 46, CW, subtitle, 10, 15)


    header(1, 'Bodyweight')
    section_title(127, '01 / Your progress', 'Coaching handover',
                  'Your bodyweight milestones, recorded personal bests and the coaching strategies that worked for you.')

    line(223)
    label(M, 245, 'Client')
    text(M, 264, 'Alex Morgan', 13, 'Helvetica-Bold')
    label(M + 264, 245, 'Coach')
    text(M + 264, 264, 'Sam Taylor', 13, 'Helvetica-Bold')
    label(M, 294, 'Records covered')
    text(M, 313, '05 Jan - 18 Oct 2026', 10)
    label(M + 264, 294, 'Report prepared')
    text(M + 264, 313, '18 Oct 2026', 10)

    label(M, 355, 'Bodyweight milestones')
    gap = 10
    card_w = (CW - 2 * gap) / 3
    milestones = [
        ('PEAK BULK', '92.4', '18 May 2026', 'Highest recorded weight in the growth phase.'),
        ('BEFORE PREP', '91.6', '01 Jun 2026', 'Actual weigh-in at the start of show prep.'),
        ('SHOW DAY', '78.2', '27 Sep 2026', 'Actual weigh-in on the day of the show.'),
    ]
    for i, (name, weight, when, note) in enumerate(milestones):
        x = M + i * (card_w + gap)
        panel(x, 369, card_w, 155)
        label(x + 13, 393, name)
        text(x + 13, 433, weight, 31, 'Helvetica-Bold')
        text(x + 96, 431, 'kg', 11, 'Helvetica', MUTED)
        text(x + 13, 458, when, 9, 'Helvetica-Bold')
        para(x + 13, 473, card_w - 26, note, 8.5, 12)

    label(M, 554, 'Bodyweight trend')
    text(W - M, 554, 'Rolling 7-day average', 8, 'Helvetica', MUTED, 'right')

    # Deterministic fictional weigh-ins. The graph uses recorded observations only.
    anchors = [(date(2026, 1, 5), 83.0), (date(2026, 5, 18), 92.4),
               (date(2026, 6, 1), 91.6), (date(2026, 9, 27), 78.2),
               (date(2026, 10, 18), 80.1)]
    readings = []
    start, end = anchors[0][0], anchors[-1][0]
    for offset in range((end - start).days + 1):
        day = start + timedelta(days=offset)
        if day.weekday() in (2, 6) and day not in [a[0] for a in anchors]:
            continue
        for (d0, w0), (d1, w1) in zip(anchors, anchors[1:]):
            if d0 <= day <= d1:
                f = (day - d0).days / (d1 - d0).days
                weight = w0 + f * (w1 - w0) + .13 * sin(offset * 1.7)
                if day <= date(2026, 6, 1):
                    weight = min(weight, 92.3)
                break
        if day in dict(anchors):
            weight = dict(anchors)[day]
        readings.append((day, round(weight, 1)))
    points = []
    for day, _ in readings:
        window = [w for d, w in readings if day - timedelta(days=6) <= d <= day]
        points.append((day, sum(window) / len(window), len(window)))

    cx, cy, gw, gh = M + 30, 574, CW - 42, 113
    for val in [78, 84, 90, 96]:
        y = cy + (96 - val) / 18 * gh
        line(y, cx, gw, LINE, .4)
        text(M, y + 3, str(val), 7.5, 'Helvetica', MUTED)

    path = c.beginPath()
    coords = []
    for i, (day, avg, count) in enumerate(points):
        x = cx + (day - start).days / (end - start).days * gw
        y = H - (cy + (96 - avg) / 18 * gh)
        coords.append((x, y))
        if i == 0:
            path.moveTo(x, y)
        else:
            path.lineTo(x, y)
    c.setLineWidth(1.7)
    c.setStrokeColor(WHITE)
    c.drawPath(path)
    c.setFillColor(WHITE)
    for x, y in [coords[0], coords[-1]]:
        c.circle(x, y, 2.7, fill=1, stroke=0)
    for when, name in [(start, 'JAN'), (date(2026, 5, 18), 'MAY'), (date(2026, 9, 1), 'SEP'), (end, 'OCT')]:
        x = cx + (when - start).days / (end - start).days * gw
        text(x, 708, name, 7.5, 'Helvetica-Bold', MUTED, 'right' if when == end else 'left')
    last_day, last_avg, last_count = points[-1]
    previous = [w for d, w in readings if end - timedelta(days=13) <= d <= end - timedelta(days=7)]
    delta = last_avg - sum(previous) / len(previous)
    para(M, 727, CW,
         f'Latest average: <b>{last_avg:.1f} kg</b> from <b>{last_count} readings</b>; '
         f'<b>{delta:+.1f} kg</b> versus seven days earlier ({len(previous)} readings). '
         'Missing weigh-ins are skipped. Milestone cards show individual readings.', 8.5, 12)
    c.showPage()


    header(2, 'Personal bests')
    section_title(127, '02 / Strength record', 'Exercise personal bests',
                  'Heaviest recorded completed set for each exercise. Weight, reps and date belong to that same set.')

    panel(M, 223, CW, 48)
    para(M + 13, 236, CW - 26,
         'Only completed sets with recorded weight and reps are included. These are actual logged lifts; no estimated one-rep max is used.', 9, 13)

    rows = [
        ('Barbell back squat', 'Total barbell load', '160', '5', '12 May 2026'),
        ('Barbell bench press', 'Total barbell load', '110', '6', '19 May 2026'),
        ('Romanian deadlift', 'Total barbell load', '140', '8', '26 May 2026'),
        ('Incline dumbbell press', 'Weight per dumbbell', '40', '8', '07 Jun 2026'),
        ('Seated dumbbell shoulder press', 'Weight per dumbbell', '32', '8', '10 Jun 2026'),
        ('Chest-supported machine row', 'Machine stack reading', '85', '10', '16 Jun 2026'),
        ('Lat pulldown', 'Machine stack reading', '80', '10', '23 Jun 2026'),
        ('Seated leg curl', 'Machine stack reading', '60', '12', '30 Jun 2026'),
    ]
    label(M, 304, 'Exercise / load convention')
    label(M + 285, 304, 'Kg')
    label(M + 343, 304, 'Reps')
    text(W - M, 304, 'DATE', 8, 'Helvetica-Bold', MUTED, 'right')
    line(318)
    for index, (name, note, weight, reps, when) in enumerate(rows):
        top = 318 + index * 46
        if index % 2 == 0:
            panel(M, top, CW, 46)
        text(M + 9, top + 19, name, 9.5, 'Helvetica-Bold')
        text(M + 9, top + 35, note, 8, 'Helvetica', MUTED)
        text(M + 285, top + 24, weight, 12, 'Helvetica-Bold')
        text(M + 343, top + 24, reps, 11)
        text(W - M - 9, top + 24, when, 8.5, 'Helvetica', WHITE, 'right')
        line(top + 46, weight=.4)

    para(M, 710, CW,
         '<b>Reading these records</b><br/>Dumbbell loads are per hand; barbell loads include the bar. Machine loads are specific to the equipment used. Exercises without qualifying logged sets are marked Not recorded.', 9, 14)
    c.showPage()


    header(3, 'Coaching notes')
    section_title(127, '03 / What worked', 'Keep what worked',
                  'Coach-recorded effective strategies, with the period used and the observed response.')


    def strategy(top, index, title, period, strategy_text, outcome):
        panel(M, top, CW, 97)
        text(M + 13, top + 23, index, 10, 'Helvetica-Bold', MUTED)
        text(M + 42, top + 23, title, 12, 'Helvetica-Bold')
        text(W - M - 13, top + 22, period, 8, 'Helvetica', MUTED, 'right')
        para(M + 42, top + 36, CW - 55, strategy_text, 9, 13, WHITE)
        para(M + 42, top + 65, CW - 55, '<b>Observed:</b> ' + outcome, 8.5, 12)


    strategy(223, '01', 'Weekly weight gain', 'JAN - MAY 2026',
             'Target used: +0.5 kg/week in the growth phase, assessed using the rolling 7-day bodyweight average.',
             'Coach recorded +0.4 to +0.5 kg/week alongside improving training performance.')
    strategy(330, '02', 'Most effective training', 'FEB - JUN 2026',
             'Four-day upper/lower split; two upper and two lower sessions each week. Keep the main lifts consistent.',
             'Most consistent session completion and steady rep or load progression.')
    strategy(437, '03', 'Recovery and fatigue', 'JUN - SEP 2026',
             'A consistent sleep schedule and lower-volume weeks when performance and recovery declined together.',
             'Coach noted better recovery and training quality after lower-volume weeks.')

    line(559)
    label(M, 582, 'Coach handover notes')
    para(M, 597, CW,
         'Continue logging exercise setup as well as load: the machine, grip and seat position can change what a personal best means. Use these records as a starting point when agreeing the next plan with your coach.',
         10, 15, WHITE)

    label(M, 658, 'Nutrition strategies')
    text(M, 679, 'Not recorded', 10, 'Helvetica', MUTED)
    label(M + 264, 658, 'Recorded by')
    text(M + 264, 679, 'Sam Taylor / 18 Oct 2026', 10)

    panel(M, 710, CW, 51)
    para(M + 13, 722, CW - 26,
         'These notes describe this fictional client\'s past coaching. They are sample content for template review, not personal targets or a new coaching plan.',
         8.5, 12)
    c.showPage()


    segments = []
    note_top = 223
    notes_page = 0

    def new_notes_page():
        nonlocal note_top, notes_page
        notes_page += 1
        header(c.getPageNumber(), "Coach handover notes")
        section_title(127, "04 / Coach handover notes", "Coach handover notes",
                      "Further notes recorded by your coach." if notes_page == 1 else
                      "Continued notes from the same coaching handover.")
        note_top = 223

    def draw_note_part(note, index, content, height, continued):
        nonlocal note_top
        title, period, title_height, period_height, body_offset = metadata(note, continued)
        card_height = body_offset + height + 12
        panel(M, note_top, CW, card_height)
        text(M + 13, note_top + 23, f"{index:02d}", 10, "Helvetica-Bold", MUTED)
        title.drawOn(c, M + 42, H - note_top - 13 - title_height)
        period.drawOn(c, M + 42, H - note_top - 13 - title_height - 5 - period_height)
        body_top = note_top + body_offset
        title_top = note_top + 13
        period_top = title_top + title_height + 5
        content.drawOn(c, M + 42, H - body_top - height)
        segments.append({"note": index, "page": c.getPageNumber(), "continued": continued,
                         "top": note_top, "bottom": note_top + card_height,
                         "title_box": [M + 42, title_top, M + 42 + note_width, title_top + title_height],
                         "period_box": [M + 42, period_top, M + 42 + note_width, period_top + period_height],
                         "body_box": [M + 42, body_top, M + 42 + note_width, body_top + height]})
        note_top += card_height + 10

    if notes:
        new_notes_page()
    for index, note in enumerate(notes, 1):
        remaining = Paragraph(_markup(note["body"]), note_style)
        continued = False
        while remaining is not None:
            _, full_height = remaining.wrap(note_width, H * 1000)
            padding = metadata(note, continued)[4] + 12
            capacity = 761 - note_top - padding
            if full_height <= capacity:
                draw_note_part(note, index, remaining, full_height, continued)
                break
            # Only oversized notes split: a whole note moves to a fresh page.
            if note_top > 223 and full_height <= 761 - 223 - padding:
                c.showPage()
                new_notes_page()
                continue
            pieces = remaining.split(note_width, capacity) if capacity >= 26 else []
            if not pieces:
                if note_top == 223:
                    raise ValueError(f"Coach note {index} cannot be split into readable segments.")
                c.showPage()
                new_notes_page()
                continue
            if len(pieces) != 2:
                raise ValueError(f"Unexpected split result for coach note {index}.")
            _, segment_height = pieces[0].wrap(note_width, capacity)
            if segment_height < 26:
                raise ValueError(f"Coach note {index} split produced fewer than two body lines.")
            draw_note_part(note, index, pieces[0], segment_height, continued)
            remaining = pieces[1]
            continued = True
            c.showPage()
            new_notes_page()
    if notes:
        c.showPage()
    c.save()
    return segments


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path, help="Destination PDF file")
    parser.add_argument("--notes", type=Path, help="JSON list of up to ten title/period/body notes")
    args = parser.parse_args()
    try:
        notes = json.loads(args.notes.read_text(encoding="utf-8")) if args.notes else None
        if args.notes and notes is None:
            raise ValueError("Coach notes must be a JSON list, not null.")
        build_preview(args.output, notes)
    except (OSError, ValueError) as error:
        parser.error(str(error))


if __name__ == "__main__":
    main()
