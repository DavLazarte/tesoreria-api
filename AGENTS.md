# AGENTS.md — Plataforma de Tesorería

Leé este archivo completo antes de tocar código. Si algo de lo que te piden lo contradice, frená y preguntá.

> Este archivo existe **idéntico** en `tesoreria-api/AGENTS.md` y `app-tesoreria/AGENTS.md` (son carpetas separadas, sin raíz común). Si cambiás uno, copiá el cambio al otro.

## 1. Qué es esta app

Herramienta web para que la tesorera de una fundación:

- registre todos los movimientos económicos de la fundación (ingresos y egresos de una cuenta general),
- reparta presupuestos por **grupos** (por ejemplo Iglesias, Comedores) y entregue plata a **entidades** (personas físicas, iglesias, comedores, etc.),
- vea siempre cuánto tiene disponible, saque informes y los exporte (PDF y Excel),
- maneje usuarios con roles y permisos, con auditoría de todo.

El pedido es personal de la tesorera, no institucional. Lo desarrolla Dav (Devsign), solo. La interfaz es en **español rioplatense**. Hay pocos usuarios (tesorera con acceso total, contador en solo lectura).

El sistema es un **registro de movimientos**: no mueve plata real ni reemplaza una contabilidad o auditoría.

## 2. Estructura del workspace

| Carpeta | Ruta local | Qué es |
|---|---|---|
| `tesoreria-api/` | `C:\laragon\www\tesoreria-api` | Backend. Laravel solo como API (sin Blade), Sanctum para auth, spatie/laravel-permission para roles y permisos, MySQL 8 |
| `app-tesoreria/` | `C:\Users\Giuliano\Downloads\app-tesoreria` | Frontend. Next.js (App Router) + TypeScript + Tailwind + shadcn/ui (pnpm) |

- Entorno local: Laragon en Windows, PHP 8.3 (`C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64`; **no está en el PATH** de PowerShell, usar la ruta completa o la terminal de Laragon), MySQL 8.0.16 o superior.
- API local: `http://tesoreria-api.test/api` (o `php artisan serve`).
- Zona horaria: `America/Argentina/Tucuman`.
- La UI inicial se generó con v0 con datos mock. El trabajo del frontend es **reemplazar los mocks por llamadas reales** a la API.

## 3. Estado actual

> Actualizá esta sección al final de cada sesión de trabajo.

- [x] Diccionario de datos normalizado.
- [x] Migración `2026_10_27_192239_tesoreria.php` (probada en MySQL 8): tablas, CHECK, vista de saldos, triggers y datos iniciales (Caja general, tipos de entidad, categorías).
- [x] Laravel instalado con Sanctum y spatie/laravel-permission. Modelo `User` con `HasApiTokens`, `HasRoles` y `SoftDeletes`.
- [ ] Seeder de roles y permisos.
- [ ] Modelos, enums y relaciones.
- [ ] `TreasuryService` con tests.
- [ ] Endpoints de la API.
- [ ] Capa `lib/api/` del front (hoy no existe: solo hay `lib/utils.ts`; los mocks están dentro de las páginas/componentes).
- [ ] Conexión del frontend.
- Ninguna de las dos carpetas es repositorio git todavía.

## 4. Reglas de oro (no negociables)

1. **La plata nunca es float.** Base de datos `DECIMAL(15,2)`, en PHP `bcmath`, en JSON como **string** (`"1250000.00"`). En el front se formatea con `Intl.NumberFormat('es-AR')`.
2. **`movements` es un libro inmutable.** No hay UPDATE ni DELETE (los triggers de MySQL lo bloquean). Un error se corrige **anulando**: se inserta una fila en `movement_voids` con motivo obligatorio y se carga un movimiento nuevo.
3. **Los saldos no se guardan, se calculan** desde `movements` (vista `v_fund_balances`). Los movimientos anulados no cuentan.
4. **Todo movimiento de plata pasa por `TreasuryService`**, dentro de `DB::transaction`, con `lockForUpdate()` sobre la caja de origen antes de verificar el saldo. Nunca desde un controlador.
5. **Cada acción relevante deja registro en `audit_logs`**: quién, qué, antes y después, IP y user agent.
6. **No edites la migración ya aplicada.** Para cambiar el esquema, creá una migración nueva.
7. **Todo endpoint exige `auth:sanctum` y un permiso** (excepto el login).
8. No instales paquetes nuevos, no toques `.env` ni cambies reglas de negocio sin avisar a Dav.

## 5. Dominio

### Cajas (`funds`)

Hay **una Caja general** (`kind = general`) y **grupos** (`kind = group`), que son subcajas con presupuesto propio. Las dos viven en la misma tabla porque se comportan igual: tienen saldo y son origen o destino de movimientos.

### Tipos de movimiento

| type | from_fund | to_fund | entity | Qué es |
|---|---|---|---|---|
| `ingreso` | — | Caja general | donante (opcional) | Depósitos, donaciones. Entra plata de afuera |
| `egreso` | Caja general **o** un grupo | — | destinatario | Gasto o entrega. Sale plata hacia afuera |
| `asignacion` | Caja general | un grupo | — | Movimiento **interno**: reparte presupuesto. No es un gasto |
| `devolucion` | un grupo | Caja general | — | Movimiento **interno**: el grupo devuelve sobrante |

### Ejemplo de referencia (usalo en los tests)

1. Ingreso de $10.000.000 → Caja general = 10.000.000.
2. Asignación de $2.000.000 al grupo Iglesias → Caja general = 8.000.000, Iglesias = 2.000.000.
3. Egreso de $500.000 desde Iglesias a una iglesia integrante → Iglesias = 1.500.000, Caja general = **8.000.000** (no cambia).
4. Asignación de $1.000.000 a Comedores → Caja general = 7.000.000, Comedores = 1.000.000.
5. **Total de fondos** = suma de saldos de todas las cajas = 9.500.000 (= ingresos − egresos). Reservado en grupos = 2.500.000.

### Reglas de validación (en `TreasuryService`, no solo en el formulario)

- `amount > 0`, con hasta 2 decimales.
- No se puede mover más de lo disponible en la caja de origen (error `insufficient_funds`).
- `ingreso` va siempre a la Caja general. `asignacion` va de la Caja general a un grupo activo. `devolucion` va de un grupo a la Caja general.
- Un `egreso` desde un grupo **exige una entidad que sea integrante de ese grupo** (`entities.group_id`).
- La categoría debe coincidir con el flujo: categorías `ingreso` para ingresos, `egreso` para egresos; las internas no llevan categoría.
- `asignacion` y `devolucion` no llevan entidad.
- No se puede anular un movimiento si dejaría un saldo negativo (por ejemplo, anular una asignación cuya plata ya se entregó). Hay que anular primero los movimientos posteriores (error `cannot_void`).
- Los informes cuentan como **gasto real** solo los `egreso`. Las asignaciones y devoluciones se muestran aparte como movimientos internos.

### Supuestos pendientes de confirmar con la tesorera

- La `devolucion` es un agregado propio, no estaba en el pedido original.
- Una entidad pertenece a un solo grupo a la vez (si hace falta más de uno, se pasa a una tabla pivote).

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
