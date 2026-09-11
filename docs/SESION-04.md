# Práctica de la sesión 04

> Historial de la sesión 04. El backend actual requiere autenticación y el script
> `scripts/check-api.cjs` verifica ahora la sesión 05. Consulta [SESION-05.md](SESION-05.md)
> para las instrucciones y la colección de Postman vigentes.

## Análisis del proyecto y adaptación del instructivo

La aplicación ejecutable está en `taskflow-backend`: PHP 8.1, Laravel 10, Eloquent y Sanctum 3. `taskflow-backend-starter` es una plantilla independiente del curso, sin el esqueleto completo de Laravel. Se conservaron sus cambios de arquitectura.

El starter disponible tiene ramas `sesion-02`, `sesion-03`, `sesion-05`, `sesion-06` y `sesion-07`; no tiene la rama `sesion-04` indicada en el PDF. Se implementaron directamente los archivos y contratos detallados en las páginas 7, 9 y 11 del instructivo.

Laravel 10 ya registra `routes/api.php` mediante `RouteServiceProvider`, y este proyecto ya incluye Sanctum y la migración de tokens. El comando `install:api` del instructivo corresponde a versiones posteriores y no está disponible aquí. Se verificaron las cinco rutas mediante `php artisan route:list --path=tasks`.

Se completaron los prerrequisitos ausentes: modelo `Task`, migración de tareas, relación `User::tasks()` y registro de `TaskObserver`. La factory existente se conservó y se limpió su formato. El estado predeterminado `pendiente` también se declara en el modelo para devolverlo en el JSON inmediatamente después de crear una tarea sin enviar `status`.

## API implementada

| Método | Ruta | Resultado |
|---|---|---|
| GET | `/api/tasks` | 200, colección en `data` |
| POST | `/api/tasks` | 201, tarea creada |
| GET | `/api/tasks/{task}` | 200, detalle; 404 si no existe |
| PUT / PATCH | `/api/tasks/{task}` | 200, actualización parcial |
| DELETE | `/api/tasks/{task}` | 204, sin cuerpo |

Cada tarea expone únicamente `id`, `title`, `description`, `status`, `user_id` y `created_at` (formato `Y-m-d H:i`).

Crear requiere título de hasta 255 caracteres y un usuario existente. La descripción admite texto o `null`. Los estados permitidos son `pendiente`, `en_progreso` y `completada`. Actualizar permite omitir campos y conserva el propietario. Los datos inválidos devuelven 422 cuando la petición solicita JSON (`Accept: application/json`). Las rutas de tareas permanecen públicas según esta sesión; la autenticación se incorpora en la siguiente práctica.

## Ejecutar en Windows

Desde `taskflow-backend`, con las dependencias instaladas y `.env` configurado:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-sqlite.ps1
```

Este script crea `database/practice.sqlite` si no existe, ejecuta migraciones pendientes, prepara un usuario de demostración con dos tareas y arranca `http://127.0.0.1:8000`. No modifica `.env`; las variables SQLite pertenecen al proceso del script. Reutiliza los datos al ejecutarlo nuevamente. `ExecutionPolicy Bypass` se aplica solo a ese proceso, porque Windows tiene deshabilitada la ejecución de scripts en esta máquina.

Para usar el MySQL configurado en `.env`, enciende el servicio y ejecuta:

```powershell
php artisan migrate
php artisan db:seed --class=TaskDemoSeeder
php artisan serve
```

## Verificación y entregables

```powershell
php artisan route:list --path=tasks
php artisan test
node scripts/check-api.cjs
```

El último comando necesita el servidor activo y Node.js 18 o posterior. Efectúa peticiones HTTP reales, comprueba los códigos de respuesta y guarda `docs/evidencia-http.json`. Elimina la tarea creada durante la comprobación y mantiene las dos tareas de demostración.

Resultados de la ejecución local del 11 de septiembre de 2026:

- Cinco rutas del recurso registradas.
- Doce tests aprobados, 43 aserciones; diez tests de API, incluidos los tres exigidos por el PDF.
- Once peticiones HTTP verificadas: colección, creación, detalle, PUT, PATCH, tres errores de validación, eliminación, 404 posterior y colección final.
- Pruebas con SQLite en memoria mediante `RefreshDatabase`; no utilizan la base MySQL de desarrollo.
- Formato PHP revisado con Laravel Pint en los archivos modificados.

Importa [TaskFlow-sesion-04.postman_collection.json](TaskFlow-sesion-04.postman_collection.json) en Postman y ejecuta sus peticiones en orden. La colección obtiene el usuario al listar y guarda automáticamente el ID de la tarea creada. La evidencia de ejecución real está en [evidencia-http.json](evidencia-http.json); se obtuvo con el cliente HTTP de Node, sin simular respuestas ni atribuirlas a una ejecución de Postman.

PHP emite un aviso previo de que `openssl` se carga dos veces en la instalación de XAMPP. No impidió las pruebas ni las respuestas JSON del servidor; no se modificó la configuración global de PHP.
