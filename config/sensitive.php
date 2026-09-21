<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dedicated sensitive-data key
    |--------------------------------------------------------------------------
    |
    | Encrypts passport numbers and medical / dietary / accessibility notes.
    | Same format as APP_KEY (base64: + 32 bytes). Must never fall back to
    | APP_KEY: rotating sessions must not make passports unreadable, and a
    | leaked APP_KEY must not decrypt them.
    |
    | Generate: php artisan key:generate --show
    | Paste the value into SENSITIVE_DATA_KEY — do not reuse APP_KEY.
    |
    | Rotation (later sprint, not built): SENSITIVE_DATA_PREVIOUS_KEYS as a
    | comma-separated list, decrypt-with-fallback, and a re-encrypt command.
    |
    */

    'key' => env('SENSITIVE_DATA_KEY'),

];
