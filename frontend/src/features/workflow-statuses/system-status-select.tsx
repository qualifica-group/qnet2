import { useTranslation } from 'react-i18next'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { WorkflowStatusSwatch } from '@/features/workflow-statuses/workflow-status-swatch'
import type {
  WorkflowStatusFormRow,
  WorkflowStatusRowPatch,
  WorkflowSystemStatusOption,
} from '@/features/workflow-statuses/types'

interface SystemStatusSelectProps<R extends WorkflowStatusFormRow> {
  row: R
  options: WorkflowSystemStatusOption[]
  disabled: boolean
  onUpdateRow: (id: string, patch: WorkflowStatusRowPatch<R>) => void
}

function OptionLabel({ option }: { option: WorkflowSystemStatusOption }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={option.color} />
      <span className="truncate">{option.label}</span>
    </span>
  )
}

/**
 * The "Stato di sistema" a quote working status links (spec 0404). A pinned
 * row (system_key) shows the system status of its own key, read-only: the link
 * never changes there. Picking one on a custom row also mirrors its behavior
 * into `group`, the read-only projection the backend rewrites on save.
 */
export function SystemStatusSelect<R extends WorkflowStatusFormRow>({
  row,
  options,
  disabled,
  onUpdateRow,
}: SystemStatusSelectProps<R>) {
  const { t } = useTranslation()
  const isPinned = row.system_key !== null
  const selectedId = isPinned
    ? (options.find((option) => option.systemKey === row.system_key)?.id ?? null)
    : (row.quote_system_status_id ?? null)
  const selected = options.find((option) => option.id === selectedId) ?? null

  const handleChange = (next: string) => {
    const option = options.find((candidate) => candidate.id === Number(next))
    if (option) {
      onUpdateRow(row.id, { quote_system_status_id: option.id, group: option.behavior } as WorkflowStatusRowPatch<R>)
    }
  }

  return (
    <Select value={selectedId !== null ? String(selectedId) : undefined} onValueChange={handleChange} disabled={disabled || isPinned}>
      <SelectTrigger className="min-w-0 flex-1" aria-label={t('workflowStatuses.systemStatusLabel')}>
        <SelectValue placeholder={t('workflowStatuses.systemStatusPlaceholder')}>
          {selected ? <OptionLabel option={selected} /> : null}
        </SelectValue>
      </SelectTrigger>
      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option.id} value={String(option.id)}>
            <OptionLabel option={option} />
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
