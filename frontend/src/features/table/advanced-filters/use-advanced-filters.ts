import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSaveTableFilters } from '@/features/table/use-table-filters'
import type {
  AdvancedFilterDescriptor,
  AdvancedFilterValue,
  AdvancedFilterValues,
} from '@/features/table/advanced-filters/types'

/** A range value has neither bound set. */
function isEmptyRange(value: { from?: unknown; to?: unknown }): boolean {
  return value.from === undefined && value.to === undefined
}

/** Whether a draft value counts as "not filled in" for required-gating and dependency checks. */
function isEmptyValue(value: AdvancedFilterValue | undefined): boolean {
  if (value === undefined || value === null) {
    return true
  }
  if (typeof value === 'string') {
    return value.trim() === ''
  }
  if (Array.isArray(value)) {
    return value.length === 0
  }
  if (typeof value === 'object') {
    return isEmptyRange(value)
  }
  // number/boolean: 0 and false are real, meaningful answers, never "empty".
  return false
}

function sameValue(a: AdvancedFilterValue | undefined, b: AdvancedFilterValue | undefined): boolean {
  return JSON.stringify(a ?? null) === JSON.stringify(b ?? null)
}

function buildDefaults(descriptors: AdvancedFilterDescriptor[]): AdvancedFilterValues {
  const defaults: AdvancedFilterValues = {}
  for (const descriptor of descriptors) {
    defaults[descriptor.name] = descriptor.defaultValue ?? null
  }
  return defaults
}

/**
 * Filters `values` down to the entries that actually deviate from their
 * descriptor's default (or, absent one, from empty) — the "active" subset
 * sent to the server and to the SSRM datasource. Keeps both the persisted
 * `advanced_filters` row and every `POST /rows` request minimal: an unfiltered
 * domain sends no `advancedFilters` key at all.
 */
function computeActiveValues(
  descriptors: AdvancedFilterDescriptor[],
  values: AdvancedFilterValues,
): AdvancedFilterValues {
  const active: AdvancedFilterValues = {}
  for (const descriptor of descriptors) {
    const value = values[descriptor.name]
    const hasDefault = descriptor.defaultValue !== undefined && descriptor.defaultValue !== null
    const isActive = hasDefault ? !sameValue(value, descriptor.defaultValue) : !isEmptyValue(value)
    if (isActive) {
      active[descriptor.name] = value ?? null
    }
  }
  return active
}

interface UseAdvancedFiltersArgs {
  domain: string
  /** The domain's advanced filter catalog (`TableConfig.advancedFilters`), ordered by `order`. */
  descriptors: AdvancedFilterDescriptor[]
  /** Persisted state replayed from `TableConfig.appliedAdvancedFilters`. */
  applied: AdvancedFilterValues | null | undefined
  /** Invoked once after Apply/Reset persists, so the caller purge-reloads the grid exactly once. */
  onApplied: () => void
  /**
   * One-time values (e.g. a Tasks dashboard card's `?status=&assignment=`
   * deep link, spec 0151 D-2) that win over `applied` for THIS visit only:
   * they seed the draft/applied state and are read by `getApplied()`, but are
   * never persisted through `saveTableFilters`. Cleared by the caller
   * (`onOverrideCleared`) once the user exercises the normal Apply/Reset flow.
   */
  override?: AdvancedFilterValues | null
  /** Invoked once Apply/Reset persists, so the caller drops the one-time deep link (e.g. from the URL). */
  onOverrideCleared?: () => void
}

