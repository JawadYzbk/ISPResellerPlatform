<?php

namespace App\Support;

use Sentry\Event;

final class SentryPrivacyScrubber
{
    public static function scrub(Event $event): Event
    {
        $event->setUser(null);
        $request = $event->getRequest();
        unset($request['data'], $request['cookies'], $request['headers']);
        $event->setRequest($request);

        return $event;
    }
}
