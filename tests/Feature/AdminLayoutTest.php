<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    public function test_admin_mobile_sidebar_has_open_close_and_backdrop_controls(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-sidebar', false)
            ->assertSee('data-admin-sidebar-close', false)
            ->assertSee('admin-sidebar-backdrop', false)
            ->assertSee('aria-label="Cerrar menu"', false);
    }
}
