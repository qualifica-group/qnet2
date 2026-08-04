<?php

use App\Http\Controllers\ActivityLog\ActivityLogController;
use App\Http\Controllers\Addresses\AddressController;
use App\Http\Controllers\Attachments\AttachmentController;
use App\Http\Controllers\Attributes\AttributeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ImpersonationController;
use App\Http\Controllers\BusinessFunctions\BusinessFunctionController;
use App\Http\Controllers\BusinessFunctions\BusinessFunctionForSelectController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyForSelectController;
use App\Http\Controllers\CompanySites\CompanySiteController;
use App\Http\Controllers\CompanySites\CompanySiteForSelectController;
use App\Http\Controllers\Config\ConfigController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Export\ExportController;
use App\Http\Controllers\Meta\MetaController;
use App\Http\Controllers\Migration\MassMigrationController;
use App\Http\Controllers\Migration\MigrationController;
use App\Http\Controllers\Migration\MigrationPlanController;
use App\Http\Controllers\Navigation\NavigationController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\OperationalSites\OperationalSiteController;
use App\Http\Controllers\OperationalSites\OperationalSiteForSelectController;
use App\Http\Controllers\PersonalData\PersonalDataController;
use App\Http\Controllers\ProductCategories\AttributeLayoutController;
use App\Http\Controllers\ProductCategories\ProductCategoryController;
use App\Http\Controllers\ReferentTypes\ReferentTypeController;
use App\Http\Controllers\ReferentTypes\ReferentTypeForSelectController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RoleForSelectController;
use App\Http\Controllers\Stats\StatsController;
use App\Http\Controllers\Table\TableController;
use App\Http\Controllers\Table\TableFilterViewController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Backend API-only. Tutte le rotte sono prefissate con /api.
| Health check disponibile su GET /up (configurato in bootstrap/app.php).
|
*/

