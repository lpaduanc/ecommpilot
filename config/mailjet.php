<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailjet API Credentials
    |--------------------------------------------------------------------------
    |
    | Here are the API credentials for Mailjet. You can find them in your
    | Mailjet account dashboard at: https://app.mailjet.com/account/api_keys
    |
    */

    'apikey' => env('MAILJET_APIKEY'),
    'secret' => env('MAILJET_APISECRET'),

    /*
    |--------------------------------------------------------------------------
    | Mailjet API Version
    |--------------------------------------------------------------------------
    |
    | The API version to use. Default is v3.1 which supports the Send API.
    |
    */

    'version' => 'v3.1',

    /*
    |--------------------------------------------------------------------------
    | Mailjet Sender Defaults
    |--------------------------------------------------------------------------
    |
    | Default sender information for transactional emails.
    |
    */

    'from' => [
        'email' => env('MAIL_FROM_ADDRESS', 'no-reply-reset-password@ecommpilot.com.br'),
        'name' => env('MAIL_FROM_NAME', 'EcommPilot'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Call Options
    |--------------------------------------------------------------------------
    |
    | Additional options for the Mailjet API client.
    |
    */

    'options' => [
        'secured' => true,
        'call' => true,
    ],

];
