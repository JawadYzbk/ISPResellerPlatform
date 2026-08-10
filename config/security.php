<?php

return [
    // Keep the flow available, but make mandatory web enrollment an explicit deployment choice.
    'enforce_web_two_factor' => env('SECURITY_ENFORCE_TWO_FACTOR', false),
];