// PUBLIC application bootstrap (unauthenticated). Serves non-sensitive
// presentation metadata (domain enum options) the frontend needs before login.
// The exposed surface is a fixed server-side allowlist (config/config.php),
// never request input, so no arbitrary class can be reflected. See ADR 0008.
Route::get('config', [ConfigController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // Public password reset flow, rate-limited against abuse.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('me/abilities', [AuthController::class, 'abilities']);

        // Self-service profile write (settings page).
        Route::patch('me', [AuthController::class, 'updateProfile']);

        // Password change is a sensitive credential operation: a stricter limit
        // (throttle:6,1) matches the public password-reset flow above.
        Route::middleware('throttle:6,1')->put('me/password', [AuthController::class, 'updatePassword']);

        // Self-service avatar (settings page): any authenticated user manages
        // their own avatar; no extra permission required.
        Route::post('me/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('me/avatar', [AuthController::class, 'deleteAvatar']);

        // "Login as customer" impersonation (spec 0050): stop the current
        // impersonation session (D-4, 403 if the current token is not one)
        // and the banner state (D-5). Starting a session is gated per-target
        // by UserPolicy::impersonate (see the users/{user}/impersonate route
        // below), so it is NOT declared here.
        Route::post('stop-impersonation', [ImpersonationController::class, 'destroy']);
        Route::get('impersonation', [ImpersonationController::class, 'show']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Level 0 — backend-driven navigation.
    Route::get('navigation', [NavigationController::class, 'index']);

    // Generic, domain-driven DataTable framework (AG Grid SSRM). One pair of
    // endpoints serves every domain; {domain} selects the TableDefinition from
    // the TableRegistry (config/tables.php), unknown domain → 404. See
    // docs/api/0002-generic-tables.md. Authorization (the definition's viewAny)
    // is enforced server-side in TableController on both endpoints.
    Route::get('tables/{domain}/columns', [TableController::class, 'columns']);
    Route::post('tables/{domain}/rows', [TableController::class, 'rows']);
    Route::patch('tables/{domain}/rows/{row}', [TableController::class, 'updateRow']);

    // Distinct values for a single column (Excel-like set filter, spec
    // 0004): allow-list columnId + filterModel keys, cap N, cross-column
    // filters applied. See TableValuesRequest / TableService::distinctValues.
    Route::post('tables/{domain}/values', [TableController::class, 'values']);

    // Generic bulk-delete: best-effort delete of many rows by id. Baseline
    // authorization is the same definition viewAny as every other
    // tables/{domain}/* endpoint; the per-row 'delete' ability and domain
    // delete guards (e.g. last-super-admin) are enforced PER ID by
    // TableBulkDeleteService, never fatal to the rest of the batch.
    Route::post('tables/{domain}/bulk-delete', [TableController::class, 'bulkDelete']);

    // Per-user column preferences (order/width/visibility): self-scoped to the
    // authenticated user, gated by the same definition viewAny. Save upserts a
    // sparse delta; delete resets to the PHP default. See ADR-0004 /
    // docs/api/0003-table-preferences.md.
    Route::post('tables/{domain}/preferences', [TableController::class, 'savePreferences']);
    Route::delete('tables/{domain}/preferences', [TableController::class, 'resetPreferences']);

    // Per-user filter state (the applied AG Grid filterModel): self-scoped,
    // gated by the same definition viewAny, keys restricted to filterable
    // columns. Save upserts the applied model; delete resets it. Mirrors the
    // preferences pair so filters survive a page reload.
    Route::post('tables/{domain}/filters', [TableController::class, 'saveFilters']);
    Route::delete('tables/{domain}/filters', [TableController::class, 'resetFilters']);

    // Saved filter views (spec 0007): named, savable AG Grid filter sets per
    // domain, private or shared. List/create are gated by the same
    // definition viewAny; update/delete are gated by TableFilterViewPolicy
    // (owner only — a shared view is a real cross-user access surface). A
    // bound {filterView} whose domain does not match {domain} 404s (never
    // 403), so views never leak across domains.
    Route::get('tables/{domain}/filter-views', [TableFilterViewController::class, 'index']);
    Route::post('tables/{domain}/filter-views', [TableFilterViewController::class, 'store']);
    Route::put('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'update'])
        ->scopeBindings();
    Route::delete('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'destroy'])
        ->scopeBindings();

    // Import domain routes (spec 0012/0033/0045): extracted to
    // routes/api/imports.php (engineering.md §6, 500-line hard limit) —
    // required from WITHIN this group so every route there still inherits
    // `auth:sanctum`, exactly as if inlined here.
    require __DIR__.'/api/imports.php';

    // Generic, domain-driven export engine (spec 0014), mirroring
    // tables/{domain} / imports/{domain}: one controller serves every domain
    // with a registered TableDefinition (config/tables.php) — no per-domain
    // export definition needed, unlike imports. {domain} resolves via
    // TableRegistry (unknown → 404). Authorization (the definition's
    // modelClass() `{domain}.export` ability) is enforced server-side in
    // ExportController on every action; a bound {exportRun} that does not
    // belong to the actor OR whose resource != {domain} 404s.
    Route::get('exports/{domain}/{exportRun}', [ExportController::class, 'show'])->scopeBindings();
    Route::get('exports/{domain}/{exportRun}/download', [ExportController::class, 'download'])->scopeBindings();

    Route::post('exports/{domain}', [ExportController::class, 'store']);

    // Generic, registry-driven external-data migration engine (spec 0013),
    // mirroring tables/{domain} / imports/{domain}: one controller serves
    // every source; {source} resolves the MigrationSource (config/
    // migrations.php), unknown → 404. Authorization is a SINGLE hard gate for
    // the whole group — the `super-admin` middleware alias (EnsureSuperAdmin,
    // fail-closed via UserService::PRIVILEGED_ROLE): 401 anonymous, 403
    // non-super-admin. A bound {migrationRun} that does not belong to the
    // actor OR whose source != {source} 404s (never 403).
    Route::middleware('super-admin')->group(function () {
        Route::get('migrations', [MigrationController::class, 'index']);

        // Mass-import plan (spec 0046): the app-wide singleton ordering which
        // sources the "Import all" run executes. Declared before the {source}
        // routes — `plan` is a fixed segment, never a source key.
        Route::get('migrations/plan', [MigrationPlanController::class, 'show']);
        Route::put('migrations/plan', [MigrationPlanController::class, 'update']);

        // "Import all" (spec 0046): run the plan's enabled sources in order,
        // stopping at the first failure. `mass-runs` is a fixed segment, so it
        // never collides with the {source} routes below.
        Route::post('migrations/mass-runs', [MassMigrationController::class, 'store']);
        Route::get('migrations/mass-runs/{massMigrationRun}', [MassMigrationController::class, 'show']);

        Route::get('migrations/{source}/columns', [MigrationController::class, 'columns']);
        Route::get('migrations/{source}/runs/{migrationRun}', [MigrationController::class, 'run']);

        Route::get('migrations/{source}/preview', [MigrationController::class, 'preview']);

        Route::post('migrations/{source}/import', [MigrationController::class, 'import']);
    });

    // Centralized, resource-driven authorization metadata (spec 0004): one
    // generic endpoint per resource, registry-driven (config/authorization.php),
    // mirroring the tables/{domain} pattern. Authorization ({resource}.viewAny)
    // is enforced server-side in MetaController; unknown {resource} → 404.
    Route::get('meta/{resource}', [MetaController::class, 'show']);

    // Generic, domain-driven module statistics panel (spec 0026), same
    // registry pattern: {domain} resolves the StatsDefinition
    // (config/stats.php), unknown → 404. Authorization (the definition's
    // `{domain}.viewAny`) is enforced server-side in StatsController.
    Route::get('stats/{domain}', StatsController::class);

    // Role form authorization catalogues (specs 0006, 0076): fields() matrix
    // + the Area > Module permission-explorer tree.
    require __DIR__.'/api/authorization.php';

    // Generic, resource-driven aggregated Activity Log (spec 0034): one route
    // serves every resource registered in config/activity-log.php (v1:
    // `users`). Unknown {resource} or missing {id} → 404 (ActivityLogRegistry
    // / findOrFail); authorization ({resource}.viewActivity AND the model's
    // own Policy `view`) is enforced server-side in ActivityLogController. No
    // throttle (decision 2026-07-15: only auth endpoints are rate-limited).
    Route::get('activity-log/{resource}/{id}', [ActivityLogController::class, 'index']);

    // Users CRUD backing the table row-actions (view/edit/delete) + create.
    // Authorization (users.view/create/update/delete) is enforced server-side
    // in UserController via the UserPolicy on every endpoint.
    // Minimal searchable/paginated user list for entity-backed selects
    // (for-select standard, ADR 0011). Declared ABOVE users/{user} so the
    // literal `for-select` segment wins over the bound {user} wildcard.
    // The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
    Route::get('users/for-select', UserForSelectController::class);

    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    // Admin avatar management on the user form. Gated by `users.update`
    // server-side (see UserController), same as editing the user.
    Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
    Route::delete('users/{user}/avatar', [UserController::class, 'deleteAvatar']);

    // "Login as customer" impersonation (spec 0050): issues a NEW Sanctum
    // token for the TARGET user (D-1). Gated by UserPolicy::impersonate
    // (users.impersonate + no escalation on a super-admin target, 403);
    // self/inactive/nesting are enforced service-side (422) — see
    // ImpersonationService.
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store']);

    // Roles CRUD backing the table row-actions (view/edit/delete) + create.
    // Authorization (roles.view/create/update/delete) is enforced server-side
    // in RoleController via the RolePolicy on every endpoint. The permission
    // catalogue shown in the role form is served by the generic table config
    // (GET /api/tables/roles/columns → the `permissions` set options), so no
    // dedicated permissions endpoint is needed.
    // Minimal searchable/paginated role list for entity-backed selects
    // (for-select standard, ADR 0011) — feeds the user-form role multi-select.
    // Declared ABOVE roles/{role} so the literal `for-select` segment wins
    // over the bound {role} wildcard. Gated by roles.viewAny server-side in
    // RoleForSelectController; options are actor-scoped to assignable roles.
    Route::get('roles/for-select', RoleForSelectController::class);

    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    // Business functions CRUD backing the table row-actions (view/edit/delete)
    // + create (spec 0010). Authorization (business-functions.view/create/
    // update/delete) is enforced server-side in BusinessFunctionController via
    // BusinessFunctionPolicy on every endpoint.
    // Minimal searchable/paginated business-function list for entity-backed
    // selects (for-select standard, ADR 0011), feeding the spec 0015 user-
    // form "function" select. Declared ABOVE business-functions/{businessFunction}
    // so the literal `for-select` segment wins over the bound wildcard.
    // The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
    Route::get('business-functions/for-select', BusinessFunctionForSelectController::class);

    Route::get('business-functions/{businessFunction}', [BusinessFunctionController::class, 'show']);
    Route::post('business-functions', [BusinessFunctionController::class, 'store']);
    Route::match(['put', 'patch'], 'business-functions/{businessFunction}', [BusinessFunctionController::class, 'update']);
    Route::delete('business-functions/{businessFunction}', [BusinessFunctionController::class, 'destroy']);

    // Companies CRUD backing the table row-actions (view/edit/delete) + create
    // (spec 0010). Authorization (companies.view/create/update/delete) is
    // enforced server-side in CompanyController via CompanyPolicy on every
    // endpoint.
    // Minimal searchable/paginated company list for entity-backed selects
    // (for-select standard, ADR 0011), feeding the spec 0015 user-form
    // "company" select. Declared ABOVE companies/{company} so the literal
    // `for-select` segment wins over the bound wildcard. The only gate is
    // auth:sanctum (ADR 0011, amended 2026-07-31).
    Route::get('companies/for-select', CompanyForSelectController::class);

    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update']);
    Route::delete('companies/{company}', [CompanyController::class, 'destroy']);

    // Operational sites CRUD backing the table row-actions (view/edit/delete)
    // + create (spec 0011). Authorization (operational-sites.view/create/
    // update/delete) is enforced server-side in OperationalSiteController via
    // OperationalSitePolicy on every endpoint.
    // Minimal searchable/paginated operational-site list for entity-backed
    // selects (for-select standard, ADR 0011), feeding the spec 0015 user-
    // form "site" select. Declared ABOVE operational-sites/{operationalSite}
    // so the literal `for-select` segment wins over the bound wildcard.
    // The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
    Route::get('operational-sites/for-select', OperationalSiteForSelectController::class);

    Route::get('operational-sites/{operationalSite}', [OperationalSiteController::class, 'show']);
    Route::post('operational-sites', [OperationalSiteController::class, 'store']);
    Route::match(['put', 'patch'], 'operational-sites/{operationalSite}', [OperationalSiteController::class, 'update']);
    Route::delete('operational-sites/{operationalSite}', [OperationalSiteController::class, 'destroy']);

    // Referent types CRUD (spec 0016): full-managed lookup feeding the
    // `referents` module's "Referent type" select. Authorization
    // (referent-types.view/create/update/delete) is enforced server-side in
    // ReferentTypeController via ReferentTypePolicy on every endpoint.
    // Minimal searchable/paginated referent-type list for entity-backed
    // selects (for-select standard, ADR 0011), feeding the referent-form
    // "Referent type" select. Declared ABOVE referent-types/{referentType}
    // so the literal `for-select` segment wins over the bound wildcard.
    // The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
    Route::get('referent-types/for-select', ReferentTypeForSelectController::class);

    Route::get('referent-types/{referentType}', [ReferentTypeController::class, 'show']);
    Route::post('referent-types', [ReferentTypeController::class, 'store']);
    Route::match(['put', 'patch'], 'referent-types/{referentType}', [ReferentTypeController::class, 'update']);
    Route::delete('referent-types/{referentType}', [ReferentTypeController::class, 'destroy']);

    // Sources / Tags / Sectors: standalone lookup-table CRUD, extracted
    // into routes/api/lookups.php (file-size split, engineering.md §6) so
    // this file stays within the 500-line hard limit. Required INSIDE this
    // auth:sanctum group so every route there inherits the same context.
    require __DIR__.'/api/lookups.php';

    // Referents CRUD (spec 0016) + rewards lazy detail (spec 0059):
    // extracted into routes/api/referents.php (file-size split,
    // engineering.md §6) so this file stays within the 500-line hard limit.
    // Required INSIDE this auth:sanctum group so every route there inherits
    // the same context.
    require __DIR__.'/api/referents.php';

    // Reward inline status edit (spec 0060 §4): extracted into
    // routes/api/rewards.php (file-size split, engineering.md §6). Required
    // INSIDE this auth:sanctum group so this route inherits the same context.
    require __DIR__.'/api/rewards.php';

    // Registries CRUD (spec 0020, "Anagrafiche"): extracted into
    // routes/api/registries.php (file-size split, engineering.md §6) so this
    // file stays within the 500-line hard limit. Required INSIDE this
    // auth:sanctum group so every route there inherits the same context.
    require __DIR__.'/api/registries.php';
    require __DIR__.'/api/projects.php'; // Project statuses / Projects / Campaigns CRUD (spec 0023)
    require __DIR__.'/api/leads.php'; // Leads CRUD (spec 0024) + opportunity-defaults (spec 0040)
    require __DIR__.'/api/opportunities.php'; // Opportunities CRUD (spec 0040)
    require __DIR__.'/api/quotes.php'; // Quotes CRUD (spec 0065, MT-05)
    require __DIR__.'/api/contracts.php'; // Contracts show/update + domain actions (spec 0072)
    require __DIR__.'/api/commission-configurations.php';
    require __DIR__.'/api/request-management.php'; // Work-panel show/update (spec 0049)
    require __DIR__.'/api/notes.php'; // Agnostic collaborative notes (spec 0052)
    require __DIR__.'/api/field-change-requests.php'; // Generic field-change-request lifecycle (spec 0078)
    // Attributes CRUD (spec 0017): the global, reusable dynamic-attribute
    // catalogue assignable to product categories. Authorization
    // (attributes.view/create/update/delete) is enforced server-side in
    // AttributeController via AttributePolicy on every endpoint.
    Route::get('attributes/{attribute}', [AttributeController::class, 'show']);
    Route::post('attributes', [AttributeController::class, 'store']);
    Route::match(['put', 'patch'], 'attributes/{attribute}', [AttributeController::class, 'update']);
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);

    // Custom field definitions CRUD (spec 0021): routes/api/custom-fields.php
    // (file-size split, engineering.md §6), required for the same context.
    require __DIR__.'/api/custom-fields.php';

    // Product categories: CRUD + the dedicated tree view + the product form's
    // effective-attributes lookup (spec 0017). `tree` and
    // `{productCategory}/effective-attributes` are declared ABOVE the plain
    // `{productCategory}` show route so their literal segments win over the
    // bound wildcard. Authorization (product-categories.view/create/update/
    // delete, plus the effective-attributes cross-resource rule) is enforced
    // server-side in ProductCategoryController via ProductCategoryPolicy.
    Route::get('product-categories/tree', [ProductCategoryController::class, 'tree']);
    Route::get('product-categories/{productCategory}/effective-attributes', [ProductCategoryController::class, 'effectiveAttributes']);

    // Attribute layout configurator (spec 0062): GET/PUT the category's
    // configured (context, form_mode) layout blob. Declared ABOVE the plain
    // `{productCategory}` show route, same literal-segment-wins-over-wildcard
    // reasoning as `effective-attributes` above. Authorization (product-
    // categories.view/update, no new permission) is enforced server-side in
    // AttributeLayoutController via ProductCategoryPolicy.
    Route::get('product-categories/{productCategory}/attribute-layouts', [AttributeLayoutController::class, 'show']);
    Route::put('product-categories/{productCategory}/attribute-layouts', [AttributeLayoutController::class, 'update']);

    // Bulk reparenting (spec 0063): move many categories under one parent, or
    // to the root. Literal segment declared ABOVE the bound wildcard, same
    // reasoning as the routes above. Authorization is per targeted category
    // (product-categories.update) inside the controller.
    Route::post('product-categories/bulk-move', [ProductCategoryController::class, 'bulkMove']);

    Route::get('product-categories/{productCategory}', [ProductCategoryController::class, 'show']);
    Route::post('product-categories', [ProductCategoryController::class, 'store']);
    Route::match(['put', 'patch'], 'product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
    Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

    // Products CRUD + for-select (spec 0017): routes/api/products.php
    // (file-size split, engineering.md §6), required for the same context.
    require __DIR__.'/api/products.php';

    // Company sites CRUD + logo/set-default (spec 0020). Authorization
    // (company-sites.view/create/update/delete) is enforced server-side in
    // CompanySiteController via CompanySitePolicy on every endpoint.
    // Minimal searchable/paginated company-site list for entity-backed
    // selects (for-select standard, ADR 0011, spec 0040 — feeds the
    // Opportunity form's "company site" select, optionally filtered by
    // `company_id`). Declared ABOVE company-sites/{companySite} so the
    // literal `for-select` segment wins over the bound wildcard, mirroring
    // every other for-select precedent. The only gate is auth:sanctum (ADR
    // 0011, amended 2026-07-31).
    Route::get('company-sites/for-select', CompanySiteForSelectController::class);

    // The `set-default`/`logo` literal segments are declared ABOVE the plain
    // show/update/destroy routes, mirroring the for-select precedent, so a
    // literal segment never risks losing to the bound {companySite} wildcard.
    Route::post('company-sites/{companySite}/set-default', [CompanySiteController::class, 'setDefault']);
    Route::post('company-sites/{companySite}/logo', [CompanySiteController::class, 'uploadLogo']);
    Route::delete('company-sites/{companySite}/logo', [CompanySiteController::class, 'deleteLogo']);

    Route::get('company-sites/{companySite}', [CompanySiteController::class, 'show']);
    Route::post('company-sites', [CompanySiteController::class, 'store']);
    Route::match(['put', 'patch'], 'company-sites/{companySite}', [CompanySiteController::class, 'update']);
    Route::delete('company-sites/{companySite}', [CompanySiteController::class, 'destroy']);

    // In-app user notifications (Laravel native `database` channel). Every
    // endpoint is self-scoped by construction to the authenticated user's own
    // notifications (auth()->user()->notifications()), so a foreign/unknown id
    // resolves to 404; authorization is ownership, not a Spatie permission or a
    // Policy (see ADR-0005 / docs/api/0004-notifications.md).
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Polymorphic file attachments: list + upload + metadata + authenticated
    // download/inline view + delete. Any model can own attachments
    // (HasAttachments trait); the attachable target is restricted to
    // config('attachments.attachable_types'). Authorization
    // (attachments.viewAny/create/view/delete) is enforced server-side in
    // AttachmentController via the AttachmentPolicy on every endpoint. The
    // binary is never served statically — download/view stream through the
    // authorized endpoints only.
    Route::get('attachments', [AttachmentController::class, 'index']);
    Route::post('attachments', [AttachmentController::class, 'store']);
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show']);
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::get('attachments/{attachment}/view', [AttachmentController::class, 'view']);
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // PersonalData / Contact / Address — the reusable, polymorphic personal-data
    // module (ADR 0006). Each entity is attached to a polymorphic owner resolved
    // through a config allowlist (config/personal_data.php): the alias is the
    // security boundary, so a request can never target an arbitrary class.
    // Authorization ({resource}.view/create/update/delete) is enforced
    // server-side in each controller via its Policy on every endpoint.
    //
    // PRIVACY: these endpoints expose personal data (fiscal identifiers, birth
    // date, geolocation) and MUST NOT be released before Legal sign-off
    // (purpose, lawful basis, retention, erasure) — see ADR 0006 and the backend
    // handoff. They are wired but pending that gate.
    Route::get('personal-data', [PersonalDataController::class, 'index']);
    Route::get('personal-data/{personalData}', [PersonalDataController::class, 'show']);
    Route::post('personal-data', [PersonalDataController::class, 'store']);
    Route::match(['put', 'patch'], 'personal-data/{personalData}', [PersonalDataController::class, 'update']);
    Route::delete('personal-data/{personalData}', [PersonalDataController::class, 'destroy']);

    Route::get('contacts/{contact}', [ContactController::class, 'show']);
    Route::post('contacts', [ContactController::class, 'store']);
    Route::match(['put', 'patch'], 'contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy']);

    Route::get('addresses/{address}', [AddressController::class, 'show']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::match(['put', 'patch'], 'addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

    // Geo reference lookups (ADR 0010): routes/api/geo.php (file-size split,
    // engineering.md §6), required for the same context.
    require __DIR__.'/api/geo.php';

    // Document layouts CRUD + for-select + variable catalogue (spec 0069):
    // routes/api/document-layouts.php (file-size split, engineering.md §6),
    // required for the same context.
    require __DIR__.'/api/document-layouts.php';
});
