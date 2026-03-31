# Fleet Management System Architecture

**Version:** 1.1  
**Last Updated:** 2026-03-30  
**Status:** Phase 0 complete, Phase 1 foundation underway

## 1. Enterprise Solution Overview

The Fleet Management System is being built as a reusable SaaS-ready operating platform for transport-heavy organisations. The design target is not a single-company CRUD app. It is a modular system that can support multiple tenants, configurable workflows, audit trails, API-first integration, and phased rollout by module.

Primary outcomes:

- manage vehicles, drivers, journeys, fuel, maintenance, compliance, incidents, documents, vendors, approvals, and reporting
- support one deployment for one company or many companies
- isolate tenant data by default
- keep business permissions configuration-driven rather than role-name-driven
- keep modules independently evolvable

## 2. Product Framing

### Core User Personas

- Platform Operator: manages tenant onboarding, tenant suspension, global platform support
- Tenant Administrator: configures organisation, users, roles, branding, and settings
- Fleet Manager: manages vehicles, drivers, allocations, and operational exceptions
- Transport Officer: coordinates journeys, dispatch, inspections, and daily fleet usage
- Maintenance Officer: manages schedules, work orders, service providers, and asset health
- Compliance Officer: manages insurance, permits, licenses, renewals, and document validity
- Finance Officer: consumes cost, fuel, maintenance, and utilisation reports
- Driver: views assignments and submits operational records permitted by policy
- Approver: reviews workflow stages such as trip approvals, disposal approvals, or exceptional spend

### Functional Requirements

- multi-tenant company onboarding and isolation
- tenant-aware user, role, and permission management
- fleet master data and document lifecycle management
- operational workflows for trips, fuel, inspections, incidents, and approvals
- maintenance, component lifecycle, and compliance reminders
- analytics, exports, dashboards, and audit history

### Non-Functional Requirements

- secure-by-default authorization and input validation
- clean module boundaries with reusable UI and backend conventions
- horizontal API scalability through stateless services and queues
- mobile-friendly responsive SPA
- testable workflows and predictable API contracts
- operational readiness: logging, environment separation, storage abstraction, CI/CD readiness

## 3. Recommended Technology Stack

### Chosen Stack

- Vue 3 + TypeScript + Vite + Pinia + Vue Router + Tailwind CSS
- Laravel 13 + PHP 8.3 + Sanctum + Form Requests + Policies + Resources
- PostgreSQL as the primary relational store
- Redis for queues, cache, and future throttling/lock support
- S3-compatible object storage for documents
- Docker Compose for local dependencies

### Justification

- Vue 3 and Laravel provide mature ecosystems, strong typing options, and long-term maintainability.
- PostgreSQL is better suited than MySQL for reporting-heavy enterprise workloads, partial indexes, JSONB configuration, and future analytics extensions.
- Sanctum is appropriate because the first client is a first-party SPA. OAuth-style delegation can be layered later if third-party client access becomes a requirement.
- Tailwind supports a disciplined design system without accumulating per-screen CSS drift.

### Trade-off Notes

- The current implementation keeps internal numeric IDs for early delivery velocity. Before broad external integrations, public-facing opaque identifiers should be added to externally referenced entities if enumeration risk or integration exposure grows.
- Roles are tenant-scoped, not global. This is the correct choice for per-organisation adaptability, while permissions remain globally defined.

## 4. Recommended Architecture

```text
Browser / Tablet / Mobile SPA
        |
        v
Vue 3 SPA (presentation, routing, view state, form workflows)
        |
        v
Laravel API (auth, policies, services, resources, audit hooks)
        |
        +--> PostgreSQL (system of record)
        +--> Redis (queues, cache, locks)
        +--> S3-compatible storage (documents, attachments)
```

### Backend Architectural Shape

- `Http`: controllers, requests, resources, middleware
- `Models`: persistence-backed entities
- `Policies`: permission and tenancy enforcement
- `Services` or `Actions`: workflow orchestration when controller logic becomes non-trivial
- `Support`: tenancy, auditing, response formatting, and shared infrastructure concerns

### Frontend Architectural Shape

- `modules`: feature-owned screens and local view logic
- `components/ui`: reusable UI primitives such as page headers, badges, cards, tables, alerts, and filter bars
- `components/layout`: app shell, sidebar, header, navigation
- `stores`: cross-cutting session and application state
- `plugins`: API client configuration and interceptors

