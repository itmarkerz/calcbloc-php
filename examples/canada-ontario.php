<?php

declare(strict_types=1);

// Canada 2026: federal and Ontario income tax from the brackets on $90,000 of taxable income
// (before non-refundable credits, the Ontario surtax and the health premium).
require __DIR__ . '/../vendor/autoload.php';

use CalcBloc\Tables;

$federal = Tables::load('canada', '2026')->section('federal-income-tax');
$ontario = Tables::load('canada', '2026', 'on')->section('provincial-income-tax');

printf("Federal brackets: CAD %s\n", number_format($federal->taxOn(90000), 2));
printf("Ontario brackets: CAD %s\n", number_format($ontario->taxOn(90000), 2));
echo 'Source: ', implode(', ', array_unique([$federal->source->url, $ontario->source->url])), "\n";
