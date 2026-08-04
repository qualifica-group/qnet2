import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, Plus, Trash2, UserRound } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
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
 * 0080 lets a caller override the per-slot denomination via `labels`.
 */
export function ManagerSlotsField({
  value,
  onChange,
  selectedItems,
  disabled = false,
  labels,
}: ManagerSlotsFieldProps) {
  const { t } = useTranslation()
  const { quickCreated, renderAction } = useQuickCreateAction(USERS_FOR_SELECT_RESOURCE)

  const slotLabel = (index: number) => labels?.[index + 1] ?? t('registries.form.managerSlotLabel', { n: index + 1 })

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
              className="flex w-9 shrink-0 items-center gap-1 text-xs font-semibold text-muted-foreground"
              title={slotLabel(index)}
            >
              <UserRound aria-hidden="true" className="size-3.5" />
              {index + 1}
            </span>
            <div className="min-w-0 flex-1">
              <AsyncPaginatedSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={slot}
                onChange={(id) => setSlot(index, id)}
                selectedItem={selectedItemFor(slot)}
                showAvatar
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.managerSlotEmpty'),
                  searchPlaceholder: t('registries.form.managersSearch'),
                  empty: t('registries.form.managersEmpty'),
                  error: t('registries.form.managersError'),
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
        disabled={disabled}
        onClick={() => onChange([...value, null])}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('registries.form.managersAddSlot')}
      </Button>

      <p className="text-xs text-muted-foreground">{t('registries.form.managersHint')}</p>
    </div>
  )
}
