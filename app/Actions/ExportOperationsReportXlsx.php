<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Spreadsheets\XlsxWriter;

final readonly class ExportOperationsReportXlsx implements Action
{
    public function __construct(private ExportOperationsReportCsv $csv, private XlsxWriter $writer) {}

    public function handle(): string
    {
        return $this->writer->write($this->writer->fromCsv($this->csv->handle()));
    }
}
