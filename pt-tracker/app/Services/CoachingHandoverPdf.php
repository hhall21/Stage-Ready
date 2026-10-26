<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

/** @phpstan-import-type HandoverSnapshot from CoachingHandoverBuilder */
class CoachingHandoverPdf
{
    private const LEFT = 44.0;

    private const WIDTH = 507.28;

    private const BOTTOM = 774.0;

    private const WHITE = [0.98, 0.98, 0.98];

    private const LINE = [0.21, 0.22, 0.24];

    private CPDF $canvas;

    private FontMetrics $fonts;

    private string $normal;

    private string $bold;

    private float $y;

    private string $section;

    /** @var HandoverSnapshot */
    private array $snapshot;

    /** @param HandoverSnapshot $snapshot */
    public function render(array $snapshot): string
    {
        $this->snapshot = $snapshot;
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
        $this->canvas = new CPDF('a4', 'portrait', new Dompdf($options));
        $this->fonts = new FontMetrics($this->canvas, $options);
        $this->normal = (string) $this->fonts->getFont('DejaVu Sans');
        $this->bold = (string) $this->fonts->getFont('DejaVu Sans', 'bold');
        $this->canvas->add_info('Title', 'Stage-Ready | Coaching handover');
        $this->canvas->add_info('Author', 'Stage-Ready');

        $this->page('Bodyweight milestones', true);
        $weights = $snapshot['weights'];
        $first = $weights[0] ?? null;
        $last = $weights === [] ? null : $weights[count($weights) - 1];
        $this->pair('First recorded weight', $this->number($first['kg'] ?? null, ' kg'), 'Latest recorded weight', $this->number($last['kg'] ?? null, ' kg'));
        $this->pair('Change across recorded weights', $this->number($first && $last ? round($last['kg'] - $first['kg'], 2) : null, ' kg'), 'Recorded weigh-ins', (string) count($weights));
        $this->paragraph('Only recorded weigh-ins within this coaching period are included. Missing readings remain missing.', 9);
        $this->chart($snapshot['weight_trend']);
        $latest = $snapshot['weight_trend'][count($snapshot['weight_trend']) - 1] ?? null;
        $this->pair('Final rolling seven-day average', $this->number($latest['average_kg'] ?? null, ' kg'), 'Readings in final seven days', (string) ($latest['readings'] ?? 0));
        $this->paragraph('Change from the seven-day average seven days earlier: '.$this->number($latest['change_from_7_days_ago_kg'] ?? null, ' kg').'.', 10);
        if ($first && $last) {
            $this->paragraph('First reading: '.$this->date($first['date']).'. Latest reading: '.$this->date($last['date']).'.', 9);
        }

        $this->page('Exercise personal bests');
        $this->pair('Completed sessions', (string) $snapshot['completed_sessions'], 'Completed working sets', (string) $snapshot['working_sets']);
        $this->paragraph('Working sets count completed sets with positive reps, including sets without a recorded load.', 9);
        $this->paragraph('Each best uses the highest recorded load, then the most reps at that load. The date and reps belong to the same completed set. Unloaded exercises use the highest reps.', 9);
        if ($snapshot['personal_bests'] === []) {
            $this->paragraph('No completed sets with positive reps were recorded during this coaching period.', 11);
        }
        foreach ($snapshot['personal_bests'] as $best) {
            $result = ($best['weight_kg'] === null ? 'Load not recorded' : $this->number($best['weight_kg'], ' kg')).' / '.$best['reps'].' reps / '.$this->date($best['date']);
            $this->block($best['exercise'], '', $result);
        }

        $this->page('Coach handover');
        $this->block('What worked well', '', $snapshot['feedback'] ?? 'No final feedback recorded.');
        $this->block('Next steps', '', $snapshot['next_steps'] ?? 'No next steps recorded.');
        $this->paragraph('Your existing plans, written reviews and logged history remain available in your Stage-Ready account.', 10);
        if ($snapshot['notes'] !== []) {
            $this->page('Additional coach notes');
            foreach ($snapshot['notes'] as $index => $note) {
                $this->block(sprintf('%02d. %s', $index + 1, $note['title']), $note['period'], $note['body']);
            }
        }

        $font = $this->normal;
        $this->canvas->page_script(function (int $page, int $total, CPDF $canvas) use ($font): void {
            $canvas->line(self::LEFT, 794, self::LEFT + self::WIDTH, 794, self::LINE, 0.6);
            $canvas->text(self::LEFT, 805, 'STAGE-READY / PRIVATE COACHING HANDOVER', $font, 7, self::WHITE);
            $canvas->text(505, 805, sprintf('%02d / %02d', $page, $total), $font, 8, self::WHITE);
        });

        return $this->canvas->output();
    }

    private function page(string $section, bool $first = false): void
    {
        if (! $first) {
            $this->canvas->new_page();
        }
        $this->section = preg_replace('/ \(continued\)$/', '', $section) ?? $section;
        $this->canvas->filled_rectangle(0, 0, 595.28, 841.89, [0.031, 0.035, 0.043]);
        $this->text(self::LEFT, 34, 'STAGE-READY', 10, true);
        $this->canvas->line(self::LEFT, 59, self::LEFT + self::WIDTH, 59, self::LINE, 0.6);
        $this->y = 80;
        $this->paragraph($section, 25, true, 32);
        $this->paragraph('Client: '.$this->snapshot['client_name'], 10);
        $this->paragraph('Coach: '.$this->snapshot['coach_name'].' / '.$this->snapshot['discipline'], 10);
        $this->paragraph($this->date($this->snapshot['start_date']).' - '.$this->date($this->snapshot['end_date']), 9);
        $this->y += 12;
    }

