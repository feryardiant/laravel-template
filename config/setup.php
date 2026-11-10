<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Initial User Email
    |--------------------------------------------------------------------------
    |
    | The email address seeded on a fresh non-local install. While this user
    | exists and no root tenant has been created, the app redirects to /setup.
    |
    */

    'initial_email' => env('SETUP_INITIAL_EMAIL', 'owner@example.com'),

];