## 5. Multi-Tenancy Strategy

### Tenancy Model

Chosen model: single database multi-tenancy with `tenant_id` on tenant-owned records.

Why this model:

- simpler operations than database-per-tenant
- lower cost and faster onboarding for SMEs and enterprise subsidiaries
- easier shared reporting for platform operators
- clear near-term scaling path through indexing, partitioning, and read replicas

### Tenant-Isolated Entities

- users
- roles
- settings
- vehicles and all fleet operational data
- documents, incidents, maintenance, compliance, and notifications

### Global Platform Entities

- tenants
- permissions
- platform-level support users and platform metadata

### Guardrails

- every tenant-scoped table must include `tenant_id`, index it, and scope uniqueness with it
- controllers enforce policies; models apply tenant scoping for normal tenant users
- destructive cross-tenant queries must explicitly opt out of tenant scope
- audit logs retain `tenant_id` for reporting and forensic traceability

## 6. RBAC Strategy

### Authorization Model

- authorization checks use permissions, not role names
- roles are tenant-scoped collections of permissions
- platform administrators are modelled separately from tenant roles using `is_super_admin`
- Laravel policies are the enforcement layer for all stateful resources

### Permission Convention

`{module}.{action}`

Examples:

- `users.view`
- `roles.update`
- `vehicles.assign`
- `maintenance.approve`
- `reports.export`

### Default Role Templates

These are seed templates, not hardcoded business logic:

- Tenant Admin
- Fleet Manager
- Transport Officer
- Maintenance Officer
- Compliance Officer
- Finance Officer
- Driver
- Department Manager
- Viewer

Organisations must be able to clone, modify, or replace tenant roles later without code changes.

## 7. High-Level ERD

```text
tenants
  ├── users ──< role_user >── roles ──< permission_role >── permissions
  ├── settings
  ├── vehicle_types
  ├── departments
  ├── vehicles ──< vehicle_documents
  │      ├── vehicle_assignments
  │      ├── fuel_logs
  │      ├── odometer_logs
  │      ├── inspections
  │      ├── incidents
  │      ├── maintenance_schedules
  │      ├── maintenance_records
  │      └── compliance_items
  ├── drivers ──< driver_documents
  ├── service_providers
  ├── trips
  ├── notifications
  └── audit_logs
```

The detailed schema contract is maintained in [ERD](erd.md).

## 8. Module Breakdown

- Authentication & Authorization
- Tenant / Company Management
- User Management
- Role & Permission Management
- Fleet / Vehicle Management
- Driver Management
- Department / Cost Centre Management
- Vehicle Allocation Management
- Trip / Journey Management
- Fuel Management
- Maintenance Management
- Compliance & Document Management
- Inspections & Checklist Management
- Incident Management
- Vendors / Service Providers
- Notifications & Alerts
- Reports & Dashboards
- Audit Logs
- System Settings / Configurations

The detailed module catalogue is maintained in [Module Catalogue](modules.md).

## 9. API Strategy

- versioned API under `/api/v1`
- JSON response envelope: `message`, `data`, optional `meta`, optional `errors`
- Form Request validation for all mutating endpoints
- Resource classes for shape control
- policy-based authorization before business execution
- pagination, filtering, sorting, and export-readiness built into list endpoints
- backgroundable actions designed around events/jobs instead of long controller transactions

## 10. Security Model

- Sanctum session-based authentication for SPA
- policies and permission middleware for all protected resources
- tenant isolation enforced by scope, validation rules, and controller authorization
- audit logging on critical model events
- soft deletion for high-value records instead of destructive deletes
- configurable password policy and login throttling
- storage abstraction for documents so environments can enforce secure object policies

## 11. Folder Structure

```text
fms-app/
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   ├── Models/
│   │   ├── Policies/
│   │   └── Support/
│   ├── database/
│   └── tests/
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   │   ├── layout/
│   │   │   └── ui/
│   │   ├── modules/
│   │   ├── plugins/
│   │   ├── router/
│   │   ├── stores/
│   │   └── types/
├── docs/
│   ├── reports/
│   ├── architecture.md
│   ├── erd.md
│   ├── local-development.md
│   ├── modules.md
│   └── roadmap.md
└── infra/
    └── docker/
```

