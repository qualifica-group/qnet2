import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { SortableList } from '@/components/ui/sortable-list'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { RequiresNoteBadge } from '@/features/quote-workflows/requires-note-badge'
import {
  WORKFLOW_STATUS_GROUPS,
  type WorkflowStatusFormRow,
  type WorkflowStatusGroupValue,
  type WorkflowStatusRowPatch,
} from '@/features/quote-workflows/types'
import { cn } from '@/lib/utils'

export interface WorkflowStatusesEditorProps {
  rows: WorkflowStatusFormRow[]
  onReorder: (orderedIds: string[]) => void
  onAddCustom: () => void
  onRemoveCustom: (id: string) => void
  onUpdateRow: (id: string, patch: WorkflowStatusRowPatch) => void
  disabled?: boolean
  error?: string | null
}

/** Backend `quote_workflow_statuses.description` column limit (`max:500`). */
const STATUS_DESCRIPTION_MAX_LENGTH = 500

/** i18n key per fixed group value, kept out of the JSX so the option list stays a plain map (mirrors `opportunity-status-form-body`). */
const GROUP_LABEL_KEYS: Record<WorkflowStatusGroupValue, string> = {
  open: 'quoteWorkflows.form.statuses.group.open',
  pending: 'quoteWorkflows.form.statuses.group.pending',
  validated: 'quoteWorkflows.form.statuses.group.validated',
  closed_won: 'quoteWorkflows.form.statuses.group.closed_won',
  closed_lost: 'quoteWorkflows.form.statuses.group.closed_lost',
}

/** Soft-badge color per group: open=green (active), pending=orange (waiting), validated=violet (accertato, non chiuso), closed_won=emerald (positive outcome), closed_lost=red (negative outcome). */
const GROUP_BADGE_CLASSES: Record<WorkflowStatusGroupValue, string> = {
  open: BADGE_COLOR_CLASSES.green,
  pending: BADGE_COLOR_CLASSES.orange,
  validated: BADGE_COLOR_CLASSES.violet,
  closed_won: BADGE_COLOR_CLASSES.emerald,
  closed_lost: BADGE_COLOR_CLASSES.red,
}

/**
 * Shared SortableList-based status editor (spec 0047 AC-025), reused by both
 * a workflow's own `statuses` section (`QuoteWorkflowFormBody`) and
 * the GLOBAL default set (`DefaultStatusesSheet`) — the single place this
 * drag & drop UI is implemented. The three per-set pinned rows (`open`/
 * `closed_won`/`closed_lost`) render without a drag handle or a remove action (`isPinned`
 * keeps `<SortableList>` from ever letting a drag cross them, and their
 * fixed `group` shows as a read-only badge) — but their name, description,
 * color and `requires_note` flag are always editable, including in create
 * mode where they seed the
 * auto-created rows (AC-004). Custom rows are freely reorderable, editable,
 * and removable, and their group select carries "Validato" like any other
 * value (user directive 2026-08-07: no system row owns that phase). All
 * non-render logic (row mutation, reorder) lives in the caller's hook.
 */
export function WorkflowStatusesEditor({
  rows,
  onReorder,
  onAddCustom,
  onRemoveCustom,
  onUpdateRow,
  disabled = false,
  error,
}: WorkflowStatusesEditorProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <SortableList
        items={rows}
        isPinned={(row) => row.system_key !== null}
        pinnedRowClassName="bg-card"
        dragHandleLabel={t('quoteWorkflows.form.statuses.dragHandleLabel')}
        onReorder={onReorder}
        renderItem={(row) => (
          <WorkflowStatusRowContent
            row={row}
            onUpdateRow={onUpdateRow}
            onRemoveCustom={onRemoveCustom}
            disabled={disabled}
          />
        )}
      />

      {error ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      ) : null}

      <Button
        type="button"
        variant="outline"
        size="sm"
        className="w-full border-dashed text-muted-foreground hover:text-foreground"
        disabled={disabled}
        onClick={onAddCustom}
      >
        <Plus aria-hidden="true" />
        {t('quoteWorkflows.form.statuses.add')}
      </Button>
    </div>
  )
}

interface WorkflowStatusRowContentProps {
  row: WorkflowStatusFormRow
  onUpdateRow: WorkflowStatusesEditorProps['onUpdateRow']
  onRemoveCustom: WorkflowStatusesEditorProps['onRemoveCustom']
  disabled: boolean
}

function WorkflowStatusRowContent({
  row,
  onUpdateRow,
  onRemoveCustom,
  disabled,
}: WorkflowStatusRowContentProps) {
  const { t } = useTranslation()
  const isCustom = row.system_key === null
  const requiresNoteId = `workflow-status-requires-note-${row.id}`

  return (
    <div className="flex flex-1 flex-col gap-2">
      <Input
        aria-label={t('quoteWorkflows.form.statuses.name')}
        value={row.name}
        disabled={disabled}
        onChange={(event) => onUpdateRow(row.id, { name: event.target.value })}
      />
      <Textarea
        aria-label={t('quoteWorkflows.form.statuses.description')}
        placeholder={t('quoteWorkflows.form.statuses.descriptionPlaceholder')}
        value={row.description ?? ''}
        rows={2}
        maxLength={STATUS_DESCRIPTION_MAX_LENGTH}
        disabled={disabled}
        className="min-h-14 text-xs"
        onChange={(event) =>
          onUpdateRow(row.id, { description: event.target.value === '' ? null : event.target.value })
        }
      />
      <div className="flex items-center gap-2">
        <ColorTokenPicker
          value={row.color ?? ''}
          onChange={(color) => onUpdateRow(row.id, { color: color === '' ? null : color })}
          disabled={disabled}
          className="min-w-0 flex-1"
        />
        {isCustom ? (
          <Select
            value={row.group}
            onValueChange={(next) => onUpdateRow(row.id, { group: next as WorkflowStatusGroupValue })}
            disabled={disabled}
          >
            <SelectTrigger className="min-w-0 flex-1" aria-label={t('quoteWorkflows.form.statuses.group.label')}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {WORKFLOW_STATUS_GROUPS.map((group) => (
                <SelectItem key={group} value={group}>
                  {t(GROUP_LABEL_KEYS[group])}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Badge className={cn('shrink-0', GROUP_BADGE_CLASSES[row.group])}>
            {t(GROUP_LABEL_KEYS[row.group])}
          </Badge>
        )}
        {isCustom ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="shrink-0 text-muted-foreground hover:text-destructive"
            aria-label={t('quoteWorkflows.form.statuses.remove')}
            disabled={disabled}
            onClick={() => onRemoveCustom(row.id)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        ) : null}
      </div>
      <div className="flex items-center gap-2">
        <Switch
          id={requiresNoteId}
          checked={row.requires_note}
          disabled={disabled}
          onCheckedChange={(checked) => onUpdateRow(row.id, { requires_note: checked })}
        />
        <Label htmlFor={requiresNoteId} className="text-xs font-normal text-muted-foreground">
          {t('quoteWorkflows.form.statuses.requiresNote')}
        </Label>
        {row.requires_note ? <RequiresNoteBadge className="ml-auto" /> : null}
      </div>
    </div>
  )
}
