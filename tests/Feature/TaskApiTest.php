<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_devuelve_200_con_la_coleccion_de_tareas(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Task::factory()->count(3)->for($user)->create();
        Task::factory()->count(2)->create();

        $this->getJson('/api/tasks')->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_store_con_datos_invalidos_devuelve_422(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/tasks', [])->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_store_con_datos_validos_crea_la_tarea_y_devuelve_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/tasks', [
            'title' => 'Escribir tests de la API',
        ])->assertStatus(201)->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.description', null);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Escribir tests de la API',
            'user_id' => $user->id,
            'status' => 'pendiente',
        ]);
    }

    public function test_show_expone_solo_los_seis_campos_del_resource(): void
    {
        $task = Task::factory()->create();
        Sanctum::actingAs($task->user);
        $this->getJson('/api/tasks/'.$task->id)->assertOk()->assertExactJson([
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'user_id' => $task->user_id,
                'created_at' => $task->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function test_update_parcial_conserva_titulo_y_propietario_y_notifica_el_cambio(): void
    {
        $task = Task::factory()->create(['status' => 'pendiente']);
        Sanctum::actingAs($task->user);
        $other = User::factory()->create();
        Log::spy();

        $this->patchJson('/api/tasks/'.$task->id, [
            'status' => 'en_progreso', 'user_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.status', 'en_progreso')
            ->assertJsonPath('data.title', $task->title)
            ->assertJsonPath('data.user_id', $task->user_id);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'en_progreso']);
        Log::shouldHaveReceived('info')->once()->with("Tarea #{$task->id} cambió de estado", [
            'anterior' => 'pendiente', 'nuevo' => 'en_progreso',
        ]);
    }

    public function test_put_permite_limpiar_descripcion_sin_notificar_cambio_de_estado(): void
    {
        $task = Task::factory()->create(['description' => 'Descripción anterior']);
        Sanctum::actingAs($task->user);
        Log::spy();
        $this->putJson('/api/tasks/'.$task->id, ['description' => null])
            ->assertOk()->assertJsonPath('data.description', null);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'description' => null]);
        Log::shouldNotHaveReceived('info');
    }

    public function test_destroy_elimina_la_tarea_y_devuelve_204_sin_cuerpo(): void
    {
        $task = Task::factory()->create();
        Sanctum::actingAs($task->user);
        $this->deleteJson('/api/tasks/'.$task->id)->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_recursos_inexistentes_devuelven_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/tasks/999999')->assertNotFound();
        $this->patchJson('/api/tasks/999999', ['status' => 'completada'])->assertNotFound();
        $this->deleteJson('/api/tasks/999999')->assertNotFound();
    }

    public function test_store_rechaza_campos_invalidos(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/tasks', [
            'title' => str_repeat('x', 256),
            'description' => ['invalida'],
            'status' => 'desconocido',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'description', 'status']);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_update_invalido_no_modifica_la_tarea(): void
    {
        $task = Task::factory()->create(['status' => 'pendiente']);
        Sanctum::actingAs($task->user);
        $this->patchJson('/api/tasks/'.$task->id, ['title' => null, 'status' => 'incorrecto'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'status']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => $task->title, 'status' => 'pendiente']);
    }

    public function test_un_usuario_no_puede_ver_la_tarea_de_otro(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Sanctum::actingAs($userA);
        $tareaDeB = Task::factory()->for($userB)->create();

        $this->getJson('/api/tasks/'.$tareaDeB->id)->assertNotFound();
    }

    public function test_un_usuario_no_puede_modificar_ni_borrar_la_tarea_de_otro(): void
    {
        $task = Task::factory()->create(['status' => 'pendiente']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/tasks/'.$task->id, ['status' => 'completada'])->assertNotFound();
        $this->patchJson('/api/tasks/'.$task->id, ['status' => 'completada'])->assertNotFound();
        $this->deleteJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pendiente']);
    }

    public function test_store_ignora_el_propietario_enviado_y_asigna_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', ['title' => 'Mi tarea', 'user_id' => $other->id])
            ->assertCreated()->assertJsonPath('data.user_id', $user->id);
        $this->assertDatabaseHas('tasks', ['title' => 'Mi tarea', 'user_id' => $user->id]);
    }

    public function test_todas_las_operaciones_exigen_autenticacion(): void
    {
        $task = Task::factory()->create();
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->postJson('/api/tasks', ['title' => 'Sin token'])->assertUnauthorized();
        $this->getJson('/api/tasks/'.$task->id)->assertUnauthorized();
        $this->putJson('/api/tasks/'.$task->id, ['status' => 'completada'])->assertUnauthorized();
        $this->patchJson('/api/tasks/'.$task->id, ['status' => 'completada'])->assertUnauthorized();
        $this->deleteJson('/api/tasks/'.$task->id)->assertUnauthorized();
    }
}
