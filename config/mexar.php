<?php

return [
    'departments' => explode(',', env('MEXAR_DEPARTMENTS', '1,2,3')),
    'url' => env('MEXAR_URL'),
    'email' => env('MEXAR_AUTH_EMAIL'),
    'password' => env('MEXAR_AUTH_PASSWORD'),
];
