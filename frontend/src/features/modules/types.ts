import type { ComponentType } from 'react'

/** Where a module's screens (view/create/edit) are mounted. */
export const OPEN_MODE_MODAL = 'modal' as const
export const OPEN_MODE_PAGE = 'page' as const

export type OpenMode = typeof OPEN_MODE_MODAL | typeof OPEN_MODE_PAGE

/** The 3-state preference a user picks in settings (spec 0042). */
export const PREFERENCE_MODE_MODAL = 'modal' as const
export const PREFERENCE_MODE_PAGE = 'page' as const
export const PREFERENCE_MODE_CUSTOM = 'custom' as const

export type ModuleOpenPreferenceMode =
  | typeof PREFERENCE_MODE_MODAL
  | typeof PREFERENCE_MODE_PAGE
  | typeof PREFERENCE_MODE_CUSTOM

/**
 * Per-user preference, persisted as a single JSON column on `users` and
 * exposed/saved via GET/PATCH `/auth/me` (spec 0042). `overrides` is only
 * consulted when `mode === 'custom'`; it stays intact (never trimmed) when
 * switching to `'modal'`/`'page'` so no per-module choice is silently lost
 * (AC-016).
 */
export interface ModuleOpenPreferences {
  mode: ModuleOpenPreferenceMode
  overrides: Record<string, OpenMode>
}

/** Server contract: a null column serializes to this (never `null` on the wire). */
export const DEFAULT_MODULE_OPEN_PREFERENCES: ModuleOpenPreferences = {
  mode: PREFERENCE_MODE_CUSTOM,
  overrides: {},
}

/**
 * Initial values for a create form (spec 0045), e.g. `{ lead_id: 7 }` when
 * converting a Lead into an Opportunity. Scalar-only so it round-trips
 * through a query string without loss: the modal path can hand over a
 * `number`, the page path always produces a `string` once parsed back from
 * the URL — adapters must normalize before use (see `opportunity-screens.tsx`).
 */
export type ModuleCreateParams = Record<string, string | number>

/** Which entity (create vs edit vs duplicate) a `FormScreen` renders. */
export type ModuleFormScreenMode =
  | { type: 'create'; params?: ModuleCreateParams }
  | { type: 'edit'; id: number }
  /** Create form pre-filled from the source row `id` (row action "duplicate"): still submits via the create path. */
  | { type: 'duplicate'; id: number }

export interface ModuleDetailScreenProps {
  id: number
  /**
   * Opens the module's edit surface in the host's own way (sheet swap in
   * modal mode, navigation in page mode). Absent when the host offers no
   * edit affordance.
   */
  onEdit?: () => void
}

export interface ModuleFormScreenProps {
  mode: ModuleFormScreenMode
  /** Called after a successful create/update with the saved entity's id. */
  onSuccess: (id: number) => void
  onCancel: () => void
}

/**
 * One registrable module (spec 0042). `domain` is the same kebab-case slug
 * used by `config/tables.php`/`TableRegistry` server-side. `DetailScreen`/
 * `FormScreen` are content-only adapters (fetch + the module's existing
 * presentational view/form, no page chrome): reused as-is inside the modal
 * Sheet (`useModuleOpener`) and inside the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`), which own the page chrome around
 * them. The module's list page (`${basePath}`) is NOT part of the registry:
 * AC-012 only requires `new`/`:id`/`:id/edit` to be generated, so the
 * existing list route/page stays declared manually in `router.tsx`, same as
 * every other module not yet in the registry.
 */
export interface ModuleRegistryEntry {
  domain: string
  basePath: string
  /** The module's native mount today — preserved bit-for-bit when the user has no preference (AC-010). */
  defaultMode: OpenMode
  /** i18n key under `navigation.*`. */
  labelKey: string
  /**
   * When `false`, the generic `new`/`:id`/`:id/edit` routes are NOT generated
   * for this module: it keeps its own bespoke dedicated pages (declared by hand
   * in `router.tsx`), e.g. registries/referents/products whose detail pages own
   * extra behaviour (breadcrumb titles, spec 0022) the generic host does not
   * replicate. `'page'` mode then navigates to those existing routes, while
   * `'modal'` mode still mounts the adapters below in a Sheet. Defaults to
   * `true` (generate the routes) when omitted.
   */
  generateRoutes?: boolean
  DetailScreen: ComponentType<ModuleDetailScreenProps>
  FormScreen: ComponentType<ModuleFormScreenProps>
  /**
   * When `true`, the `DetailScreen` renders the edit affordance itself and
   * the generic page header omits its own Edit button, so the action is not
   * duplicated. Defaults to `false`.
   */
  detailOwnsEditAction?: boolean
  /**
   * When `true`, the `FormScreen` renders its own visible heading (title,
   * subtitle and the save/cancel actions on one row) and the generic hosts
   * omit theirs: the dedicated page drops its header outright, the Sheet
   * keeps its `SheetHeader` `sr-only` — Radix still needs a title/description
   * for the dialog, it just must not be shown twice. Same shape as
   * `detailOwnsEditAction`, and the same `sr-only` treatment the Sheet's
   * `view` branch already gives a `DetailScreen` that owns its bar. Defaults
   * to `false`.
   */
  formOwnsHeader?: boolean
  /**
   * Optional extra actions rendered as a real child component in the generic
   * detail page's header, between "Back" and "Edit" (e.g. leads' "Create/Go
   * to opportunity" CTA) — mounted via JSX so its own hooks run correctly,
   * never called as a plain function. Any fetch it performs shares the same
   * detail query key/cache as `DetailScreen`, so React Query dedupes it
   * instead of firing a second request. Omitted by every module with no such
   * extra.
   */
  DetailPageActions?: ComponentType<ModuleDetailScreenProps>
}
