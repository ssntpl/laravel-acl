<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | The number of seconds that ACL cache entries should be stored.
    | Default is 1 day (86400 seconds).
    |
    */
    'cache_ttl' => env('ACL_CACHE_TTL', 86400),
];
