<?php

return [
    'enabled' => env('LOGIN_CODE_ENABLED', env('APP_ENV') === 'production'),
    'owner_username' => env('OWNER_USERNAME'),
    'owner_email' => env('OWNER_EMAIL'),
];
