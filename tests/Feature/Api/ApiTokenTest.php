<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le jeton API contournait la vérification de l'adresse e-mail et restait
 * valable pour toujours : un jeton volé donnait un accès sans fin.
 */
class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): TestResponse
    {
        return $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ]);
    }

    private function getUserWith(string $token): TestResponse
    {
        app('auth')->forgetGuards();

        return $this->withToken($token)->getJson(route('api.v1.user'));
    }

    public function test_an_unverified_account_gets_no_token(): void
    {
        $user = User::factory()->unverified()->create();

        $this->login($user)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Merci de vérifier votre adresse e-mail avant de vous connecter.'])
            ->assertJsonMissingPath('token');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_wrong_password_does_not_reveal_that_the_account_is_unverified(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
            'device_name' => 'test',
        ])->assertUnprocessable();

        $this->assertStringNotContainsString('vérifier', $response->json('errors.email.0'));
    }

    public function test_a_verified_account_gets_a_working_token(): void
    {
        $user = User::factory()->create();

        $token = $this->login($user)->assertOk()->json('token');

        $this->getUserWith($token)->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_the_token_stops_working_after_30_days(): void
    {
        $token = $this->login(User::factory()->create())->json('token');

        $this->travel(29)->days();
        $this->getUserWith($token)->assertOk();

        $this->travel(2)->days();
        $this->getUserWith($token)->assertUnauthorized();
    }

    public function test_expired_tokens_are_removed_every_day(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression]);

        $prune = $commands->first(fn ($event) => str_contains((string) $event[0], 'sanctum:prune-expired'));

        $this->assertNotNull($prune);
        $this->assertSame('0 0 * * *', $prune[1]);
    }
}