## 12. Key Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Tenancy leaks through unscoped queries | Severe | global tenant scope, policies, tenant-aware validation, dedicated tests |
| Role-name coupling in business logic | High | permission-based checks only, treat role templates as seed data |
| Frontend drift from backend contract | High | module-owned pages, typed API layer, route-to-page parity checks |
| Reporting queries degrading OLTP performance | Medium | index strategy, read replica path, export jobs, summary tables later |
| Overgrown controllers/services | Medium | introduce action/service classes when workflows become multi-step |
| Document storage lock-in | Medium | S3-compatible abstraction from day one |

## 13. Implementation Sequence

1. Phase 0: architecture contract, schema direction, repository alignment, local environment plan
2. Phase 1: auth, tenancy, RBAC, settings, audit trail, reusable frontend shell, seed data, tests
3. Phase 2: fleet master data and onboarding
4. Phase 3: operations workflows
5. Phase 4: maintenance and compliance
6. Phase 5: dashboards, analytics, exports
7. Phase 6: hardening, documentation, release readiness
| `tenant_admin` | Organization administrator | All permissions within their tenant |
| `fleet_manager` | Fleet operations manager | vehicles.*, drivers.*, trips.*, maintenance.*, reports.* |
| `dispatcher` | Trip coordination | trips.view, trips.create, trips.update, vehicles.view, drivers.view |
| `driver` | Vehicle operator | trips.view (own), inspections.create, fuel_logs.create |
| `mechanic` | Maintenance technician | work_orders.view, work_orders.update, maintenance.view |
| `viewer` | Read-only access | *.view on permitted modules |

### Permission Check Flow

```
Controller@store
  -> $this->authorize('create', Vehicle::class)
    -> VehiclePolicy@create($user)
      -> $user->hasPermission('vehicles.create')
        -> Check permission_role + role_user pivot tables
          -> Return boolean
```

### Implementation Details

- Permissions are seeded via a `PermissionSeeder` and versioned in code.
- The `HasRolesAndPermissions` trait on the `User` model provides `hasPermission()`, `hasRole()`, and `assignRole()` methods.
- Permissions are cached in Redis per user session to avoid repeated DB lookups.
- Cache is invalidated on role/permission changes via model observers.

---

## 5. API Strategy

### URL Structure

```
/api/v1/{resource}                  # Collection
/api/v1/{resource}/{id}             # Single resource
/api/v1/{resource}/{id}/{relation}  # Nested resource
```

All endpoints are prefixed with `/api/v1`. When breaking changes are needed, a new version (`/api/v2`) is introduced while maintaining the previous version for a deprecation period.

### Response Envelope

Every API response follows a consistent envelope:

```json
{
  "success": true,
  "message": "Vehicles retrieved successfully.",
  "data": { ... },
  "meta": { ... }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "registration_number": ["The registration number is required."]
  }
}
```

See [api-conventions.md](./api-conventions.md) for complete specification.

### Pagination

Two pagination strategies are supported:

| Strategy | Use Case | Parameters |
|---|---|---|
| **Page-based** | UI tables with page numbers | `?page=2&per_page=25` |
| **Cursor-based** | Infinite scroll, large datasets | `?cursor=eyJpZCI6MTAwfQ&per_page=25` |

Default: page-based with `per_page=25`, max `per_page=100`.

### Filtering and Sorting

```
GET /api/v1/vehicles?filter[status]=active&filter[type_id]=3&sort=-created_at,name
```

- Filters use bracket notation: `filter[field]=value`
- Sorting uses comma-separated fields; prefix `-` for descending
- Only whitelisted fields are filterable/sortable (defined per resource)
- Date range filters: `filter[created_after]=2026-01-01&filter[created_before]=2026-03-30`
- Search: `?search=toyota` performs a scoped full-text search

### Rate Limiting

| Scope | Limit |
|---|---|
| Authenticated user | 120 requests/minute |
| Login attempts | 5 requests/minute per IP |
| File uploads | 20 requests/minute |
| Export endpoints | 5 requests/minute |