export interface UseAdvancedFiltersResult {
  draft: AdvancedFilterValues
  setFieldValue: (name: string, value: AdvancedFilterValue) => void
  isFieldDisabled: (descriptor: AdvancedFilterDescriptor) => boolean
  isFieldInvalid: (descriptor: AdvancedFilterDescriptor) => boolean
  /** for-select extra query params resolved from an active `dependency.param`. */
  dependencyParamsFor: (
    descriptor: AdvancedFilterDescriptor,
  ) => Record<string, string | number> | undefined
  /** False while any non-disabled required field is empty in the draft. */
  canApply: boolean
  /**
   * Currently-applied filters whose value differs from their default (spec
   * AC-012), keyed by descriptor name — e.g. to seed a saved filter view
   * (spec 0007/AC-009).
   */
  activeValues: AdvancedFilterValues
  /** `Object.keys(activeValues).length`. */
  activeCount: number
  apply: () => void
  reset: () => void
  /**
   * Clears ONE applied filter back to its default (spec 0158 D-5: the "x" of
   * a single advanced-filter chip — a required field lands on its default
   * instead of disappearing). Acts on `applied` directly (not the panel's
   * `draft`), so it never commits an unrelated in-progress edit sitting in a
   * closed panel.
   */
  clearField: (name: string) => void
  /**
   * Restores an externally-supplied set of values (e.g. from a saved filter
   * view) as the new applied state: merges over the descriptor defaults,
   * persists, and refreshes once — bypassing the required-gating that guards
   * `apply()`, since a saved view is assumed already valid (spec AC-009).
   */
  applyValues: (values: AdvancedFilterValues) => void
  isSaving: boolean
  /** Stable getter reading the last-APPLIED values; fed to the SSRM datasource. */
  getApplied: () => AdvancedFilterValues
}

/**
 * Owns the advanced-filters panel's draft-vs-applied state for one domain:
 * default seeding, dependency disable+clear, required-gating of Apply,
 * active-filter counting and persistence (spec 0032). The panel/table-view
 * stay thin: they only read this hook's state and call `setFieldValue` /
 * `apply` / `reset`.
 */
