# TaskFlow ? Backend

API REST de tareas de Edgar Punina. Cada cuenta administra sus propias tareas con registro, login, cierre de sesi?n y CRUD protegido por Laravel Sanctum.

- Repositorio: https://github.com/EdgarPunina/Proyecto-Laravel-inicial-de-TaskFlow
- API p?blica temporal de la sesi?n 07: https://acquisition-tahoe-sequence-discrimination.trycloudflare.com/api
- Frontend, URL prevista de Pages: https://edgarpunina.github.io/taskflow-frontend/
- CI: https://github.com/EdgarPunina/Proyecto-Laravel-inicial-de-TaskFlow/actions/workflows/tests.yml

El frontend est? preparado para Pages, pero GitHub rechaz? habilitarlo porque el plan actual no admite Pages en el repositorio privado del frontend. La publicaci?n queda pendiente de disponer de un plan compatible o autorizar que ese repositorio sea p?blico.

## Stack y ejecuci?n local

Laravel 10, PHP 8.1 o superior (CI en 8.2), Eloquent, Sanctum 3 y PHPUnit 10. MySQL es la configuraci?n original; la demostraci?n utiliza SQLite persistente y los tests SQLite en memoria.

Desde la ra?z de este repositorio, con PHP, Composer y las extensiones mbstring, OpenSSL y pdo_sqlite disponibles:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-sqlite.ps1
```

Copia el archivo de entorno solo durante la primera instalaci?n. El script migra, crea datos de demostraci?n y sirve en http://127.0.0.1:8000. La base local es database/practice.sqlite y no se versiona. Para MySQL configura DB_* en .env, inicia MySQL, ejecuta php artisan migrate y php artisan serve.

## Arquitectura

![Arquitectura de TaskFlow](diagrama-arquitectura.svg)

```mermaid
flowchart LR
  Usuario --> React[React en GitHub Pages]
  React -->|HTTPS JSON y Bearer| Tunnel[Cloudflare Tunnel]
  Tunnel --> Laravel[Laravel API y Sanctum]
  Laravel --> Eloquent
  Eloquent --> SQLite[(SQLite de pr?ctica / MySQL)]
```

Las rutas delegan en AuthController y TaskController. Los controladores validan la entrada y consultan la relaci?n tasks del usuario autenticado; TaskResource limita la respuesta a los campos p?blicos. Consulta [las decisiones de arquitectura](ARQUITECTURA.md).

## Patrones de dise?o

**Observer (GoF):** app/Observers/TaskObserver.php observa las actualizaciones de Task; si cambia status registra el estado anterior y el nuevo. AppServiceProvider registra el observador con Task::observe. Esto desacopla la notificaci?n de los controladores. Los tests comprueban que cambiar otro campo no notifica un cambio de estado.

**Factory de Eloquent:** database/factories/TaskFactory.php centraliza los datos de prueba y crea un propietario con User::factory(). Es la herramienta de creaci?n de fixtures del framework; el patr?n GoF expl?cito aplicado en el dominio es Observer.

## Endpoints

| M?todo | Ruta | Uso |
|---|---|---|
| POST | /api/register, /api/login | Registro y emisi?n de token |
| GET | /api/user | Usuario autenticado |
| POST | /api/logout | Revocar el token actual |
| GET, POST | /api/tasks | Listar tareas propias / crear |
| GET, PATCH, PUT, DELETE | /api/tasks/{id} | Detalle / edici?n / eliminaci?n |

Salvo registro y login, requieren Authorization: Bearer y Accept: application/json. Sin token: 401; tarea ajena o inexistente: 404; datos inv?lidos: 422. La configuraci?n CORS existente admite api/* y el uso de Bearer entre or?genes.

## Exponer la API con Cloudflare

En una terminal:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\serve-public.ps1
```

En otra, instala cloudflared desde su distribuci?n oficial o usa el ejecutable port?til descargado en la carpeta hermana .tools:

```powershell
..\.tools\cloudflared.exe tunnel --url http://127.0.0.1:8001 --no-autoupdate
```

El puerto 8001 permite conservar el servidor local del puerto 8000. El script desactiva APP_DEBUG y sirve la base SQLite de pr?ctica. Copia la URL HTTPS impresa por cloudflared y a?ade /api; actualiza VITE_API_URL en el frontend y en Settings ? Secrets and variables ? Actions ? Variables. Despu?s vuelve a ejecutar el workflow de Pages: la variable se incorpora al compilar.

**Disponibilidad:** el t?nel es temporal. La API funciona mientras este equipo permanezca encendido y ambos procesos activos. Al reiniciar el t?nel cambia su URL. GitHub Pages conserva los archivos del frontend, pero no mantiene encendido Laravel. Para una revisi?n posterior hay que reabrir el t?nel y actualizar el despliegue, o contratar/configurar alojamiento persistente por separado.

## Pruebas y CI

```powershell
php artisan test
```

23 pruebas y 110 aserciones verifican autenticaci?n, aislamiento por usuario, CRUD, validaci?n, Resource y Observer. .github/workflows/tests.yml instala PHP 8.2 y dependencias, genera una clave nueva y ejecuta la suite en cada push a main, pull request o ejecuci?n manual. phpunit.xml fuerza SQLite en memoria: no requiere MySQL ni credenciales externas.

No se versionan .env, vendor, bases SQLite ni credenciales. Las gu?as [sesi?n 04](SESION-04.md) y [sesi?n 05](SESION-05.md) conservan la evidencia anterior.
