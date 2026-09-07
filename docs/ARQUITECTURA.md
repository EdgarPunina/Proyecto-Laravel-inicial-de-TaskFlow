# Arquitectura de TaskFlow

> Este archivo es parte de la Tarea 2 del curso (se asigna al cierre de la Sesión 2, se entrega en la Sesión 4).

## Diagrama

Diagrama C4 (Nivel 2 - Contenedores):

- Persona (usuario final) → usa → SPA (React)
- SPA (React), interfaz de usuario → HTTPS/JSON → API (Laravel), backend REST
- API (Laravel) → SQL → Base de datos (MySQL), persistencia de datos

## Decisiones de arquitectura

- **Arquitectura desacoplada (API-first):** Laravel actúa solo como API REST, sin renderizar vistas con Blade, para que el frontend en React consuma los datos de forma independiente y ambos puedan desplegarse, versionarse y escalar por separado.
- **Capas del backend:**las rutas (`routes/api.php`) y controladores forman la capa de presentación; los modelos Eloquent concentran la lógica de negocio y las reglas del dominio; las migraciones definen y versionan el acceso a datos.