    private function text(float $x, float $y, string $text, float $size = 10, bool $bold = false): void
    {
        $this->canvas->text($x, $y, $text, $bold ? $this->bold : $this->normal, $size, self::WHITE);
    }

    private function space(float $height): void
    {
        if ($this->y + $height > self::BOTTOM) {
            $this->page($this->section.' (continued)');
        }
    }

    private function paragraph(string $text, float $size = 10, bool $bold = false, float $leading = 15): void
    {
        foreach ($this->wrap($text, self::WIDTH, $size, $bold) as $line) {
            $this->space($leading);
            $this->text(self::LEFT, $this->y, $line, $size, $bold);
            $this->y += $leading;
        }
        $this->y += 8;
    }

    private function pair(string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): void
    {
        $this->space(68);
        foreach ([[$leftLabel, $leftValue, self::LEFT], [$rightLabel, $rightValue, self::LEFT + 264]] as [$label, $value, $x]) {
            $this->text($x, $this->y, $label, 8);
            $this->text($x, $this->y + 18, $value, 16, true);
        }
        $this->y += 68;
    }

    private function block(string $title, string $period, string $body): void
    {
        $heading = $this->wrap($title, self::WIDTH, 13, true);
        $periodLines = $period === '' ? [] : $this->wrap($period, self::WIDTH, 9);
        $lines = $this->wrap($body, self::WIDTH, 10);
        $headerHeight = count($heading) * 19 + count($periodLines) * 14 + 14;
        $height = $headerHeight + count($lines) * 15 + 20;
        // Keep a short note together; split oversized notes without shrinking or omitting text.
        $freshCapacity = self::BOTTOM - 240;
        $this->space($height <= $freshCapacity ? $height : $headerHeight + 30);
        $continued = false;
        do {
            $this->canvas->line(self::LEFT, $this->y, self::LEFT + self::WIDTH, $this->y, self::LINE, 0.6);
            $this->y += 12;
            foreach ($this->wrap($title.($continued ? ' (continued)' : ''), self::WIDTH, 13, true) as $line) {
                $this->text(self::LEFT, $this->y, $line, 13, true);
                $this->y += 19;
            }
            foreach ($periodLines as $line) {
                $this->text(self::LEFT, $this->y, $line, 9);
                $this->y += 14;
            }
            $this->y += 8;
            $count = max(1, (int) floor((self::BOTTOM - $this->y) / 15));
            foreach (array_splice($lines, 0, $count) as $line) {
                $this->text(self::LEFT, $this->y, $line, 10);
                $this->y += 15;
            }
            if ($lines !== []) {
                $this->page($this->section.' (continued)');
                $continued = true;
            }
        } while ($lines !== []);
        $this->y += 20;
    }

    /** @param list<array{date: string, average_kg: float|null, readings: int, change_from_7_days_ago_kg: float|null}> $points */
    private function chart(array $points): void
    {
        $this->space(218);
        $top = $this->y;
        $this->text(self::LEFT, $top, 'Rolling seven-day average / final 56 days', 10, true);
        $values = array_values(array_filter(array_column($points, 'average_kg'), fn ($value) => $value !== null));
        if ($values === []) {
            $this->text(self::LEFT, $top + 55, 'No recorded weigh-ins in this period.', 10);
        } else {
            $min = min($values) - 0.5;
            $max = max($values) + 0.5;
            $left = self::LEFT + 45;
            $width = self::WIDTH - 45;
            $this->canvas->line($left, $top + 32, $left + $width, $top + 32, self::LINE, 0.6);
            $this->canvas->line($left, $top + 162, $left + $width, $top + 162, self::LINE, 0.6);
            $this->text(self::LEFT, $top + 26, number_format($max, 1).' kg', 8);
            $this->text(self::LEFT, $top + 154, number_format($min, 1).' kg', 8);
            $previous = null;
            foreach ($points as $index => $point) {
                if ($point['average_kg'] === null) {
                    $previous = null;

                    continue;
                }
                $x = $left + $index / max(1, count($points) - 1) * $width;
                $y = $top + 162 - ($point['average_kg'] - $min) / ($max - $min) * 130;
                if ($previous !== null) {
                    $this->canvas->line($previous[0], $previous[1], $x, $y, self::WHITE, 1.5);
                }
                $this->canvas->filled_rectangle($x - 1, $y - 1, 2, 2, self::WHITE);
                $previous = [$x, $y];
            }
            $this->text($left, $top + 175, $this->date($points[0]['date']), 8);
            $this->text(self::LEFT + self::WIDTH - 65, $top + 175, $this->date($points[count($points) - 1]['date']), 8);
        }
        $this->y += 210;
    }

    /** @return list<string> */
    private function wrap(string $text, float $width, float $size, bool $bold = false): array
    {
        $font = $bold ? $this->bold : $this->normal;
        $lines = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $paragraph) {
            $line = '';
            foreach (preg_split('/[\t ]+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($this->fonts->getTextWidth($candidate, $font, $size) <= $width) {
                    $line = $candidate;

                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                foreach (mb_str_split($word) as $character) {
                    if ($this->fonts->getTextWidth($line.$character, $font, $size) > $width && $line !== '') {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('d M Y');
    }

    private function number(?float $value, string $suffix = ''): string
    {
        return $value === null ? 'Not recorded' : number_format($value, 2).$suffix;
    }
}
