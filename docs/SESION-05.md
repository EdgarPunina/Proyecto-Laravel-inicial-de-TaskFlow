# Sesión 05: autenticación con Laravel Sanctum

## Resultado

Se implementaron los seis pasos del instructivo sobre la aplicación Laravel real (`taskflow-backend`). Registro y login emiten tokens; logout revoca únicamente el token de la petición. Las cinco operaciones de tareas usan la relación del usuario autenticado. Un ID ajeno devuelve 404 y no permite leer, editar ni eliminar la tarea.

## Revisión del punto de partida

- Laravel 10, Sanctum 3 y `HasApiTokens` ya estaban instalados. `php artisan migrate:status` confirmó `personal_access_tokens` como `Ran` en la base SQLite de práctica. No era necesario reinstalar paquetes ni ejecutar `install:api`, comando ausente en Laravel 10.
- La rama `origin/sesion-05` de la plantilla local corresponde al CRUD anterior y no contiene `AuthController`. Se implementó directamente el código del PDF conservando las pruebas y mejoras de la sesión 04. No se sobrescribió la plantilla ni su documento de arquitectura.
- Se conserva `status = pendiente` como valor predeterminado en el modelo y la migración; crear sin estado lo devuelve correctamente desde la primera respuesta. El ejemplo del PDF que muestra `status: null` no se reproduce, porque el propio instructivo define `pendiente` como valor por defecto.
- La consulta de propietario en `update` se realiza antes de validar el cuerpo, de modo que una tarea ajena siempre se trate como no encontrada.

## Contrato HTTP

Enviar siempre `Accept: application/json`; para cuerpos JSON, también `Content-Type: application/json`.

| Método y ruta | Acceso | Resultado |
|---|---|---|
| `POST /api/register` | Público | 200, `user` y `token`; valida nombre, email único y contraseña de al menos 8 caracteres |
| `POST /api/login` | Público | 200, `user` y `token`; 401 si las credenciales son incorrectas |
| `POST /api/logout` | Bearer token | 200, `Sesión cerrada`; revoca solo el token actual |
| `GET /api/user` | Bearer token | 200, usuario autenticado, sin contraseña |
| `GET /api/tasks` | Bearer token | 200, solo las tareas propias en `data` |
| `POST /api/tasks` | Bearer token | 201; el propietario se asigna desde el token |
| `GET /api/tasks/{id}` | Bearer token | 200 si es propia; 404 si es ajena o inexistente |
| `PUT / PATCH /api/tasks/{id}` | Bearer token | 200 si es propia; 404 si es ajena o inexistente |
| `DELETE /api/tasks/{id}` | Bearer token | 204 si es propia; 404 si es ajena o inexistente |

Las rutas protegidas devuelven 401 sin token válido. Los datos inválidos devuelven 422. Registro devuelve 200, tal como implementa el PDF. Crear o actualizar tareas ignora `user_id` enviado por el cliente: no permite elegir ni cambiar de propietario.

## Arrancar y probar

Desde `taskflow-backend`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-sqlite.ps1
```

Se mantiene `.env` con su configuración de MySQL. El script utiliza `database/practice.sqlite` solo para ese proceso. Con MySQL encendido también se puede usar `php artisan migrate` y `php artisan serve` directamente.

En otra terminal:

```powershell
php artisan route:list --path=api -v
php artisan test
node scripts/check-api.cjs
```

El verificador HTTP utiliza el ejecutable `curl` (Node solo coordina las peticiones y las aserciones). Envía JSON por la entrada estándar para evitar problemas de comillas en PowerShell. Crea Ana y Beto con correos únicos en cada ejecución; conserva esos usuarios para posteriores pruebas, elimina únicamente las tareas que acaba de crear y revoca sus tokens al terminar. No modifica las tareas de demostración anteriores.

Las credenciales de los últimos usuarios de demostración quedan en `storage/app/practice-credentials.json`, excluido de Git, para iniciar sesión desde el futuro frontend. La evidencia versionada omite tokens y contraseñas. No es necesario mantener tokens de esta ejecución: `/api/login` genera uno nuevo.

## Evidencia de verificación

Ejecución local del 11 de septiembre de 2026:

- **23 tests aprobados, 110 aserciones**. `TaskApiTest` contiene 14 tests, incluidos los cuatro del PDF; `AuthApiTest` agrega siete comprobaciones de autenticación. Se mantienen los dos tests base de Laravel.
- **33 peticiones reales mediante curl aprobadas**: registro, duplicados, login correcto e incorrecto, perfil, acceso sin token, token inválido, creación, listas separadas, bloqueo de GET/PUT/PATCH/DELETE ajenos, operaciones propias, validación y revocación selectiva de tokens.
- Nueve rutas API registradas; tareas, perfil y logout tienen `auth:sanctum`.
- Laravel Pint aprobó los cinco archivos PHP modificados o creados.
- Las pruebas automatizadas usan SQLite en memoria y `RefreshDatabase`, sin alterar los datos de desarrollo.

Archivos entregables:

- [Evidencia HTTP de la sesión 05](evidencia-http-sesion-05.json).
- [Colección de Postman de la sesión 05](TaskFlow-sesion-05.postman_collection.json): 25 peticiones para ejecutar en orden con Collection Runner. Genera correos únicos y guarda tokens e IDs automáticamente; incluye aislamiento entre Ana y Beto y logout. Se verificó la sintaxis de sus scripts; la ejecución HTTP registrada se realizó con curl.
- [Pruebas de tareas](../tests/Feature/TaskApiTest.php) y [pruebas de autenticación](../tests/Feature/AuthApiTest.php).

La colección y evidencia de la sesión 04 se conservan como historial. Para el backend actual debe usarse la colección de sesión 05, porque ahora las tareas exigen autenticación. El comando `node scripts/check-api.cjs` también corresponde ya a la sesión 05.

El aviso preexistente de XAMPP sobre `openssl` cargado dos veces continúa apareciendo en CLI; no impidió ninguna prueba y no se modificó la configuración global de PHP.
