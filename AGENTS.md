# AGENTS.md — Plataforma de Tesorería

Leé este archivo completo antes de tocar código. Si algo de lo que te piden lo contradice, frená y preguntá.

> Este archivo existe **idéntico** en `tesoreria-api/AGENTS.md` y `app-tesoreria/AGENTS.md` (son carpetas separadas, sin raíz común). Si cambiás uno, copiá el cambio al otro.

## 1. Qué es esta app

Herramienta web para que la tesorera de una fundación:

- registre todos los movimientos económicos de la fundación (ingresos y egresos de una cuenta o caja general),
- entregue fondos a **entidades** (iglesias, comedores, personas físicas, etc.) y registre gastos propios (compras, proveedores, servicios),
- vea siempre cuánto dinero real tiene disponible en la caja, saque informes y los exporte (PDF y Excel),
- maneje usuarios con roles y permisos, con auditoría de todo.

El pedido es personal de la tesorera, no institucional. Lo desarrolla Dav (Devsign), solo. La interfaz es en **español rioplatense**. Hay pocos usuarios (tesorera con acceso total, contador en solo lectura).

El sistema es un **registro de movimientos**: no mueve plata real ni reemplaza una contabilidad o auditoría.

## 2. Estructura del workspace

| Carpeta | Ruta local | Qué es |
|---|---|---|
| `tesoreria-api/` | `C:\laragon\www\tesoreria-api` | Backend. Laravel solo como API (sin Blade), Sanctum para auth, spatie/laravel-permission para roles y permisos, MySQL 8 |
| `app-tesoreria/` | `C:\Users\Giuliano\Downloads\app-tesoreria` | Frontend. Next.js (App Router) + TypeScript + Tailwind + shadcn/ui (pnpm) |

- Entorno local: Laragon en Windows, PHP 8.3 (`C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64`), MySQL 8.0.16 o superior.
- API local: `http://tesoreria-api.test/api` (o `php artisan serve`).
- Base de datos principal: `tesoreria`. Base de datos exclusiva de tests: `tesoreria_test`.
- Zona horaria: `America/Argentina/Tucuman`.
- La UI inicial se generó con v0 con datos mock. El trabajo del frontend es **reemplazar los mocks por llamadas reales** a la API.

## 3. Estado actual

> Actualizá esta sección al final de cada sesión de trabajo.

- [x] Diccionario de datos normalizado.
- [x] Migración `2026_10_27_192239_tesoreria.php`: tablas, CHECK, vista de saldos, triggers y datos iniciales.
- [x] Laravel instalado con Sanctum y spatie/laravel-permission. Modelo `User` con `HasApiTokens`, `HasRoles` y `SoftDeletes`.
- [x] Seeder de roles y permisos (`RolesAndPermissionsSeeder` + `InitialUserSeeder` con Karina Noble como tesorera, e `InitialCatalogsSeeder`). Tests pasando al 100% en `tesoreria_test`.
- [x] Modelos, enums y relaciones completados (Fund, Entity, EntityType, Category, Movement, MovementVoid, MovementAttachment, AuditLog, AccessLog y User adaptado).
- [x] `TreasuryService` adaptado al flujo directo (Caja General única para etapa 1) con tests actualizados pasando al 100%.
- [x] Endpoints de auth (`login`, `logout`, `me`) con `access_logs`.
- [x] CRUD de entities, entity-types, categories y funds.
- [x] Endpoints de movements y adjuntos.
- [x] Dashboard, informes y exportación.
- [x] Endpoints de users, roles y auditoría.
- [x] Conexión del frontend al 100% (Auth/Login con cookie httpOnly, Dashboard en tiempo real, Movimientos con carga/descarga de comprobantes y anulación con motivo obligatorio, Entidades CRUD, Informes con exportación PDF/Excel, Usuarios y Roles con control de permisos, y Libro de Auditoría y Accesos conectados con la API).

## 4. Reglas de oro (no negociables)

