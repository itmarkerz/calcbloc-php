<?php

declare(strict_types=1);

// US federal income tax brackets 2026 (single filer) from the bundled tables, and the tax on $85,000 of taxable income.
require __DIR__ . '/../vendor/autoload.php';

use CalcBloc\Tables;

$table = Tables::load('us', '2026');
$single = $table->section('federal-income-tax-single');

foreach ($single->rows as $band) {
    printf(
        "%-22s %5.1f%%\n",
        '$' . number_format($band['from']) . ($band['to'] === null ? ' and over' : ' to $' . number_format($band['to'])),
        $band['rate'] * 100,
    );
}
printf("Tax on $85,000 taxable income: $%s\n", number_format(Tables::taxFromBands($single, 85000), 2));
printf("Source: %s (checked %s)\n", $single->source->url, $single->source->retrievedAt);
