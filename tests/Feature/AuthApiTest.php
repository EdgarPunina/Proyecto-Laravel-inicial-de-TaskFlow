<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_guarda_password_cifrado_y_emite_un_token_utilizable(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.remember_token');

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
        $token = $response->json('token');
        $stored = PersonalAccessToken::findToken($token);
        $this->assertNotNull($stored);
        $this->assertSame($user->id, $stored->tokenable_id);
        $this->assertNotSame($token, $stored->token);
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    }

    public function test_register_valida_campos_y_rechaza_email_duplicado(): void
    {
        $this->postJson('/api/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->postJson('/api/register', [
            'name' => str_repeat('x', 256), 'email' => 'incorrecto', 'password' => 'corta',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $user = User::factory()->create();
        $this->postJson('/api/register', [
            'name' => 'Duplicado', 'email' => $user->email, 'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_correcto_emite_token_y_no_expone_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $response = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.password');

        $this->withToken($response->json('token'))->getJson('/api/tasks')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_login_rechaza_password_incorrecto_y_usuario_inexistente(): void
    {
        $user = User::factory()->create();
        foreach ([$user->email, 'inexistente@example.com'] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'incorrecta'])
                ->assertUnauthorized()->assertExactJson(['message' => 'Credenciales inválidas']);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_valida_los_datos_de_entrada(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revoca_solo_el_token_actual_y_los_otros_siguen_funcionando(): void
    {
        $user = User::factory()->create();
        $first = $user->createToken('primera-sesion');
        $second = $user->createToken('segunda-sesion');

        $this->withToken($first->plainTextToken)->postJson('/api/logout')->assertOk()
            ->assertExactJson(['message' => 'Sesión cerrada']);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $first->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $second->accessToken->id]);

        // Cada petición debe resolver el token nuevamente, como ocurre en HTTP real.
        $this->app['auth']->forgetGuards();
        $this->withToken($first->plainTextToken)->getJson('/api/tasks')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($second->plainTextToken)->getJson('/api/user')->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_user_y_logout_exigen_token_y_rechazan_tokens_invalidos(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->withToken('token-invalido')->getJson('/api/tasks')->assertUnauthorized();
        $this->withToken('token-invalido')->postJson('/api/logout')->assertUnauthorized();
    }
}