1. **La plata nunca es float.** Base de datos `DECIMAL(15,2)`, en PHP `bcmath`, en JSON como **string** (`"1250000.00"`). En el front se formatea con `Intl.NumberFormat('es-AR')`.
2. **`movements` es un libro inmutable.** No hay UPDATE ni DELETE (los triggers de MySQL lo bloquean). Un error se corrige **anulando**: se inserta una fila en `movement_voids` con motivo obligatorio y se carga un movimiento nuevo.
3. **Los saldos no se guardan, se calculan** desde `movements` (vista `v_fund_balances`). Los movimientos anulados no cuentan.
4. **Todo movimiento de plata pasa por `TreasuryService`**, dentro de `DB::transaction`, con `lockForUpdate()` sobre la caja de origen antes de verificar el saldo. Nunca desde un controlador.
5. **Cada acción relevante deja registro en `audit_logs`**: quién, qué, antes y después, IP y user agent.
6. **No edites la migración ya aplicada.** Para cambiar el esquema, creá una migración nueva.
7. **Todo endpoint exige `auth:sanctum` y un permiso** (excepto el login).
8. No instales paquetes nuevos, no toques `.env` ni cambies reglas de negocio sin avisar a Dav.
9. **El agente no corre migraciones ni seeders en la base de desarrollo/producción** (`migrate`, `db:seed`, etc.): solo los crea y le pasa a Dav el comando para que lo corra él en la terminal de Laragon. Excepción autorizada por Dav: el agente sí puede ejecutar `php artisan test` utilizando la base exclusiva de pruebas `tesoreria_test`.

## 5. Dominio

### Caja (`funds`)

- En esta etapa inicial la tesorería opera con **una única Caja general** (`kind = general`), que representa la cuenta/disponibilidad real de la fundación.
- La funcionalidad de "subcajas/grupos presupuestarios" queda **pausada** hasta que Karina valide el uso en producción.

### Tipos de movimiento

| type | from_fund | to_fund | entity | category | Qué es |
|---|---|---|---|---|---|
| `ingreso` | — | Caja general | donante/origen (opcional) | categoría `ingreso` (obligatoria) | Donaciones, depósitos bancarios, subsidios recibidos. Entra dinero a la fundación |
| `egreso` | Caja general | — | destinatario/proveedor (opcional si es gasto menor) | categoría `egreso` (obligatoria) | Salida real de dinero: entregas a iglesias/comedores, compras de insumos, servicios, etc. |

### Dinámica de saldos

- **Saldo disponible real** = $\sum \text{Ingresos no anulados} - \sum \text{Egresos no anulados}$.
- Cada egreso reduce inmediatamente el disponible de la Caja general (la plata salió realmente).
- Los informes permiten clasificar los egresos por:
  - **Categoría**: Gastos generales, Servicios, Ayudas y subsidios, etc.
  - **Tipo de Entidad**: Iglesias, Comedores, Proveedores, etc.
  - **Entidad puntual**: Historial detallado de cuánto dinero recibió o facturó cada entidad.

### Reglas de validación (en `TreasuryService`)

- `amount > 0`, con hasta 2 decimales.
- No se puede realizar un egreso si supera el saldo disponible de la Caja general (error `insufficient_funds`).
- `ingreso` va siempre a la Caja general y exige una categoría de flujo `ingreso`.
- `egreso` sale siempre de la Caja general y exige una categoría de flujo `egreso`. La entidad es opcional para permitir gastos menores directos.
- No se puede anular un movimiento si ya está anulado, o si la anulación de un ingreso dejase el saldo total en negativo (error `cannot_void`).
- Anular exige motivo obligatorio (`reason`).

## 6. Modelo de datos (resumen)

Todas las tablas usan `id BIGINT UNSIGNED` y nombres en inglés `snake_case`.

