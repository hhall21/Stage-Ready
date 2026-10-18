<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Notification::fake();
    }

    public function test_reset_requests_have_the_same_response_for_active_unknown_and_inactive_emails(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $active = $this->postJson('/api/forgot-password', ['email' => strtoupper($user->email)])->assertOk()->json();
        $this->assertSame($active, $this->postJson('/api/forgot-password', ['email' => 'missing@example.com'])->assertOk()->json());
        $this->assertSame($active, $this->postJson('/api/forgot-password', ['email' => $inactive->email])->assertOk()->json());
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_reset_tokens_are_single_use_and_new_passwords_work(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $token = Notification::sent($user, ResetPassword::class)->first()->token;
        $password = 'ReplacementPassword123';
        $payload = ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertNotNull($user->fresh()->remember_token);
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])->assertOk();
    }

    public function test_expired_and_inactive_account_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'ReplacementPassword123', 'password_confirmation' => 'ReplacementPassword123'];
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
        $this->travelBack();
        $payload['token'] = Password::createToken($user);
        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_reset_invalidates_an_existing_authenticated_session(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->update(['password' => 'ReplacementPassword123']);
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->getJson('/api/user')->assertUnauthorized();
    }

    public function test_reset_requests_are_rate_limited_and_public_pages_load(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password', ['email' => 'missing@example.com'])->assertOk();
        }
        $this->postJson('/api/forgot-password', ['email' => 'missing@example.com'])->assertTooManyRequests();
        $this->withoutVite()->get('/forgot-password')->assertOk();
        $this->get('/reset-password/example?email=test@example.com')->assertOk();
    }
}