export function useAdvancedFilters({
  domain,
  descriptors,
  applied: appliedFromServer,
  onApplied,
  override,
  onOverrideCleared,
}: UseAdvancedFiltersArgs): UseAdvancedFiltersResult {
  const saveFilters = useSaveTableFilters(domain)

  const defaults = useMemo(() => buildDefaults(descriptors), [descriptors])
  const initial = useMemo(
    () => ({ ...defaults, ...(appliedFromServer ?? {}) }),
    [defaults, appliedFromServer],
  )

  const [draft, setDraft] = useState<AdvancedFilterValues>(initial)
  const [applied, setApplied] = useState<AdvancedFilterValues>(initial)

  // The SSRM datasource reads the active subset lazily (like the toolbar's
  // search term) through `getApplied`, so it never rebuilds on every
  // keystroke in the panel. `appliedRef`/`descriptorsRef` back that getter and
  // are updated ONLY from non-render code (effects, apply()/reset()) — never
  // assigned during the render body itself (`react-hooks/refs`) — and, for
  // `appliedRef`, synchronously at the same two call sites that change
  // `applied`, so a refresh triggered right after Apply never reads a stale
  // value (an effect-only sync would still be one tick behind).
  const appliedRef = useRef(initial)
  const descriptorsRef = useRef(descriptors)
  useEffect(() => {
    descriptorsRef.current = descriptors
  }, [descriptors])
  const getApplied = useCallback(
    () => computeActiveValues(descriptorsRef.current, appliedRef.current),
    [],
  )

  // Re-seed whenever the server config (re)loads — restores the persisted
  // state on mount and after an explicit "reset filters"/"reset layout"
  // refetch (spec AC-017). Guarded by CONTENT, not just the `initial`
  // reference: `appliedFromServer` is a plain object from the config query, so
  // it is only guaranteed referentially stable between renders when nothing
  // actually changed — a content compare keeps this effect a true no-op
  // (never re-triggers itself) instead of depending on that guarantee.
  const lastSeenInitialRef = useRef<string | null>(null)
  useEffect(() => {
    const serialized = JSON.stringify(initial)
    if (serialized === lastSeenInitialRef.current) {
      return
    }
    lastSeenInitialRef.current = serialized
    setDraft(initial)
    setApplied(initial)
    appliedRef.current = initial
  }, [initial])

  // One-time deep-link override (spec 0151 D-2): declared AFTER the reseed
  // effect above, so on a mount/config-load where both fire in the same pass
  // it wins over the persisted `appliedFromServer` state (the reseed effect
  // would otherwise leave `appliedRef` on the persisted state, since it only
  // reacts to `appliedFromServer`). Guarded by comparing the merged override
  // against the CURRENT `appliedRef.current` — not against the previous
  // override, which would wrongly skip re-seeding right after the reseed
  // effect's clobber merely because the override's own content did not
  // change — so it is idempotent no matter how often `override` fires (a
  // fresh-but-equal object literal every render is expected: only the
  // caller's `useMemo` keeps it referentially stable, this hook does not
  // assume that). Does nothing once `override` clears back to
  // `null`/`undefined` — Apply/Reset already set draft/applied themselves.
  useEffect(() => {
    if (!override) {
      return
    }
    const merged = { ...defaults, ...override }
    if (JSON.stringify(merged) === JSON.stringify(appliedRef.current)) {
      return
    }
    setDraft(merged)
    setApplied(merged)
    appliedRef.current = merged
  }, [override, defaults])

  const setFieldValue = useCallback(
    (name: string, value: AdvancedFilterValue) => {
      setDraft((current) => {
        const next = { ...current, [name]: value }
        // A dependent field disables and clears whenever its parent changes
        // (spec AC-016), regardless of the parent's new value.
        for (const descriptor of descriptors) {
          if (descriptor.dependency?.on === name) {
            next[descriptor.name] = defaults[descriptor.name] ?? null
          }
        }
        return next
      })
    },
    [descriptors, defaults],
  )

  const isFieldDisabled = useCallback(
    (descriptor: AdvancedFilterDescriptor) => {
      const dependency = descriptor.dependency
      return dependency !== undefined && isEmptyValue(draft[dependency.on])
    },
    [draft],
  )

  const isFieldInvalid = useCallback(
    (descriptor: AdvancedFilterDescriptor) =>
      descriptor.required && !isFieldDisabled(descriptor) && isEmptyValue(draft[descriptor.name]),
    [draft, isFieldDisabled],
  )

  const dependencyParamsFor = useCallback(
    (descriptor: AdvancedFilterDescriptor) => {
      const param = descriptor.dependency?.param
      if (!param) {
        return undefined
      }
      const parentValue = draft[descriptor.dependency!.on]
      if (typeof parentValue !== 'string' && typeof parentValue !== 'number') {
        return undefined
      }
      return { [param]: parentValue }
    },
    [draft],
  )

  const canApply = useMemo(
    () => descriptors.every((descriptor) => !isFieldInvalid(descriptor)),
    [descriptors, isFieldInvalid],
  )

  const activeValues = useMemo(
    () => computeActiveValues(descriptors, applied),
    [descriptors, applied],
  )
  const activeCount = useMemo(() => Object.keys(activeValues).length, [activeValues])

  const persist = useCallback(
    (values: AdvancedFilterValues) => {
      saveFilters.mutate({ advancedFilters: values })
    },
    [saveFilters],
  )

  const apply = useCallback(() => {
    if (!canApply) {
      return
    }
    setApplied(draft)
    appliedRef.current = draft
    persist(computeActiveValues(descriptors, draft))
    onApplied()
    // The normal flow always wins over a one-time deep-link override (spec
    // 0151 D-2): drop it (e.g. the caller strips `status`/`assignment` from
    // the URL) now that the user has explicitly applied filters.
    onOverrideCleared?.()
  }, [canApply, draft, descriptors, persist, onApplied, onOverrideCleared])

  const reset = useCallback(() => {
    setDraft(defaults)
    setApplied(defaults)
    appliedRef.current = defaults
    // The active subset of `defaults` is always empty by construction.
    persist({})
    onApplied()
    onOverrideCleared?.()
  }, [defaults, persist, onApplied, onOverrideCleared])

  const clearField = useCallback(
    (name: string) => {
      const next = { ...appliedRef.current, [name]: defaults[name] ?? null }
      setDraft((current) => ({ ...current, [name]: defaults[name] ?? null }))
      setApplied(next)
      appliedRef.current = next
      persist(computeActiveValues(descriptors, next))
      onApplied()
    },
    [defaults, descriptors, persist, onApplied],
  )

  const applyValues = useCallback(
    (values: AdvancedFilterValues) => {
      const merged = { ...defaults, ...values }
      setDraft(merged)
      setApplied(merged)
      appliedRef.current = merged
      persist(computeActiveValues(descriptors, merged))
      onApplied()
    },
    [defaults, descriptors, persist, onApplied],
  )

  return {
    draft,
    setFieldValue,
    isFieldDisabled,
    isFieldInvalid,
    dependencyParamsFor,
    canApply,
    activeValues,
    activeCount,
    apply,
    reset,
    clearField,
    applyValues,
    isSaving: saveFilters.isPending,
    getApplied,
  }
}
