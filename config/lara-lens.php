<?php

/*
 * LaraLens Configuration.
 */
return [
    'prefix' => env('LARALENS_PREFIX', 'laralens'), // URL prefix (default=laralens)
    'middleware' => explode(';', env('LARALENS_MIDDLEWARE', 'web;auth.basic')), // middleware (default=web) more separate with ;
    'web-enabled' => env('LARALENS_WEB_ENABLED', 'off') // Activate web view (default=off)
];
