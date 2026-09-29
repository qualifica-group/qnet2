import { type ReactElement } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { ForSelectItem } from '@/features/for-select/types'

interface ImportConfigRelationSelectProps {
  /** Resource segment of the for-select endpoint, e.g. `campaigns` -> `/campaigns/for-select`. */
  resource: string
  value: number | null
  onChange: (next: number | null) => void
  /** Resolved label of the global field, used as the select's accessible trigger name. */
  triggerLabel: string
  /**
   * Fired alongside `onChange` (pick/clear) with the full selected
   * `ForSelectItem`, including `meta` — spec 0176's Campaign -> Fonte prefill
   * reads `meta.source` off it, mirroring the Lead form's own
   * `RelationSelectField.onItemChange`.
   */
  onItemChange?: (item: ForSelectItem | null) => void
}

/**
 * The relation branch of a global-configuration field: the picker plus the
 * quick-create "+" next to it (spec 0028), so campaign/source can be created
 * without leaving the import wizard. Its own component because
 * `useQuickCreateAction` is a hook and `ImportConfigFields` renders the
 * fields in a loop.
 */
export function ImportConfigRelationSelect({
  resource,
  value,
  onChange,
  triggerLabel,
  onItemChange,
}: ImportConfigRelationSelectProps): ReactElement {
  const { t } = useTranslation('importWizard')
  const { renderAction, selectedItemFor } = useQuickCreateAction(resource)

  return (
    <AsyncPaginatedSelect
      resource={resource}
      value={value}
      onChange={(next) => onChange(next)}
      onItemChange={onItemChange}
      selectedItem={selectedItemFor(value)}
      labels={{
        placeholder: t('config.select.placeholder'),
        searchPlaceholder: t('config.select.searchPlaceholder'),
        empty: t('config.select.empty'),
        error: t('config.select.error'),
        clearLabel: t('config.select.clear'),
        triggerLabel,
        retry: t('config.select.retry'),
      }}
      action={renderAction((ref) => onChange(ref.id))}
    />
  )
}
