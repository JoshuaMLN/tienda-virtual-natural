<?php

return [
    'database' => 'tienda_virtual_natural_e2e',
    'reset_lock_name' => 'tienda_virtual_natural_e2e_reset',
    'customer' => [
        'email' => env('E2E_CUSTOMER_EMAIL', 'e2e.customer@vitanatural.test'),
        'password' => env('E2E_CUSTOMER_PASSWORD'),
    ],
    'admin' => [
        'email' => env('E2E_ADMIN_EMAIL', 'e2e.admin@vitanatural.test'),
        'password' => env('E2E_ADMIN_PASSWORD'),
    ],
];
