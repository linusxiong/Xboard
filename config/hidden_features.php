<?php

$exposedUserCountFix = env('ENABLE_EXPOSED_USER_COUNT_FIX', true);

return [
    'enable_exposed_user_count_fix' => $exposedUserCountFix === '3f06f182'
        || filter_var($exposedUserCountFix, FILTER_VALIDATE_BOOLEAN)
];
