<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

final class RedactSensitiveContext
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $monolog->pushProcessor(function (LogRecord $record): LogRecord {
            $context = SensitiveDataRedactor::redact($record->context);
            $extra = SensitiveDataRedactor::redact($record->extra);

            return $record->with(
                context: is_array($context) ? $context : [],
                extra: is_array($extra) ? $extra : [],
            );
        });
    }
}
