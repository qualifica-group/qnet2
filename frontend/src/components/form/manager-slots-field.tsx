import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, Plus, Trash2, UserRound } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'

interface ManagerSlotsFieldProps {
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  value: (number | null)[]
  onChange: (next: (number | null)[]) => void
  /** Known {id,label} for the filled slots, for edit-mode trigger hydration. */
  selectedItems: ForSelectItem[]
  disabled?: boolean
  /**
   * Spec 0080: per-position label override (1-based, mirrors `value`'s own
   * index+1), resolved by the caller from a Product Category's configured G.A.
   * labels. A position missing from the map falls back to the default
   * "Gestore account n" string, unchanged. OPTIONAL: Registries never passes
   * it (decision 2 — that path keeps the default labels), which is what keeps
   * this prop, and every existing call site, non-breaking.
   */
  labels?: Record<number, string>
  /**
   * Spec 0097 D-4: per-position `for-select` filter (1-based, like `labels`).
   * Exists because ONE slot can be constrained while the others are not —
   * Gestione richieste scopes its OPERATOR slot to the chosen Sede operativa
   * (user directive 2026-07-23) while the rest of the team is not bound to a
   * site. Returning `undefined` for a position leaves that list unfiltered,
   * which is what every caller that does not pass the prop gets.
   */
  paramsFor?: (position: number) => Record<string, string | number> | undefined
  /**
   * Spec 0097 D-4: sibling of the value change exposing the whole picked
   * `ForSelectItem`, not just its id — the reciprocal half of the link above
   * needs the item's `meta` (the user's own Sede) to hydrate the dependent
   * field. Fired on pick and on clear, like `AsyncPaginatedSelect`'s own.
   */
  onItemChange?: (position: number, item: ForSelectItem | null) => void
  /**
   * Spec 0096: per-module overrides for the strings that NAME the people being
   * assigned. The defaults say "gestore account", which reads wrong wherever
   * the slots are not Gestori Account — Commesse assigns "Partecipanti".
   * Every key is optional and falls back to the shared `registries.form.*`
   * wording, so callers that pass nothing are untouched. Deliberately covers
   * only the noun-bearing strings: "Sposta su"/"Rimuovi slot"/"Slot vuoto"
   * are already neutral and stay shared.
   */
  strings?: {
    search?: string
    empty?: string
    error?: string
    addSlot?: string
    hint?: string
  }
}

/**
 * Ordered "G.A. n" manager slot editor. Each row is a fixed slot whose index+1
 * is its static G.A. number (the order of importance inherited by other
 * modules, spec 0040): pick a manager, clear it (the slot stays as a
 * persistent empty card), reorder with the arrows, or remove the slot
 * entirely. The value is the gap-aware `manager_slots` array submitted
 * verbatim to the backend. Domain-agnostic (spec 0020, extracted for reuse by
 * Opportunities, spec 0040): every i18n string is the shared `registries.form.*`
 * namespace, generic enough that "G.A." reads the same across modules. Spec
 * 0080 lets a caller override the per-slot denomination via `labels`, and spec
 * 0096 the noun-bearing strings around them via `strings` — Commesse assigns
 * "Partecipanti", not "Gestori account".
 */
export function ManagerSlotsField({
  value,
  onChange,
  selectedItems,
  disabled = false,
  labels,
  paramsFor,
  onItemChange,
  strings,
}: ManagerSlotsFieldProps) {
  const { t } = useTranslation()
  const { quickCreated, renderAction } = useQuickCreateAction(USERS_FOR_SELECT_RESOURCE)

  const slotLabel = (index: number) => labels?.[index + 1] ?? t('registries.form.managerSlotLabel', { n: index + 1 })

  // A caller that resolved ANY denomination (spec 0080) makes every row show
  // its name as VISIBLE text instead of the bare position number — a
  // `title`-only tooltip is invisible on touch, exactly the reasoning the
  // read-only detail panel already applies. All-or-nothing per field, not
  // per row: a mixed column would be ragged. Callers with no `labels` at all
  // (Registries, decision 2) keep the compact number badge unchanged.
  const hasResolvedLabels = labels !== undefined && Object.keys(labels).length > 0

  const setSlot = (index: number, id: number | null) =>
    onChange(value.map((slot, i) => (i === index ? id : slot)))

  const swap = (a: number, b: number) => {
    const next = [...value]
    ;[next[a], next[b]] = [next[b], next[a]]
    onChange(next)
  }

  const removeSlot = (index: number) => onChange(value.filter((_, i) => i !== index))

  const selectedItemFor = (id: number | null): ForSelectItem | null => {
    if (id === null) {
      return null
    }
    const known = selectedItems.find((item) => item.id === id)
    if (known) {
      return known
    }
    const created = quickCreated.find((ref) => ref.id === id)
    return created ? { id: created.id, label: created.name } : null
  }

  return (
    <div className="flex flex-col gap-2">
      <ul className="flex flex-col gap-2">
        {value.map((slot, index) => (
          // The slot's identity IS its position, so the index is the correct key.
          <li key={index} className="flex items-center gap-2">
            <span
              className={cn(
                'flex shrink-0 items-center gap-1 text-xs font-semibold text-muted-foreground',
                hasResolvedLabels ? 'w-24 sm:w-32' : 'w-9',
              )}
              title={slotLabel(index)}
            >
              <UserRound aria-hidden="true" className="size-3.5 shrink-0" />
              {hasResolvedLabels ? <span className="truncate">{slotLabel(index)}</span> : index + 1}
            </span>
            <div className="min-w-0 flex-1">
              <AsyncPaginatedSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={slot}
                onChange={(id) => setSlot(index, id)}
                onItemChange={(item) => onItemChange?.(index + 1, item)}
                params={paramsFor?.(index + 1)}
                selectedItem={selectedItemFor(slot)}
                showAvatar
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.managerSlotEmpty'),
                  searchPlaceholder: strings?.search ?? t('registries.form.managersSearch'),
                  empty: strings?.empty ?? t('registries.form.managersEmpty'),
                  error: strings?.error ?? t('registries.form.managersError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: slotLabel(index),
                  retry: t('common.retry'),
                }}
                action={renderAction((ref) => setSlot(index, ref.id), disabled)}
              />
            </div>
            <div className="flex shrink-0 gap-1">
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={t('registries.form.managerMoveUp')}
                disabled={disabled || index === 0}
                onClick={() => swap(index, index - 1)}
              >
                <ArrowUp aria-hidden="true" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={t('registries.form.managerMoveDown')}
                disabled={disabled || index === value.length - 1}
                onClick={() => swap(index, index + 1)}
              >
                <ArrowDown aria-hidden="true" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={t('registries.form.managerRemoveSlot')}
                disabled={disabled}
                onClick={() => removeSlot(index)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
          </li>
        ))}
      </ul>

      <Button
        type="button"
        variant="outline"
        size="sm"
        // Spec 0080 A1: the array itself (incl. empty slots) is capped at
        // MAX_MANAGER_SLOTS regardless of how many are actually filled —
        // mirrors the backend's own `ValidatesManagerSlots::MAX_MANAGER_SLOTS`.
        disabled={disabled || value.length >= MAX_MANAGER_SLOTS}
        onClick={() => onChange([...value, null])}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {strings?.addSlot ?? t('registries.form.managersAddSlot')}
      </Button>

      <p className="text-xs text-muted-foreground">{strings?.hint ?? t('registries.form.managersHint')}</p>
    </div>
  )
}
