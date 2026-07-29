import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { moduleI18nNamespace } from '@/features/modules/i18n-namespace'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { useModuleOpenMode } from '@/features/modules/use-module-open-mode'
import { OPEN_MODE_MODAL, type ModuleCreateParams, type OpenMode } from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'

/**
 * Query string for the `page`-mode create deep-link (spec 0045). `undefined`/
 * empty `params` yields `''` so the caller navigates to a bare `${basePath}/new`
 * (AC-003), never a trailing `?`. Values are cast to `string` before handing
 * them to `URLSearchParams` since `ModuleCreateParams` also allows `number`.
 */
function buildCreateQueryString(params?: ModuleCreateParams): string {
  if (!params || Object.keys(params).length === 0) {
    return ''
  }
  const stringValues = Object.entries(params).map(([key, value]) => [key, String(value)])
  return new URLSearchParams(stringValues).toString()
}

/** Which sheet (if any) is currently open and for which row — same shape every `*-table.tsx` used inline before the rewire. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create'; params?: ModuleCreateParams }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }
  | { kind: 'duplicate'; row: TableRow }

export interface UseModuleOpenerOptions {
  /**
   * Called after a successful create/update, modal mode only (the caller's
   * own grid refresh / stats invalidation — `useModuleOpener` itself is
   * domain-agnostic and owns neither). Page mode never calls it: the user
   * has navigated away from the grid, there is nothing to refresh.
   */
  onSaved?: () => void
  /**
   * Overrides the mode resolved from the module's default/the user's open-mode
   * preference (spec 0067 D-3). Used by embedded contexts — e.g. the
   * Opportunity Quotes panel — where the parent record must never be
   * abandoned, so create/view/edit/duplicate always mount the Sheet
   * regardless of the target module's `defaultMode` or the user's stored
   * preference. Omitted by every other call site, which keeps resolving the
   * mode via `useModuleOpenMode` exactly as before (AC-064).
   */
  forceMode?: OpenMode
}

export interface UseModuleOpenerResult {
  /**
   * Opens the create form with no initial data. Declared with ZERO parameters
   * on purpose: 24 call sites pass it straight to a click handler
   * (`onClick={openCreate}`), where React supplies a `MouseEvent` as the first
   * argument. A `(params?: ModuleCreateParams) => void` signature — or an
   * overload hiding one — would let that event through as `params`, and in
   * page mode it would be serialized into the query string
   * (`/new?_reactName=onClick&type=click&...`). Ignoring extra arguments is
   * the behaviour those call sites have always relied on; keep it.
   */
  openCreate: () => void
  /** Opens the create form seeding the target module's form with `params` (spec 0045). */
  openCreateWith: (params: ModuleCreateParams) => void
  openView: (row: TableRow) => void
  openEdit: (row: TableRow) => void
  /** Opens the create form pre-filled from `row` (row action "duplicate"): the source is still fetched fresh, submit still goes through the create path. */
  openDuplicate: (row: TableRow) => void
  /** The modal Sheet when the effective mode (resolved, or `forceMode` when set) is `'modal'`, `null` in `'page'` mode. Render it once, anywhere in the table adapter's tree. */
  sheet: ReactNode
}

/**
 * Domain-generic replacement for the `SheetState`/`navigate` pair every
 * `*-table.tsx` used to hard-code (spec 0042). Resolves the module's
 * effective open mode (`useModuleOpenMode`, overridable per call site via
 * `options.forceMode`, spec 0067 D-3) and instrades view/edit/create
 * accordingly: `'modal'` mounts the registry's `DetailScreen`/`FormScreen`
 * inside an owned `<Sheet>` (AC-011/018, same `sheet-width:${domain}` layout
 * key as before); `'page'` navigates to the module's deep-link routes
 * (AC-011/019) and never mounts a Sheet.
 */
