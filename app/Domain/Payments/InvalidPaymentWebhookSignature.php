<?php

namespace App\Domain\Payments;

use RuntimeException;

final class InvalidPaymentWebhookSignature extends RuntimeException {}
