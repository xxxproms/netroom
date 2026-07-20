<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available interface locales
    |--------------------------------------------------------------------------
    |
    | Locales users may pick for the interface. The application default comes
    | from APP_LOCALE; a user may override it in their profile settings.
    |
    */

    'locales' => [
        'ru' => 'Русский',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-service registration
    |--------------------------------------------------------------------------
    |
    | Disabled by default: NetRoom documents internal infrastructure, so
    | accounts are created by an administrator rather than by visitors.
    |
    */

    'allow_registration' => env('NETROOM_ALLOW_REGISTRATION', false),

];
