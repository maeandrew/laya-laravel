<?php

return [

    'driver' => 'laya',

    /*
    |--------------------------------------------------------------------------
    | Laya Server
    |--------------------------------------------------------------------------
    |
    | Laya runs as a model you host yourself, so there is no vendor endpoint to
    | default to. Point this at a `laya-serve` instance. The key is only needed
    | when that instance was started with LAYA_API_KEY set.
    |
    */

    'url' => env('LAYA_URL', 'http://localhost:8000/v1'),

    'key' => env('LAYA_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Checkpoint
    |--------------------------------------------------------------------------
    |
    | "auto" lets Laya's Router read the state's script and language and choose
    | between the checkpoints itself, which is the recommended setting. Name one
    | of english, multilingual or typed-decisions to pin the choice instead.
    |
    */

    'models' => [
        'classification' => [
            'default' => env('LAYA_MODEL', 'auto'),
        ],
    ],

];
