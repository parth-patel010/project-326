<?php

declare(strict_types=1);

/**
 * Dining area helpers for POS floor map (EatnSay-style sections).
 */

/** @return list<array{key:string,label:string,prefix:string,has_col:string,count_col:string,symbol:string}> */
function ha_dining_area_defs(): array
{
    return [
        [
            'key' => 'table',
            'label' => 'Tables',
            'prefix' => '',
            'has_col' => '', // always on
            'count_col' => 'dining_total_tables',
            'symbol' => 'table_restaurant',
        ],
        [
            'key' => 'tent',
            'label' => 'Outdoor tents',
            'prefix' => 'T-',
            'has_col' => 'dining_has_tents',
            'count_col' => 'dining_total_tents',
            'symbol' => 'camping',
        ],
        [
            'key' => 'garden',
            'label' => 'Garden tables',
            'prefix' => 'G-',
            'has_col' => 'dining_has_garden_tables',
            'count_col' => 'dining_total_garden_tables',
            'symbol' => 'yard',
        ],
        [
            'key' => 'bar',
            'label' => 'Bar tables',
            'prefix' => 'B-',
            'has_col' => 'dining_has_bar_tables',
            'count_col' => 'dining_total_bar_tables',
            'symbol' => 'local_bar',
        ],
        [
            'key' => 'room',
            'label' => 'Private rooms',
            'prefix' => 'R-',
            'has_col' => 'dining_has_rooms',
            'count_col' => 'dining_total_rooms',
            'symbol' => 'meeting_room',
        ],
        [
            'key' => 'ac',
            'label' => 'AC tables',
            'prefix' => 'AC-',
            'has_col' => 'dining_has_ac_tables',
            'count_col' => 'dining_total_ac_tables',
            'symbol' => 'ac_unit',
        ],
    ];
}

function ha_dining_loc_key(string $area, int $number): string
{
    $area = strtolower(trim($area));
    if ($area === '' || $area === 'table' || $area === 'dine_in') {
        $area = 'table';
    }
    $number = max(1, $number);
    if ($area === 'table') {
        // Prefer plain number for backward compatibility with existing bills
        return (string) $number;
    }
    return $area . ':' . $number;
}

/**
 * @return array{area:string,number:int}
 */
function ha_dining_parse_loc(?string $loc): array
{
    $loc = trim((string) $loc);
    if ($loc === '') {
        return ['area' => 'table', 'number' => 0];
    }
    if (preg_match('/^([a-z]+):(\d+)$/i', $loc, $m)) {
        return ['area' => strtolower($m[1]), 'number' => (int) $m[2]];
    }
    if (ctype_digit($loc)) {
        return ['area' => 'table', 'number' => (int) $loc];
    }
    return ['area' => 'table', 'number' => 0];
}

function ha_dining_display_label(string $area, int $number, array $hotel = []): string
{
    $area = strtolower($area);
    if ($area === 'room') {
        $labels = [];
        if (!empty($hotel['dining_room_labels'])) {
            $decoded = is_array($hotel['dining_room_labels'])
                ? $hotel['dining_room_labels']
                : json_decode((string) $hotel['dining_room_labels'], true);
            if (is_array($decoded)) {
                $labels = $decoded;
            }
        }
        $custom = trim((string) ($labels[(string) $number] ?? $labels[$number] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        return 'R-' . $number;
    }
    if ($area === 'table') {
        return (string) $number;
    }
    foreach (ha_dining_area_defs() as $def) {
        if ($def['key'] === $area) {
            return ($def['prefix'] !== '' ? $def['prefix'] : '') . (string) $number;
        }
    }
    return (string) $number;
}

/**
 * Enabled floor sections for a hotel row.
 *
 * @return list<array{key:string,label:string,prefix:string,count:int,symbol:string}>
 */
function ha_dining_sections(array $hotel, ?PDO $pdo = null): array
{
    $sections = [];
    foreach (ha_dining_area_defs() as $def) {
        $countCol = $def['count_col'];
        $hasCol = $def['has_col'];
        $enabled = $hasCol === '';
        if ($hasCol !== '') {
            if ($pdo && function_exists('ha_col_exists') && !ha_col_exists('hotels', $hasCol, $pdo)) {
                continue;
            }
            $enabled = !empty($hotel[$hasCol]);
        }
        if (!$enabled) {
            continue;
        }
        if ($pdo && function_exists('ha_col_exists') && !ha_col_exists('hotels', $countCol, $pdo)) {
            if ($def['key'] === 'table') {
                $count = 12;
            } else {
                continue;
            }
        } else {
            $count = max(0, (int) ($hotel[$countCol] ?? ($def['key'] === 'table' ? 12 : 0)));
        }
        if ($def['key'] === 'table') {
            $count = max(1, $count);
        }
        if ($count < 1) {
            continue;
        }
        $sections[] = [
            'key' => $def['key'],
            'label' => $def['label'],
            'prefix' => $def['prefix'],
            'count' => $count,
            'symbol' => $def['symbol'],
        ];
    }
    return $sections;
}
