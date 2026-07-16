<?php

namespace Tests\Concerns;

use App\Models\User;

trait AuthenticatesAdmins
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->actingAs($this->adminUser);
    }
}
