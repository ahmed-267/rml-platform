<?php

namespace App\Support;

/**
 * DomPDF-friendly SVG charts for admin report exports.
 */
class ReportChartSvg
{
    /**
     * @param  list<array{label: string, value: float|int}>  $items
     */
    public static function bar(
        array $items,
        int $width = 520,
        int $height = 200,
        string $barColor = '#16a34a',
    ): string {
        $items = array_values(array_filter(
            $items,
            fn (array $item) => is_numeric($item['value'] ?? null),
        ));

        if ($items === []) {
            return self::empty($width, $height);
        }

        $padL = 36;
        $padR = 12;
        $padT = 16;
        $padB = 48;
        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;
        $max = max(array_map(fn (array $i) => (float) $i['value'], $items));
        $max = $max > 0 ? $max : 1;
        $n = count($items);
        $gap = 8;
        $barW = max(8, ($chartW - ($gap * max(0, $n - 1))) / $n);

        $parts = [self::svgOpen($width, $height)];
        $parts[] = self::axis($padL, $padT, $chartW, $chartH);

        foreach ($items as $i => $item) {
            $value = (float) $item['value'];
            $h = ($value / $max) * $chartH;
            $x = $padL + ($i * ($barW + $gap));
            $y = $padT + ($chartH - $h);
            $parts[] = sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" fill="%s" rx="2"/>',
                self::n($x),
                self::n($y),
                self::n($barW),
                self::n(max(1, $h)),
                $barColor,
            );
            $parts[] = sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="#6b7280">%s</text>',
                self::n($x + ($barW / 2)),
                self::n($padT + $chartH + 14),
                self::e(self::truncate((string) $item['label'], 10)),
            );
            $parts[] = sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="#111827">%s</text>',
                self::n($x + ($barW / 2)),
                self::n(max($padT + 10, $y - 4)),
                self::e(self::fmt($value)),
            );
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * @param  list<array{month: string, revenue: float|int, cost: float|int, margin: float|int}>  $rows
     */
    public static function multiLine(
        array $rows,
        int $width = 520,
        int $height = 220,
    ): string {
        $rows = array_values($rows);
        if ($rows === []) {
            return self::empty($width, $height);
        }

        $padL = 44;
        $padR = 12;
        $padT = 20;
        $padB = 44;
        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;

        $values = [];
        foreach ($rows as $row) {
            $values[] = (float) ($row['revenue'] ?? 0);
            $values[] = (float) ($row['cost'] ?? 0);
            $values[] = (float) ($row['margin'] ?? 0);
        }
        $max = max($values);
        $min = min(0, min($values));
        $span = ($max - $min) > 0 ? ($max - $min) : 1;
        $n = count($rows);
        $stepX = $n > 1 ? $chartW / ($n - 1) : 0;

        $series = [
            'revenue' => '#2563eb',
            'cost' => '#f59e0b',
            'margin' => '#16a34a',
        ];

        $parts = [self::svgOpen($width, $height)];
        $parts[] = self::axis($padL, $padT, $chartW, $chartH);

        foreach ($series as $key => $color) {
            $points = [];
            foreach ($rows as $i => $row) {
                $x = $padL + ($i * $stepX);
                $v = (float) ($row[$key] ?? 0);
                $y = $padT + $chartH - ((($v - $min) / $span) * $chartH);
                $points[] = self::n($x).','.self::n($y);
                $parts[] = sprintf(
                    '<circle cx="%s" cy="%s" r="2.5" fill="%s"/>',
                    self::n($x),
                    self::n($y),
                    $color,
                );
            }
            $parts[] = sprintf(
                '<polyline fill="none" stroke="%s" stroke-width="2" points="%s"/>',
                $color,
                implode(' ', $points),
            );
        }

        foreach ($rows as $i => $row) {
            $x = $padL + ($i * $stepX);
            $parts[] = sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-size="8" fill="#6b7280">%s</text>',
                self::n($x),
                self::n($padT + $chartH + 14),
                self::e(self::truncate((string) ($row['month'] ?? ''), 7)),
            );
        }

        $legendX = $padL;
        $legendY = 12;
        foreach ($series as $key => $color) {
            $parts[] = sprintf(
                '<rect x="%s" y="%s" width="8" height="8" fill="%s"/>',
                self::n($legendX),
                self::n($legendY - 7),
                $color,
            );
            $parts[] = sprintf(
                '<text x="%s" y="%s" font-size="8" fill="#374151">%s</text>',
                self::n($legendX + 12),
                self::n($legendY),
                self::e(ucfirst($key)),
            );
            $legendX += 70;
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * @param  list<array{label: string, value: float|int}>  $items
     */
    public static function donut(
        array $items,
        int $width = 280,
        int $height = 200,
    ): string {
        $items = array_values(array_filter(
            $items,
            fn (array $item) => ((float) ($item['value'] ?? 0)) > 0,
        ));

        if ($items === []) {
            return self::empty($width, $height);
        }

        $total = array_sum(array_map(fn (array $i) => (float) $i['value'], $items));
        $cx = 90;
        $cy = 100;
        $r = 62;
        $rInner = 34;
        $colors = ['#16a34a', '#2563eb', '#f59e0b', '#ef4444', '#8b5cf6', '#0891b2', '#64748b'];

        $parts = [self::svgOpen($width, $height)];
        $angle = -90.0;

        foreach ($items as $i => $item) {
            $value = (float) $item['value'];
            $sweep = ($value / $total) * 360;
            $color = $colors[$i % count($colors)];
            $parts[] = self::donutSlice($cx, $cy, $r, $rInner, $angle, $angle + $sweep, $color);
            $angle += $sweep;
        }

        $legendX = 170;
        $legendY = 36;
        foreach ($items as $i => $item) {
            $color = $colors[$i % count($colors)];
            $parts[] = sprintf(
                '<rect x="%s" y="%s" width="8" height="8" fill="%s"/>',
                self::n($legendX),
                self::n($legendY - 7),
                $color,
            );
            $parts[] = sprintf(
                '<text x="%s" y="%s" font-size="8" fill="#374151">%s (%s)</text>',
                self::n($legendX + 12),
                self::n($legendY),
                self::e(self::truncate((string) $item['label'], 16)),
                self::e(self::fmt((float) $item['value'])),
            );
            $legendY += 16;
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * @param  array<string, int|float>  $assoc
     * @return list<array{label: string, value: float|int}>
     */
    public static function fromAssoc(array $assoc, ?callable $labelFn = null): array
    {
        $items = [];
        foreach ($assoc as $key => $value) {
            $items[] = [
                'label' => $labelFn ? (string) $labelFn((string) $key) : (string) $key,
                'value' => $value,
            ];
        }

        return $items;
    }

    private static function donutSlice(
        float $cx,
        float $cy,
        float $r,
        float $rInner,
        float $startDeg,
        float $endDeg,
        string $color,
    ): string {
        if ($endDeg - $startDeg >= 359.9) {
            return sprintf(
                '<circle cx="%s" cy="%s" r="%s" fill="%s"/><circle cx="%s" cy="%s" r="%s" fill="#ffffff"/>',
                self::n($cx),
                self::n($cy),
                self::n($r),
                $color,
                self::n($cx),
                self::n($cy),
                self::n($rInner),
            );
        }

        $p1 = self::polar($cx, $cy, $r, $startDeg);
        $p2 = self::polar($cx, $cy, $r, $endDeg);
        $p3 = self::polar($cx, $cy, $rInner, $endDeg);
        $p4 = self::polar($cx, $cy, $rInner, $startDeg);
        $large = ($endDeg - $startDeg) > 180 ? 1 : 0;

        return sprintf(
            '<path d="M %s %s A %s %s 0 %d 1 %s %s L %s %s A %s %s 0 %d 0 %s %s Z" fill="%s"/>',
            self::n($p1[0]),
            self::n($p1[1]),
            self::n($r),
            self::n($r),
            $large,
            self::n($p2[0]),
            self::n($p2[1]),
            self::n($p3[0]),
            self::n($p3[1]),
            self::n($rInner),
            self::n($rInner),
            $large,
            self::n($p4[0]),
            self::n($p4[1]),
            $color,
        );
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function polar(float $cx, float $cy, float $r, float $deg): array
    {
        $rad = deg2rad($deg);

        return [$cx + ($r * cos($rad)), $cy + ($r * sin($rad))];
    }

    private static function axis(float $padL, float $padT, float $chartW, float $chartH): string
    {
        return sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#e5e7eb" stroke-width="1"/><line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#e5e7eb" stroke-width="1"/>',
            self::n($padL),
            self::n($padT),
            self::n($padL),
            self::n($padT + $chartH),
            self::n($padL),
            self::n($padT + $chartH),
            self::n($padL + $chartW),
            self::n($padT + $chartH),
        );
    }

    private static function empty(int $width, int $height): string
    {
        return self::svgOpen($width, $height)
            .'<text x="'.($width / 2).'" y="'.($height / 2).'" text-anchor="middle" font-size="10" fill="#9ca3af">—</text>'
            .'</svg>';
    }

    private static function svgOpen(int $width, int $height): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
            $width,
            $height,
            $width,
            $height,
        );
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function fmt(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value);
        }

        return number_format($value, 1, '.', '');
    }

    private static function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $max - 1)).'…';
    }
}
