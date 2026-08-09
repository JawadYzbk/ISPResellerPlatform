<?php

namespace App\Contracts;

interface TenantAware
{
    public function tenantId(): int;
}
