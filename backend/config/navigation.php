<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backend-driven navigation (Level 0)
    |--------------------------------------------------------------------------
    |
    | Declarative menu tree. NavigationService filters these items by the
    | current user's permissions (Spatie). The frontend just renders what it
    | receives — it never decides visibility.
    |
    | Item shape:
    |   - key:        stable identifier (string)
    |   - label:      i18n key resolved by the frontend (e.g. "navigation.users")
    |   - icon:       icon name the frontend maps to a component (nullable)
    |   - route:      frontend route (nullable for pure groups)
    |   - permission: required permission, or null = any authenticated user
    |   - role:       (optional, spec 0013) required Spatie role name — item is
    |                 visible ONLY to a user holding it (in ADDITION to the
    |                 permission check above, if any). Omit for ordinary items;
    |                 never used by SyncPermissions (it has nothing to do with
    |                 the permission catalogue).
    |   - type:       'item' (default) or 'section'. A 'section' is a labeled
    |                 separator: the frontend renders its children as flat,
    |                 sibling links under a group label — NOT as a collapsible
    |                 parent. Omit for ordinary items.
    |   - children:   nested items (optional)
    |
    | A group/section (route = null) with no visible children is removed
    | automatically by NavigationService.
    |
    | NOTE: hiding a menu item is UX only. Every endpoint must still enforce
    | authorization server-side via Policies/permissions.
    |
    | Split into config/navigation/*.php — one file per top-level item/section
    | (engineering.md §6 file-size budget). The order follows the legacy QNet
    | macro-areas (spec 0318); NavigationService reads config('navigation.items')
    | unaware of the split.
    |
    */

    'items' => [
        require __DIR__.'/navigation/dashboard.php',
        require __DIR__.'/navigation/tasks.php',
        require __DIR__.'/navigation/time-entries.php',
        require __DIR__.'/navigation/absences.php',
        require __DIR__.'/navigation/commercial.php',
        require __DIR__.'/navigation/operations.php',
        require __DIR__.'/navigation/registries.php',
        require __DIR__.'/navigation/warehouse.php',
        require __DIR__.'/navigation/accounting.php',
        require __DIR__.'/navigation/personnel.php',
        require __DIR__.'/navigation/configuration.php',
        require __DIR__.'/navigation/develop.php',
    ],

];