export function useModuleOpener(domain: string, options: UseModuleOpenerOptions = {}): UseModuleOpenerResult {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const resolvedMode = useModuleOpenMode(domain)
  const entry = getModuleRegistryEntry(domain)
  // Every module-specific path below the registry lookup is a stable, known
  // route/component; this "basePath" fallback only exists so hooks stay
  // unconditional (rules-of-hooks) up to the invariant check at the bottom.
  const basePath = entry?.basePath ?? ''
  // Sheet titles/subtitles are keyed under the module's camelCase i18n
  // namespace, while `domain` is the kebab-case slug (spec 0042).
  const ns = moduleI18nNamespace(domain)

  const { onSaved, forceMode } = options
  // `forceMode` short-circuits the resolved mode for the caller (spec 0067
  // D-3). Applied here rather than inside `resolveOpenMode`/`useModuleOpenMode`
  // so those stay pure functions of the user's actual preference — the
  // embedded panel's override is a concern of THIS call site, not of "what
  // does the user prefer" (engineering.md §1.3: no speculative parameter on
  // the pure resolver for a single caller).
  const mode = forceMode ?? resolvedMode
  const [sheetState, setSheetState] = useState<SheetState>({ kind: 'none' })

  const closeSheet = useCallback(() => setSheetState({ kind: 'none' }), [])

  const openCreateWith = useCallback(
    (params?: ModuleCreateParams) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'create', params })
      } else {
        const query = buildCreateQueryString(params)
        void navigate(query ? `${basePath}/new?${query}` : `${basePath}/new`)
      }
    },
    [mode, navigate, basePath],
  )

  // Wrapper with no declared parameters: it swallows whatever a click handler
  // passes (see `openCreate` on the result interface) instead of forwarding it
  // as `params`.
  const openCreate = useCallback(() => openCreateWith(undefined), [openCreateWith])

  const openView = useCallback(
    (row: TableRow) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'view', row })
      } else {
        void navigate(`${basePath}/${row.id}`)
      }
    },
    [mode, navigate, basePath],
  )

  const openEdit = useCallback(
    (row: TableRow) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'edit', row })
      } else {
        void navigate(`${basePath}/${row.id}/edit`)
      }
    },
    [mode, navigate, basePath],
  )

  const openDuplicate = useCallback(
    (row: TableRow) => {
      if (mode === OPEN_MODE_MODAL) {
        setSheetState({ kind: 'duplicate', row })
      } else {
        void navigate(`${basePath}/${row.id}/duplicate`)
      }
    },
    [mode, navigate, basePath],
  )

  const handleSaved = useCallback(() => {
    closeSheet()
    onSaved?.()
  }, [closeSheet, onSaved])

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  if (!entry) {
    throw new Error(`useModuleOpener: "${domain}" is not registered in the module registry.`)
  }

  const { DetailScreen, FormScreen } = entry

  const sheet =
    mode === OPEN_MODE_MODAL ? (
      <Sheet open={sheetState.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${domain}`}>
          {sheetState.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t(`${ns}.detail.title`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.detail.subtitle`)}</SheetDescription>
              </SheetHeader>
              <DetailScreen
                id={sheetState.row.id}
                onEdit={() => setSheetState({ kind: 'edit', row: sheetState.row })}
              />
            </>
          )}

          {sheetState.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t(`${ns}.form.createTitle`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.form.createSubtitle`)}</SheetDescription>
              </SheetHeader>
              <FormScreen
                mode={{ type: 'create', params: sheetState.params }}
                onSuccess={handleSaved}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheetState.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t(`${ns}.form.editTitle`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.form.editSubtitle`)}</SheetDescription>
              </SheetHeader>
              <FormScreen
                mode={{ type: 'edit', id: sheetState.row.id }}
                onSuccess={handleSaved}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheetState.kind === 'duplicate' && (
            <>
              <SheetHeader>
                <SheetTitle>{t(`${ns}.form.createTitle`)}</SheetTitle>
                <SheetDescription>{t(`${ns}.form.createSubtitle`)}</SheetDescription>
              </SheetHeader>
              <FormScreen
                mode={{ type: 'duplicate', id: sheetState.row.id }}
                onSuccess={handleSaved}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    ) : null

  return { openCreate, openCreateWith, openView, openEdit, openDuplicate, sheet }
}