Rate limit headers are included in every response:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 117
X-RateLimit-Reset: 1711785600
```

---

## 6. Security Model

### Authentication

- **SPA Authentication:** Laravel Sanctum cookie-based authentication for the Vue frontend. Stateful auth with CSRF protection.
- **API Token Authentication:** Bearer token via Sanctum for third-party integrations and mobile clients.
- **Token scoping:** Tokens can be scoped to specific abilities (e.g., `vehicles:read`, `trips:write`).

### CSRF Protection

- All state-changing requests from the SPA require a valid CSRF token.
- The SPA obtains the token via `GET /sanctum/csrf-cookie` before login.
- The token is sent as `X-XSRF-TOKEN` header on subsequent requests.

### Input Validation

- **Form Request classes** validate every incoming request before it reaches the controller.
- **Strict typing:** All request parameters are cast to their expected types.
- **SQL injection prevention:** Eloquent ORM with parameterized queries; raw queries are prohibited unless reviewed and approved.
- **XSS prevention:** All output is escaped by default in Vue templates; `v-html` is prohibited except for sanitized content.
- **File upload validation:** MIME type verification (not just extension), file size limits, virus scanning queue job.

### Audit Trails

Every create, update, and delete operation on a tenant-scoped model is recorded:

```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "user_id": "uuid",
  "auditable_type": "App\\Models\\Vehicle",
  "auditable_id": "uuid",
  "event": "updated",
  "old_values": { "status": "active" },
  "new_values": { "status": "decommissioned" },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "created_at": "2026-03-30T10:15:00Z"
}
```

Implementation via Eloquent Observers on each auditable model, delegating to an `AuditService` that writes to the `audit_logs` table asynchronously via a queued job.

### Additional Security Measures

| Measure | Implementation |
|---|---|
| **Password policy** | Min 12 chars, complexity requirements, bcrypt hashing |
| **Session management** | Configurable timeout, single-session enforcement (optional) |
| **IP allowlisting** | Optional per-tenant IP restrictions |
| **Data encryption** | TLS 1.3 in transit; AES-256 at rest for sensitive fields (license numbers, etc.) |
| **Dependency scanning** | Automated Composer and NPM audit in CI pipeline |
| **CORS** | Strict origin allowlist, no wildcards in production |
| **Content Security Policy** | Strict CSP headers to prevent XSS and injection |
| **Soft deletes** | All models use soft deletes; hard deletion requires superadmin and is audited |

---

## 7. Module List and Phases

| # | Module | Phase | Description |
|---|---|---|---|
| 1 | Tenant Management | 0 | Organization setup, settings, branding |
| 2 | Authentication & Authorization | 0 | Login, registration, RBAC, permissions |
| 3 | User Management | 0 | User CRUD, role assignment, profile |
| 4 | Vehicle Registry | 1 | Vehicle CRUD, types, status lifecycle |
| 5 | Vehicle Documents | 1 | Insurance, registration, permits with expiry tracking |
| 6 | Driver Management | 1 | Driver profiles, license tracking |
| 7 | Driver Documents | 1 | Driver licenses, certifications, medical records |
| 8 | Department Management | 1 | Organizational units for vehicle/driver grouping |
| 9 | Vehicle Assignments | 2 | Assign vehicles to drivers/departments with date ranges |
| 10 | Trip Management | 2 | Trip requests, scheduling, status tracking |
| 11 | Fuel Management | 2 | Fuel log recording, consumption analysis |
| 12 | Odometer Tracking | 2 | Mileage recording, discrepancy detection |
| 13 | Inspection Management | 3 | Pre-trip/post-trip inspections, checklists |
| 14 | Incident Management | 3 | Accident/incident reporting, tracking, resolution |
| 15 | Approval Workflows | 3 | Configurable multi-step approval chains |
| 16 | Maintenance Scheduling | 4 | Preventive maintenance schedules, reminders |
| 17 | Work Orders | 4 | Maintenance task tracking, parts, labor |
| 18 | Service Provider Management | 4 | External vendor registry and performance tracking |
| 19 | Compliance Management | 5 | Regulatory compliance tracking, expiry alerts |
| 20 | Reporting & Analytics | 5 | Dashboards, reports, data export |
| 21 | Notifications | 5 | In-app, email, push notifications |
| 22 | Settings & Configuration | 6 | System settings, preferences, feature flags |

See [roadmap.md](./roadmap.md) for detailed phase planning and [modules.md](./modules.md) for module specifications.

---

## 8. Frontend Architecture

### Directory Structure

```
frontend/src/
  api/                    # Axios instance, interceptors, API client functions
    client.ts             # Base Axios config, response/error interceptors
    modules/              # Per-module API functions
      vehicles.ts
      drivers.ts
      trips.ts
  assets/                 # Static assets (images, fonts)
  components/
    common/               # Shared UI components (Button, Modal, DataTable, etc.)
    layout/               # App shell (Sidebar, Header, Breadcrumbs)
  composables/            # Shared composition functions
    useAuth.ts            # Authentication state and methods
    usePagination.ts      # Pagination logic
    useFilters.ts         # Filter state management
    usePermission.ts      # Permission checking helpers
    useNotification.ts    # Toast/alert notifications
  modules/                # Feature modules (one per system module)
    vehicles/
      views/              # Page-level components
        VehicleList.vue
        VehicleDetail.vue
        VehicleForm.vue
      components/         # Module-specific components
        VehicleStatusBadge.vue
        VehicleCard.vue
      composables/        # Module-specific composables
        useVehicleFilters.ts
      store.ts            # Pinia store for vehicles
      routes.ts           # Route definitions
      types.ts            # TypeScript interfaces
    drivers/
      ...
    trips/
      ...
  router/
    index.ts              # Root router config, navigation guards
    guards.ts             # Auth and permission guards
  stores/                 # Global Pinia stores
    auth.ts               # Auth state (user, token, permissions)
    tenant.ts             # Current tenant info and settings
    ui.ts                 # UI state (sidebar, theme, loading)
  types/                  # Global TypeScript types
    api.ts                # API envelope types
    models.ts             # Shared model interfaces
  utils/                  # Utility functions
    date.ts               # Date formatting
    currency.ts           # Currency formatting
    validation.ts         # Frontend validation helpers
  App.vue
  main.ts
