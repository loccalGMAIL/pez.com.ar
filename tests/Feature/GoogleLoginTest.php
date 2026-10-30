<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'secret']);
    }

    private function panelUrl(string $path = '/'): string
    {
        return 'http://'.config('app.admin_domain').$path;
    }

    private function googleReturns(GoogleUser|\Throwable $result): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $result instanceof \Throwable
            ? $provider->shouldReceive('user')->andThrow($result)
            : $provider->shouldReceive('user')->andReturn($result);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function googleUser(string $email, bool $verified = true): GoogleUser
    {
        return (new GoogleUser)
            ->setRaw(['email_verified' => $verified])
            ->map(['id' => 'google-123', 'email' => $email, 'name' => 'Claudio', 'avatar' => 'https://lh3.googleusercontent.com/a/photo']);
    }

    public function test_login_page_offers_google_when_configured(): void
    {
        $this->get($this->panelUrl('/login'))->assertOk()->assertSee('Ingresar con Google');

        config(['services.google.client_id' => null]);
        $this->get($this->panelUrl('/login'))->assertOk()->assertDontSee('Ingresar con Google');
        $this->get($this->panelUrl('/auth/google/redirect'))->assertNotFound();
    }

    public function test_redirects_to_google(): void
    {
        $this->get($this->panelUrl('/auth/google/redirect'))
            ->assertRedirectContains('accounts.google.com')
            ->assertRedirectContains(urlencode($this->panelUrl('/auth/google/callback')));
    }

    public function test_an_existing_user_signs_in_with_google(): void
    {
        $user = User::factory()->create(['email' => 'Loccal@gmail.com']);
        $this->googleReturns($this->googleUser('loccal@gmail.com'));

        $this->get($this->panelUrl('/auth/google/callback'))->assertRedirect(rtrim($this->panelUrl(), '/'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->refresh()->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/a/photo', $user->getFilamentAvatarUrl());
    }

    public function test_unknown_google_accounts_are_rejected(): void
    {
        User::factory()->create(['email' => 'loccal@gmail.com']);
        $this->googleReturns($this->googleUser('otro@gmail.com'));

        $this->get($this->panelUrl('/auth/google/callback'))->assertRedirect($this->panelUrl('/login'));
        $this->assertGuest();

        Notification::assertNotified('La cuenta otro@gmail.com no tiene acceso al sistema.');
    }

    public function test_unverified_google_emails_are_rejected(): void
    {
        User::factory()->create(['email' => 'loccal@gmail.com']);
        $this->googleReturns($this->googleUser('loccal@gmail.com', verified: false));

        $this->get($this->panelUrl('/auth/google/callback'))->assertRedirect($this->panelUrl('/login'));
        $this->assertGuest();
    }

    public function test_failed_or_cancelled_logins_go_back_to_the_login(): void
    {
        $this->googleReturns(new InvalidStateException);

        $this->get($this->panelUrl('/auth/google/callback'))->assertRedirect($this->panelUrl('/login'));
        $this->assertGuest();
    }
}
