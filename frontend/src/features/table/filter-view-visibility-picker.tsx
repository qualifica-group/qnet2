import { useTranslation } from 'react-i18next'
import { Lock, Users } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { FilterViewVisibility } from '@/features/table/types'

interface VisibilityOptionProps {
  value: FilterViewVisibility
  active: boolean
  icon: typeof Lock
  label: string
  onSelect: (value: FilterViewVisibility) => void
}

/** One segment of the private/shared segmented control. */
function VisibilityOption({ value, active, icon: Icon, label, onSelect }: VisibilityOptionProps) {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={() => onSelect(value)}
      className={cn(
        'flex items-center justify-center gap-1.5 rounded-[5px] px-2 py-1 text-xs font-medium transition-colors',
        active
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground',
      )}
    >
      <Icon aria-hidden="true" className="size-3.5" />
      {label}
    </button>
  )
}

interface FilterViewVisibilityPickerProps {
  value: FilterViewVisibility
  onChange: (value: FilterViewVisibility) => void
  /** Spec 0158 D-3: `shared` requires `table-filter-views.publish`; without it, only "Privata" is offered. */
  canPublish: boolean
}

/**
 * The private/shared segmented control shared by the saved-view save panel
 * (`FilterViewsControl`) and the custom filter rule builder's "Salva come
 * vista" step (spec 0158): a user without `table-filter-views.publish` never
 * sees the "Condivisa" option at all, so `visibility` stays `private`.
 */
export function FilterViewVisibilityPicker({ value, onChange, canPublish }: FilterViewVisibilityPickerProps) {
  const { t } = useTranslation()

  if (!canPublish) {
    return null
  }

  return (
    <div role="group" aria-label={t('table.visibility')} className="grid grid-cols-2 gap-1 rounded-md bg-muted p-1">
      <VisibilityOption
        value="private"
        active={value === 'private'}
        icon={Lock}
        label={t('table.visibilityPrivate')}
        onSelect={onChange}
      />
      <VisibilityOption
        value="shared"
        active={value === 'shared'}
        icon={Users}
        label={t('table.visibilityShared')}
        onSelect={onChange}
      />
    </div>
  )
}