```

### State Management

Each module has its own Pinia store that encapsulates:

- List state (items, pagination metadata, filters, sort order)
- Detail state (current item, loading state)
- CRUD actions that call the module's API functions
- Computed getters for derived state

Global stores (`auth`, `tenant`, `ui`) handle cross-cutting concerns.

**Store pattern:**

```typescript
// modules/vehicles/store.ts
export const useVehicleStore = defineStore('vehicles', () => {
  const items = ref<Vehicle[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const loading = ref(false)
  const filters = ref<VehicleFilters>({})
  const current = ref<Vehicle | null>(null)

  async function fetchList(params?: ListParams) { ... }
  async function fetchOne(id: string) { ... }
  async function create(payload: CreateVehiclePayload) { ... }
  async function update(id: string, payload: UpdateVehiclePayload) { ... }
  async function remove(id: string) { ... }

  return { items, meta, loading, filters, current, fetchList, fetchOne, create, update, remove }
})
```

### Routing and Code Splitting

- Each module exports its own route definitions.
- All module views are lazy-loaded via dynamic imports.
- Navigation guards enforce authentication and permission checks before rendering.

```typescript
// modules/vehicles/routes.ts
export const vehicleRoutes: RouteRecordRaw[] = [
  {
    path: '/vehicles',
    meta: { permission: 'vehicles.view' },
    component: () => import('./views/VehicleList.vue'),
  },
  // ...
]
```

### Component Design

- **Presentational components** in `components/common/` are stateless and emit events.
- **Container components** in module `views/` connect to stores and handle business logic.
- **Composables** extract reusable stateful logic (pagination, filtering, form handling).
- All components are written in `<script setup lang="ts">` with strict TypeScript.

---

## 9. Backend Architecture

### Directory Structure

```
backend/app/
  Console/
    Commands/                 # Artisan commands (e.g., SendExpiryReminders)
  Events/                     # Domain events (VehicleCreated, TripCompleted, etc.)
  Exceptions/
    Handler.php               # Global exception handler with JSON envelope
  Http/
    Controllers/
      Api/
        V1/                   # Versioned API controllers
          VehicleController.php
          DriverController.php
          TripController.php
          ...
    Middleware/
      ResolveTenant.php       # Resolves tenant from authenticated user
      CheckPermission.php     # Validates user has required permission
      ForceJsonResponse.php   # Ensures all responses are JSON
    Requests/                 # Form Request classes
      Vehicle/
        StoreVehicleRequest.php
        UpdateVehicleRequest.php
      Driver/
        StoreDriverRequest.php
        UpdateDriverRequest.php
      ...
    Resources/                # API Resource classes (response transformers)
      VehicleResource.php
      VehicleCollection.php
      DriverResource.php
      ...
  Jobs/                       # Queued jobs
    ProcessAuditLog.php
    SendNotification.php
    GenerateReport.php
  Listeners/                  # Event listeners
  Mail/                       # Mailable classes
  Models/
    Tenant.php
    User.php
    Vehicle.php
    Driver.php
    Trip.php
    ...
  Notifications/              # Notification classes
  Observers/                  # Model observers for audit logging
    VehicleObserver.php
    DriverObserver.php
    TripObserver.php
    ...
  Policies/                   # Authorization policies
    VehiclePolicy.php
    DriverPolicy.php
    TripPolicy.php
    ...
  Services/                   # Business logic services (only where complex)
    TripSchedulingService.php
    MaintenanceSchedulerService.php
    ApprovalWorkflowService.php
    ReportGenerationService.php
    DocumentExpiryService.php
  Traits/
    BelongsToTenant.php
    HasAuditTrail.php
    HasRolesAndPermissions.php
    Filterable.php
```

### Controller Pattern

Controllers are thin and follow a consistent pattern:

```php
class VehicleController extends Controller
{
    public function index(Request $request): VehicleCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()
            ->filter($request->validated())
            ->sort($request->input('sort'))
            ->paginate($request->input('per_page', 25));

        return new VehicleCollection($vehicles);
    }

    public function store(StoreVehicleRequest $request): VehicleResource
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = Vehicle::create($request->validated());

        return new VehicleResource($vehicle);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle->load(['type', 'currentAssignment.driver']));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->fresh());
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete(); // Soft delete

        return response()->json(['success' => true, 'message' => 'Vehicle deleted.']);
    }
}
```

### Form Requests

Every endpoint has a dedicated Form Request that handles:

- Authorization (delegates to policy for simple cases)
- Validation rules
- Custom error messages
- Data preparation (casting, normalization)

```php
class StoreVehicleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:20', 'unique:vehicles,registration_number'],
            'vehicle_type_id'     => ['required', 'uuid', 'exists:vehicle_types,id'],
            'make'                => ['required', 'string', 'max:100'],
            'model'               => ['required', 'string', 'max:100'],
            'year'                => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'color'               => ['nullable', 'string', 'max:50'],
            'vin'                 => ['nullable', 'string', 'size:17', 'unique:vehicles,vin'],
            'status'              => ['required', 'in:active,inactive,maintenance,decommissioned'],
            'acquisition_date'    => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost'    => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

