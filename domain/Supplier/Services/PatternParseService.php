<?php

declare(strict_types=1);

namespace Domain\Supplier\Services;

use Domain\Catalogue\Enums\WineColour;

/**
 * Executes a machine "rules" recipe over coordinate-extracted rows — the cheap
 * deterministic half of the hybrid parser. The rules are written ONCE by the
 * LLM studying the document (zones by cell start-x, an optional row regex,
 * carry-down fields, section headers, code maps); running them costs nothing,
 * so re-uploads of a studied supplier's list parse for free.
 *
 * Rules shape (all keys optional unless noted):
 *   zones:          [{field, x_min, x_max}]  field ∈ ClaudeClient::FIELDS ∪ {ignore}
 *   row_regex:      PCRE body (no delimiters) with named groups ∈ FIELDS,
 *                   matched against the row's non-ignored text; captures
 *                   override zone values
 *   require:        fields that must be non-empty for a wine row (default [wine_name])
 *   carry:          fields inheriting the last non-empty value from rows above
 *   section_regex:  PCRE body with a named group `value`; a matching row is a
 *                   section header, not a wine
 *   section_field:  where the current section value lands (e.g. region)
 *   colour_map:     {code => WineColour value} for style shorthands (r, w, sp…)
 *   format_unit:    appended to a bare numeric format_ml (e.g. "cl" → "75cl")
 */
class PatternParseService
{
    /**
     * @param  array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>  $rows
     * @param  array<string, mixed>  $rules
     * @param  array{section?: string, carry?: array<string, string>}  $state  carry/section context threaded across page batches
     * @return array{rows: array<int, array<string, string>>, matched: int, residue: int, state: array{section: string, carry: array<string, string>}}
     */
    public function parse(array $rows, array $rules, array $state = []): array
    {
        $rules = $this->sanitise($rules);
        $require = $rules['require'] !== [] ? $rules['require'] : ['wine_name'];

        $wines = [];
        $residue = 0;
        // Context survives batch boundaries — a producer/section printed on
        // page 50 still applies to page 51's rows.
        $section = (string) ($state['section'] ?? '');
        $carryValues = (array) ($state['carry'] ?? []);

        foreach ($rows as $row) {
            $fields = [];
            $textParts = [];

            foreach ($row['cells'] as $cell) {
                $zone = $this->zoneFor($cell['x0'], $rules['zones']);

                if ($zone === 'ignore') {
                    continue;
                }

                $textParts[] = $cell['text'];

                if ($zone !== null) {
                    $fields[$zone] = trim(($fields[$zone] ?? '').' '.$cell['text']);
                }
            }

            $text = trim(implode(' ', $textParts));

            if ($text === '') {
                continue;
            }

            // Section headers update context and are never wines.
            if ($rules['section_regex'] !== '' && preg_match(self::wrap($rules['section_regex']), $text, $m)) {
                $section = trim($m['value'] ?? $text);

                continue;
            }

            // Regex captures override zone values.
            if ($rules['row_regex'] !== '' && preg_match(self::wrap($rules['row_regex']), $text, $m)) {
                foreach (ClaudeClient::FIELDS as $field) {
                    if (isset($m[$field]) && trim($m[$field]) !== '') {
                        $fields[$field] = trim($m[$field]);
                    }
                }
            }

            $fields = $this->transform($fields, $rules);

            if ($rules['section_field'] !== '' && $section !== '' && trim($fields[$rules['section_field']] ?? '') === '') {
                $fields[$rules['section_field']] = $section;
            }

            // Carry-down: remember fresh values, fill gaps from above. A row
            // that is only a producer/region cell still feeds the memory.
            foreach ($rules['carry'] as $field) {
                $value = trim($fields[$field] ?? '');

                if ($value !== '') {
                    $carryValues[$field] = $value;
                } elseif (isset($carryValues[$field])) {
                    $fields[$field] = $carryValues[$field];
                }
            }

            $isWine = true;
            foreach ($require as $field) {
                if (trim($fields[$field] ?? '') === '') {
                    $isWine = false;
                    break;
                }
            }

            if ($isWine) {
                $fields['_page'] = (string) $row['page'];
                $wines[] = $fields;
            } elseif (count($row['cells']) >= 2) {
                $residue++;
            }
        }

        return [
            'rows' => $wines,
            'matched' => count($wines),
            'residue' => $residue,
            'state' => ['section' => $section, 'carry' => $carryValues],
        ];
    }

    /**
     * Render coordinate rows for the LLM's one-off study: each cell prefixed
     * with its start-x so zone boundaries can be proposed.
     *
     * @param  array<int, array{page: int, y: float, cells: array<int, array{text: string, x0: float, x1: float}>}>  $rows
     */
    public function renderForStudy(array $rows, int $limit = 120): string
    {
        $lines = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $cells = array_map(
                fn (array $cell) => '['.round($cell['x0']).']'.$cell['text'],
                $row['cells'],
            );
            $lines[] = 'p'.$row['page'].': '.implode(' || ', $cells);
        }

