<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Spreadsheets\XlsxReader;

final readonly class NormalizeTabularImport implements Action
{
    public function __construct(private XlsxReader $reader) {}

    public function handle(string $contents, string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xlsx'
            ? $this->reader->toCsv($contents)
            : $contents;
    }
}
