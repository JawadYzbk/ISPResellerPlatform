<?php

use App\Domain\Spreadsheets\XlsxReader;
use App\Domain\Spreadsheets\XlsxWriter;

it('round-trips tabular data through an XLSX first worksheet', function (): void {
    $rows = [['metric', 'value'], ['invoiced', 3500], ['note', 'A & B']];
    $xlsx = app(XlsxWriter::class)->write($rows);

    expect(app(XlsxReader::class)->rows($xlsx))->toBe([['metric', 'value'], ['invoiced', '3500'], ['note', 'A & B']]);
});
