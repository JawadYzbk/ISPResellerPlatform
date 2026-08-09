<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\LogRecord;

final class RedactSensitiveContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $context = SensitiveDataRedactor::redact($record->context);
            $extra = SensitiveDataRedactor::redact($record->extra);

            return $record->with(
                context: is_array($context) ? $context : [],
                extra: is_array($extra) ? $extra : [],
            );
        });
    }
}