| Tabla | Campos clave |
|---|---|
| `users` | name, email, password, is_active, last_login_at, deleted_at (soft delete) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | estructura estándar de spatie |
| `personal_access_tokens` | estándar de Sanctum |
| `funds` | name, kind (general/group), description, is_active, `general_flag` (garantiza una sola caja general) |
| `entity_types` | name, is_active (editable por la tesorera) |
| `entities` | entity_type_id, group_id → funds, name, document_number, phone, email, address, notes, is_active, soft delete |
| `categories` | name, flow (ingreso/egreso), is_active |
| `movements` | type, movement_date, from_fund_id, to_fund_id, entity_id, category_id, amount, concept, reference, note, created_by, created_at (**sin updated_at**) |
| `movement_voids` | movement_id (UNIQUE), voided_by, reason, voided_at |
| `movement_attachments` | movement_id, disk, path, original_name, mime_type, size_bytes, sha256, uploaded_by |
| `audit_logs` | user_id, event, auditable_type, auditable_id, old_values (JSON), new_values (JSON), ip_address, user_agent, created_at |
| `access_logs` | user_id, email_attempted, event (login/logout/login_failed), ip_address, user_agent, created_at |
| vista `v_fund_balances` | fund_id, name, kind, balance |

## 7. Autenticación, roles y permisos

- **Sanctum con tokens personales**: `POST /api/auth/login` devuelve un token. Sanctum solo autentica, no maneja roles.
- **Roles y permisos con spatie/laravel-permission.** Permisos:
  `movements.view`, `movements.create`, `movements.void`, `funds.view`, `funds.manage`, `entities.view`, `entities.manage`, `reports.view`, `reports.export`, `users.manage`, `audit.view`.
- **Roles iniciales:** `tesorera` (todos los permisos) y `contador` (`*.view`, `reports.view`, `reports.export`).
- Las rutas se protegen con el middleware `permission:...`.
- Los usuarios inactivos (`is_active = 0`) no pueden iniciar sesión.

## 8. Auditoría

- `audit_logs` es **append-only** (triggers bloquean UPDATE y DELETE). Se escribe desde un `AuditService::log($event, $model, $old, $new)` llamado por los servicios, tomando IP y user agent del request.
- Eventos mínimos: `movement.created`, `movement.voided`, `fund.created`, `fund.updated`, `entity.created`, `entity.updated`, `user.created`, `user.updated`, `role.permissions_changed`, `report.exported`.
- `access_logs` registra logins, logouts y logins fallidos desde los endpoints de auth.

## 9. Contrato de la API (propuesto: ajustalo y mantenelo sincronizado con el front)

Base `/api`. JSON. Usar **API Resources** de Laravel. Montos como string decimal, fechas `Y-m-d`, timestamps ISO 8601. Listados con el paginador estándar de Laravel (`page`, `per_page`).

**Errores:** 401 y 403 estándar. Validación → 422 `{ message, errors: { campo: [..] } }`. Errores de dominio → 422 `{ message, code }` con `code` en `insufficient_funds`, `invalid_movement`, `cannot_void`.

| Método y ruta | Permiso | Descripción |
|---|---|---|
| `POST /auth/login` | público | `{email, password}` → `{token, user}` |
| `POST /auth/logout` | auth | Revoca el token actual |
| `GET /me` | auth | Usuario, roles y permisos |
| `GET /dashboard` | movements.view | `general_available`, `reserved_in_groups`, `total_funds`, `expenses_month`, `groups[{id,name,assigned,delivered,available}]`, `monthly[{month,ingresos,egresos}]`, `latest_movements[]` |
| `GET /movements` | movements.view | Filtros: `from`, `to`, `type`, `fund_id`, `entity_id`, `category_id`, `voided`, `q` |
| `GET /movements/{id}` | movements.view | Detalle con adjuntos y anulación |
| `POST /movements` | movements.create | `{type, movement_date, from_fund_id?, to_fund_id?, entity_id?, category_id?, amount, concept, reference?, note?}` |
| `POST /movements/{id}/void` | movements.void | `{reason}` |
| `POST /movements/{id}/attachments` | movements.create | Sube comprobante (PDF o imagen), en storage privado |
| `GET /movements/{id}/attachments/{attachment}` | movements.view | Descarga |
| `GET /funds`, `GET /funds/{id}` | funds.view | Con saldo; el detalle incluye integrantes y lo recibido por cada uno |
| `POST /funds`, `PUT /funds/{id}` | funds.manage | Crear y editar grupos |
| `GET /entities`, `GET /entities/{id}` | entities.view | Filtros por tipo y grupo; ficha con historial |
| `POST /entities`, `PUT /entities/{id}` | entities.manage | Alta y edición (incluye `group_id`) |
| `GET/POST/PUT /entity-types` | entities.manage | Tipos de entidad |
| `GET/POST/PUT /categories` | entities.manage | Categorías |
| `GET /reports/summary` | reports.view | Filtros: `from`, `to`, `fund_id`, `entity_id`, `type` |
| `GET /reports/export?format=pdf\|xlsx` | reports.export | Descarga y deja `report.exported` en auditoría |
| `GET/POST/PUT /users`, `GET /roles`, `PUT /roles/{id}/permissions` | users.manage | Usuarios y roles |
| `GET /audit-logs`, `GET /access-logs` | audit.view | Auditoría |

