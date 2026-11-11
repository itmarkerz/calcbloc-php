<?php

declare(strict_types=1);

// Germany's employee social security rates for 2026 (pension, unemployment, health and care insurance).
require __DIR__ . '/../vendor/autoload.php';

use CalcBloc\Tables;

$table = Tables::load('germany');

foreach ($table->sectionsInGroup('employee') as $section) {
    echo $section->title, "\n";
    foreach ($section->rows as $row) {
        $value = match ($row['unit']) {
            'rate' => rtrim(rtrim(number_format($row['value'] * 100, 2), '0'), '.') . '%',
            'money' => number_format($row['value'], 2) . ' ' . $table->currency,
            default => (string) $row['value'],
        };
        printf("  %s: %s\n", $row['label'], $value);
    }
}
