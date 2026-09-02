<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_home_page_redirects_to_staff_login_without_device_activation(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