## 10. Frontend (`app-tesoreria`)

- Next.js App Router, TypeScript estricto (sin `any`), Tailwind, shadcn/ui.
- Capa `lib/api/`: cliente fetch y un servicio por recurso. **Todavía no existe**: primero se crea devolviendo los mocks actuales con la forma real de la API, y después se cambia la implementación por llamadas reales **sin cambiar las firmas**.
- **Auth:** el login pasa por un route handler de Next que llama a Laravel y guarda el token en una **cookie httpOnly** (nunca en localStorage). `middleware.ts` protege las rutas. Las llamadas a la API se hacen del lado del servidor (route handlers o server actions) agregando `Authorization: Bearer <token>`. Variable de entorno solo de servidor: `API_URL=http://tesoreria-api.test/api` (sin `NEXT_PUBLIC`). Así no hace falta CORS.
- Tras el login se pide `/me`; la UI **oculta o deshabilita acciones según permisos**.
- Los tipos TypeScript deben coincidir con los API Resources de Laravel; si cambia uno, cambia el otro.
- Formato: montos con `Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' })`, fechas `dd/mm/aaaa`.
- Pantallas: Login, Panel, Movimientos (con panel de detalle y anulación), Nuevo movimiento, Grupos y detalle de grupo, Entidades, Informes, Usuarios y roles, Auditoría.
- El formulario de movimiento muestra el disponible del origen y cómo quedaría, y valida antes de enviar. **La validación real es la del backend.**

## 11. Convenciones

**Backend (Laravel):** PSR-12. Validación en Form Requests. Respuestas con API Resources. Enums de PHP para los tipos. Lógica de negocio en `app/Services`, controladores finos. Mensajes de error en español. Nombres de tablas, columnas y código en inglés.

**Frontend:** componentes en `components/`, tipos en `types/`, un servicio por recurso en `lib/api/`. Textos de interfaz en español.

**Git:** commits chicos y por módulo, mensajes en español.

## 12. Tests

- Backend: tests de feature (PHPUnit o Pest) para `TreasuryService` con el **ejemplo de referencia** de la sección 5. Casos obligatorios: asignar más de lo disponible, entregar más del presupuesto del grupo, egreso directo desde la Caja general, egreso desde un grupo a una entidad que no es integrante, anular con plata ya gastada, anulado que no cuenta en el saldo, y permisos por rol.
- Frontend: `tsc --noEmit` y lint sin errores.

## 13. Cómo trabajar

- Cambios chicos, por módulo, y explicá qué cambiaste y por qué.
- Nada de refactors fuera de alcance.
- Si una regla de la plata es ambigua, **preguntá** en vez de asumir.
- Al terminar, actualizá la sección 3 (Estado) en **ambas** copias.
- Si hay revisión cruzada entre agentes, el revisor se concentra en `TreasuryService`, los saldos, los permisos y la auditoría.

## 14. Orden sugerido de trabajo

1. Seeder de roles y permisos.
2. Modelos, enums y relaciones.
3. `TreasuryService` y sus tests (con el ejemplo de referencia).
4. Endpoints de auth (`login`, `logout`, `me`) con `access_logs`.
5. CRUD de funds, entities, entity-types y categories.
6. Endpoints de movements y adjuntos.
7. Dashboard, informes y exportación (paquetes de PDF y Excel: **consultar a Dav antes de elegirlos**).
8. Endpoints de users, roles y auditoría.
9. Conectar el frontend, módulo por módulo, reemplazando los mocks.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
