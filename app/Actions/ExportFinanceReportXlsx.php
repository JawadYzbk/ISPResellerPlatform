<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Spreadsheets\XlsxWriter;
use Carbon\CarbonImmutable;

final readonly class ExportFinanceReportXlsx implements Action
{
    public function __construct(private ExportFinanceReportCsv $csv, private XlsxWriter $writer) {}

    public function handle(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $this->writer->write($this->writer->fromCsv($this->csv->handle($from, $to)));
    }
}
