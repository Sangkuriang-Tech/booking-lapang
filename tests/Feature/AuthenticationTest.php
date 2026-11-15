<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('authenticates a customer via Google Sign-In callback and blocks staff from Google login', function (): void {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('google-new-123');
    $socialiteUser->shouldReceive('getEmail')->andReturn('newcustomer@example.test');
    $socialiteUser->shouldReceive('getName')->andReturn('Pelanggan Baru');
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');
    $response->assertRedirect(route('home'));

    $this->assertAuthenticated();
    $user = User::query()->where('email', 'newcustomer@example.test')->firstOrFail();
    expect($user->role)->toBe(User::ROLE_CUSTOMER);
    expect($user->google_id)->toBe('google-new-123');

    expect(AuditLog::query()->where('action', 'auth.google_login')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('throttles staff login after 5 failed attempts and records masked audit log', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post('/staff/login', [
            'email' => 'staff1@example.test',
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post('/staff/login', [
        'email' => 'staff1@example.test',
        'password' => 'WrongPassword123!',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan login');

    $failedAudit = AuditLog::query()->where('action', 'auth.staff_login_failed')->latest('id')->firstOrFail();
    expect($failedAudit->new_values['email_masked'])->toBe('s***@example.test');
});
