<?php

namespace Tests\Feature;

use Tests\TestCase;

class TemplatePagesTest extends TestCase
{
    public function test_dashboard_and_authentication_pages_render_with_the_original_stylesheet(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('assets/css/styles.min.css')
            ->assertSee('assets/css/bmt-theme.css')
            ->assertSee('assets/images/logos/bmt-cinde-barokah.png')
            ->assertSee('bmt-login-page', false)
            ->assertSee('app-fixed-footer', false)
            ->assertSee('<div class="bmt-auth-brand text-center py-3 w-100">', false)
            ->assertDontSee('<a href="http://localhost/login" class="bmt-auth-brand', false)
            ->assertSee('Koperasi Konsumen Cinde Barokah Sejahtera © '.now()->year)
            ->assertSee('KOPERASI KONSUMEN')
            ->assertSee('Cinde Barokah Sejahtera')
            ->assertDontSee('Set up the first account')
            ->assertDontSee('Create Account');

        $this->get('/')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
    }

    public function test_unused_template_demo_pages_are_removed(): void
    {
        foreach ([
            '/ui-buttons',
            '/ui-alerts',
            '/ui-card',
            '/ui-forms',
            '/ui-typography',
            '/icon-tabler',
            '/sample-page',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
