<?php

namespace Tests\Feature;

use App\Models\ClientInvitation;
use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\PtTrackerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_malformed_login_fields_are_validation_errors(): void
    {
        $this->postJson('/api/login', ['email' => ['unexpected'], 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => 'person@example.test', 'password' => ['unexpected']])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
    }

    public function test_login_throttling_cannot_be_bypassed_by_rotating_email_addresses(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/login', ['email' => "person{$i}@example.test", 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'another@example.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_sql_injection_cannot_authenticate_or_change_query_scope(): void
    {
        $client = User::factory()->coached()->create();
        $this->postJson('/api/login', ['email' => "' OR 1=1 --", 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $client->email, 'password' => "' OR 1=1 --"])->assertUnprocessable();
        $this->assertGuest();

        $base = "/api/clients/{$client->id}";
        $this->actingAs($client)->getJson($base.'/exercise-progress?exercise_id=1%20OR%201%3D1')
            ->assertUnprocessable()->assertJsonValidationErrors('exercise_id');
        $this->getJson('/api/clients/1%20OR%201%3D1/workouts')->assertNotFound();
        $this->getJson($base.'/workouts/1%20OR%201%3D1')->assertNotFound();
        $this->getJson($base.'/end-of-day-nutrition?date=2026-10-01%27%20OR%201%3D1--')
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        $text = "Robert'); DROP TABLE users; -- <script>alert('xss')</script>";
        $this->postJson($base.'/daily-check-ins', ['check_in_date' => today()->toDateString(), 'notes' => $text])
            ->assertCreated()->assertJsonPath('data.notes', $text);
        $this->assertDatabaseHas('daily_check_ins', ['client_id' => $client->id, 'notes' => $text]);
        $this->assertDatabaseCount('users', 2);

        $exercise = Exercise::create(['exercise_name' => 'Squat']);
        $this->postJson($base.'/workouts', [
            'workout_name' => 'Legs', 'workout_date' => today()->toDateString(),
            'exercises' => [['exercise_id' => $exercise->id.' OR 1=1']],
        ])->assertUnprocessable()->assertJsonValidationErrors('exercises.0.exercise_id');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_public_auth_pages_handle_array_query_parameters_safely(): void
    {
        $this->withoutVite()->get('/login?next[]=unexpected')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('next', '/dashboard'));
        $this->get('/reset-password/example?email[]=unexpected')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('email', ''));
        $this->get('/login?next=//example.com')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('next', '/dashboard'));
    }

    public function test_passwords_exceeding_the_bcrypt_byte_limit_are_rejected_without_changing_the_account(): void
    {
        $client = User::factory()->create();
        $hash = $client->password;
        $token = Password::createToken($client);
        foreach ([str_repeat('a', 70).'A12', str_repeat('é', 35).'Aa1', "ValidPassword123\0suffix"] as $password) {
            $this->postJson('/api/reset-password', [
                'email' => $client->email, 'token' => $token,
                'password' => $password, 'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->postJson('/api/login', ['email' => $client->email, 'password' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertSame($hash, $client->fresh()->password);
        $this->assertTrue(Password::tokenExists($client, $token));
    }

    public function test_passwords_at_the_bcrypt_byte_limit_can_be_reset_and_used_to_sign_in(): void
    {
        $client = User::factory()->create();
        $password = str_repeat('a', 69).'A12';
        $this->postJson('/api/reset-password', [
            'email' => $client->email, 'token' => Password::createToken($client),
            'password' => $password, 'password_confirmation' => $password,
        ])->assertOk();
        $this->postJson('/api/login', ['email' => $client->email, 'password' => $password])->assertOk();
        $this->assertAuthenticatedAs($client);
    }

    public function test_unsafe_invitation_passwords_do_not_create_accounts_or_consume_the_link(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $token = str_repeat('a', 64);
        $invite = ClientInvitation::create([
            'trainer_id' => $coach->id, 'name' => 'Sample Client', 'email' => 'sample@example.test',
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(),
        ]);
        foreach ([str_repeat('a', 70).'A12', str_repeat('é', 35).'Aa1', "ValidPassword123\0suffix"] as $password) {
            $this->postJson('/api/invitations/'.$token.'/accept', ['password' => $password, 'password_confirmation' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertNull($invite->fresh()->accepted_at);
            $this->assertDatabaseMissing('users', ['email' => 'sample@example.test']);
        }
        $password = str_repeat('a', 69).'A12';
        $this->postJson('/api/invitations/'.$token.'/accept', ['password' => $password, 'password_confirmation' => $password])->assertOk();
        $this->assertTrue(Hash::check($password, User::where('email', 'sample@example.test')->firstOrFail()->password));
    }

    public function test_sensitive_responses_cannot_be_cached_framed_or_leak_tokens_in_referrers(): void
    {
        $client = User::factory()->create();
        $responses = [
            $this->withoutVite()->get('/login'),
            $this->get('/reset-password/example?email=client@example.test'),
            $this->getJson('/api/csrf-token'),
            $this->actingAs($client)->get('/dashboard'),
            $this->getJson('/api/clients/'.$client->id.'/daily-check-ins'),
            $this->getJson('/api/clients/999999/daily-check-ins'),
        ];
        foreach ($responses as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_authenticated_writes_require_a_valid_csrf_token(): void
    {
        $client = User::factory()->coached()->create();
        $this->app['env'] = 'local';
        $url = '/api/clients/'.$client->id.'/daily-check-ins';
        $this->actingAs($client)->withSession(['_token' => 'test-csrf-token'])
            ->postJson($url, ['check_in_date' => today()->toDateString()])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'incorrect')->postJson($url, ['check_in_date' => today()->toDateString()])->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'test-csrf-token')->postJson($url, ['check_in_date' => today()->toDateString()])->assertCreated();
    }

    public function test_demo_seeding_is_blocked_outside_local_and_testing_environments(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;
            try {
                (new PtTrackerSeeder)->run();
                $this->fail('Demo seeding must be blocked in '.$environment);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('local and testing', $exception->getMessage());
            }
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_demo_seeding_creates_documented_accounts_and_rejects_nonempty_databases(): void
    {
        User::factory()->create()->delete();
        (new PtTrackerSeeder)->run();
        $coach = User::where('email', 'james@pttracker.test')->firstOrFail();
        $client = User::where('email', 'adam@example.com')->firstOrFail();
        $this->assertSame('coach', $coach->role);
        $this->assertSame('client', $client->role);
        $this->assertTrue(Hash::check('password', $coach->password));
        $this->assertTrue(Hash::check('password', $client->password));
        $count = User::count();
        try {
            (new PtTrackerSeeder)->run();
            $this->fail('A populated database must not be seeded again.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('empty tables', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', $count);
    }
}