### API Resources

API Resources transform Eloquent models into the JSON response format:

```php
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'registration_number' => $this->registration_number,
            'type'                => new VehicleTypeResource($this->whenLoaded('type')),
            'make'                => $this->make,
            'model'               => $this->model,
            'year'                => $this->year,
            'status'              => $this->status,
            'current_assignment'  => new VehicleAssignmentResource($this->whenLoaded('currentAssignment')),
            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### Services

Services are introduced only when business logic is too complex for a controller (e.g., multi-step workflows, cross-model orchestration). Simple CRUD does not require a service layer.

**Criteria for introducing a service:**
- Logic involves multiple models or external APIs
- Logic has branching/conditional workflows
- Logic needs to be reusable across controllers or commands
- Logic requires transaction management across multiple operations

### Observers for Audit

Model observers capture lifecycle events and dispatch audit log entries:

```php
class VehicleObserver
{
    public function created(Vehicle $vehicle): void
    {
        ProcessAuditLog::dispatch($vehicle, 'created', [], $vehicle->toArray());
    }

    public function updated(Vehicle $vehicle): void
    {
        ProcessAuditLog::dispatch(
            $vehicle,
            'updated',
            $vehicle->getOriginal(),
            $vehicle->getChanges()
        );
    }

    public function deleted(Vehicle $vehicle): void
    {
        ProcessAuditLog::dispatch($vehicle, 'deleted', $vehicle->toArray(), []);
    }
}
```

---

## 10. Infrastructure

### Docker Compose Services

| Service | Image | Purpose |
|---|---|---|
| `app` | Custom PHP 8.3-FPM | Laravel application |
| `nginx` | nginx:alpine | Reverse proxy, serves SPA static files |
| `postgres` | postgres:16-alpine | Primary database |
| `redis` | redis:7-alpine | Cache, queues, sessions, rate limiting |
| `minio` | minio/minio | S3-compatible document/image storage |
| `queue` | Same as `app` | Runs `php artisan queue:work` |
| `scheduler` | Same as `app` | Runs `php artisan schedule:run` |
| `mailpit` | axllent/mailpit | Development email testing (dev only) |

### Environment Configuration

| Variable | Dev | Staging | Production |
|---|---|---|---|
| `APP_DEBUG` | true | false | false |
| `DB_CONNECTION` | pgsql | pgsql | pgsql |
| `CACHE_DRIVER` | redis | redis | redis |
| `QUEUE_CONNECTION` | redis | redis | redis |
| `SESSION_DRIVER` | redis | redis | redis |
| `FILESYSTEM_DISK` | minio | minio | minio/s3 |
| `LOG_CHANNEL` | stack | stack | stack (with external sink) |

### Database Strategy

- **Connection pooling:** PgBouncer in production for connection management.
- **Read replicas:** Supported via Laravel's read/write database configuration.
- **Migrations:** Versioned, idempotent, reviewed in PRs. Destructive migrations require explicit approval.
- **Backups:** Automated daily backups with 30-day retention. Point-in-time recovery enabled.

### Caching Strategy

| Cache | TTL | Invalidation |
|---|---|---|
| User permissions | 1 hour | On role/permission change (observer) |
| Tenant settings | 1 hour | On settings update |
| Vehicle list (per page) | 5 minutes | On any vehicle write |
| Dashboard aggregations | 15 minutes | Scheduled refresh |
| Static lookups (types, statuses) | 24 hours | On deployment |

### Queue Configuration

| Queue | Workers | Purpose |
|---|---|---|
| `default` | 2 | General background jobs |
| `notifications` | 1 | Email, push, in-app notifications |
| `audit` | 1 | Audit log processing |
| `reports` | 1 | Report generation (long-running) |

### Monitoring and Observability

- **Application metrics:** Laravel Telescope (dev), Prometheus metrics endpoint (production)
- **Error tracking:** Sentry integration for exception monitoring
- **Log aggregation:** Structured JSON logs shipped to centralized logging
- **Health checks:** `/api/health` endpoint checking DB, Redis, storage, queue connectivity
- **Uptime monitoring:** External ping monitoring on the health endpoint

---

## Appendix: Decision Records

### ADR-001: Single DB Multi-Tenancy over Schema-per-Tenant

**Context:** Multi-tenant data isolation strategy.
**Decision:** Single database with `tenant_id` column.
**Rationale:** Lower operational complexity, simpler migrations, enables cross-tenant analytics. PostgreSQL row-level security provides defense-in-depth. Partitioning by `tenant_id` is available if single-table performance degrades.

### ADR-002: Permission-Based over Role-Based Checks

**Context:** Authorization granularity.
**Decision:** All authorization checks use permissions; roles are merely permission groups.
**Rationale:** Allows tenants to customize roles without code changes. Avoids hardcoding role names in business logic. Supports fine-grained feature toggling.

### ADR-003: Sanctum over Passport

**Context:** API authentication for a first-party SPA.
**Decision:** Laravel Sanctum.
**Rationale:** Simpler setup, smaller footprint, native SPA cookie auth. OAuth2 complexity (Passport) is unwarranted for a first-party client. Sanctum tokens suffice for any future mobile app or third-party integration.

### ADR-004: PostgreSQL over MySQL

**Context:** Database selection.
**Decision:** PostgreSQL 16.
**Rationale:** Superior handling of UUID primary keys, JSONB support for flexible metadata, partial indexes for performance, better concurrent write performance, advanced full-text search capabilities.

### ADR-005: Services Only When Complex

**Context:** Backend layering strategy.
**Decision:** No mandatory service layer; services introduced only for complex multi-model workflows.
**Rationale:** Avoids unnecessary abstraction for simple CRUD. Controllers remain thin by delegating to Form Requests (validation), Policies (auth), Resources (transformation), and Observers (side effects). Services are reserved for orchestration logic.
