<?php

namespace App\Support;

final class CustomerCodeGenerator
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    public function next(): string
    {
        return $this->numbers->next('customer', 'CUS');
    }
}