        return implode("\n", $lines);
    }

    /**
     * Validate/normalise LLM-written rules so nothing unsafe or broken executes.
     *
     * @param  array<string, mixed>  $rules
     * @return array{zones: array<int, array{field: string, x_min: float, x_max: float}>, row_regex: string, require: array<int, string>, carry: array<int, string>, section_regex: string, section_field: string, colour_map: array<string, string>, format_unit: string}
     */
    public function sanitise(array $rules): array
    {
        $fields = ClaudeClient::FIELDS;

        $zones = [];
        foreach ((array) ($rules['zones'] ?? []) as $zone) {
            $field = (string) ($zone['field'] ?? '');
            if (($field === 'ignore' || in_array($field, $fields, true)) && is_numeric($zone['x_min'] ?? null) && is_numeric($zone['x_max'] ?? null)) {
                $zones[] = ['field' => $field, 'x_min' => (float) $zone['x_min'], 'x_max' => (float) $zone['x_max']];
            }
        }

        $colours = array_column(WineColour::cases(), 'value');
        $colourMap = [];
        foreach ((array) ($rules['colour_map'] ?? []) as $code => $colour) {
            // Accept both {code: colour} and the LLM's [{code, colour}] pair shape.
            if (is_array($colour)) {
                $code = (string) ($colour['code'] ?? '');
                $colour = (string) ($colour['colour'] ?? '');
            }
            if ($code !== '' && in_array($colour, $colours, true)) {
                $colourMap[mb_strtolower(trim((string) $code))] = $colour;
            }
        }

        return [
            'zones' => $zones,
            'row_regex' => $this->validRegex((string) ($rules['row_regex'] ?? '')),
            'require' => array_values(array_intersect((array) ($rules['require'] ?? []), $fields)),
            'carry' => array_values(array_intersect((array) ($rules['carry'] ?? []), $fields)),
            'section_regex' => $this->validRegex((string) ($rules['section_regex'] ?? '')),
            'section_field' => in_array($rules['section_field'] ?? '', $fields, true) ? (string) $rules['section_field'] : '',
            'colour_map' => $colourMap,
            'format_unit' => in_array($rules['format_unit'] ?? '', ['cl', 'ml', 'l'], true) ? (string) $rules['format_unit'] : '',
        ];
    }

    /**
     * Zones are matched on the ROUNDED start-x — renderForStudy shows the model
     * rounded coordinates, so boundaries must be compared the same way (a cell
     * at x0=463.66 displayed as [464] belongs to a zone starting at 464).
     *
     * @param  array<int, array{field: string, x_min: float, x_max: float}>  $zones
     */
    private function zoneFor(float $x0, array $zones): ?string
    {
        $x = round($x0);

        foreach ($zones as $zone) {
            if ($x >= $zone['x_min'] && $x < $zone['x_max']) {
                return $zone['field'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array{colour_map: array<string, string>, format_unit: string}  $rules
     * @return array<string, string>
     */
    private function transform(array $fields, array $rules): array
    {
        if (isset($fields['colour'])) {
            $code = mb_strtolower(trim($fields['colour']));
            $fields['colour'] = $rules['colour_map'][$code] ?? $fields['colour'];
        }

        // "75/6" is the trade idiom for size/case — split it automatically.
        if (isset($fields['format_ml']) && str_contains($fields['format_ml'], '/')) {
            [$size, $case] = array_pad(explode('/', $fields['format_ml'], 2), 2, '');
            $fields['format_ml'] = trim($size);
            if (trim($fields['case_size'] ?? '') === '' && trim($case) !== '') {
                $fields['case_size'] = trim($case);
            }
        }

        if ($rules['format_unit'] !== '' && isset($fields['format_ml']) && preg_match('/^\d{1,4}(\.\d+)?$/', trim($fields['format_ml']))) {
            $fields['format_ml'] = trim($fields['format_ml']).$rules['format_unit'];
        }

        return $fields;
    }

    private function validRegex(string $pattern): string
    {
        if ($pattern === '' || strlen($pattern) > 500) {
            return '';
        }

        return @preg_match(self::wrap($pattern), '') === false ? '' : $pattern;
    }

    /**
     * Delimit an LLM-written pattern body safely (rules routinely contain `/`,
     * e.g. the 75/6 size-case idiom, so `~` is the delimiter).
     */
    private static function wrap(string $pattern): string
    {
        return '~'.str_replace('~', '\\~', $pattern).'~u';
    }
}
