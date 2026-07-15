<?php

namespace App\Support\Cart;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Container\Container;

class CartStorageResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly AuthManager $auth,
    ) {}

    public function resolve(): CartStorageInterface
    {
        return $this->auth->guard('web')->check()
            ? $this->container->make(DatabaseCartStorage::class)
            : $this->container->make(SessionCartStorage::class);
    }
}
