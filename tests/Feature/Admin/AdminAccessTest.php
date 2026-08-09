<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_signed_in_visitor_who_is_not_an_admin_cannot_find_the_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin')->assertNotFound();
        $this->get('/admin/restaurants/anything/edit')->assertNotFound();
    }

    #[Test]
    public function an_administrator_reaches_the_register(): void
    {
        Restaurant::factory()->count(3)->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin')->assertOk();
    }

    #[Test]
    public function a_non_admin_cannot_sign_in_to_the_admin(): void
    {
        User::factory()->create([
            'email' => 'hungry@example.com',
            'password' => 'correct-horse',
            'is_admin' => false,
        ]);

        $this->post('/admin/login', [
            'email' => 'hungry@example.com',
            'password' => 'correct-horse',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function an_administrator_can_sign_in_and_out(): void
    {
        $admin = User::factory()->create([
            'email' => 'editor@kslive.au',
            'password' => 'correct-horse',
            'is_admin' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'editor@kslive.au',
            'password' => 'correct-horse',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);

        $this->post('/admin/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function repeated_failures_are_throttled(): void
    {
        User::factory()->create(['email' => 'editor@kslive.au', 'is_admin' => true]);

        foreach (range(1, 5) as $ignored) {
            $this->post('/admin/login', ['email' => 'editor@kslive.au', 'password' => 'wrong']);
        }

        $response = $this->post('/admin/login', ['email' => 'editor@kslive.au', 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many attempts',
            (string) session('errors')->first('email'),
        );
    }
}
