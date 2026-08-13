<?php

namespace App\Data;

use App\Models\PublicBillingLink;

final readonly class CreatedPublicBillingLink
{
    public function __construct(public PublicBillingLink $link, public string $token) {}
}
