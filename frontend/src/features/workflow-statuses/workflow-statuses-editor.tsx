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
import { RequiresNoteBadge } from '@/features/workflow-statuses/requires-note-badge'
import type {
  WorkflowStatusFormRow,
  WorkflowStatusGroupOption,
  WorkflowStatusRowPatch,
} from '@/features/workflow-statuses/types'
import { cn } from '@/lib/utils'

/** Per-domain optional row fields: quotes turn on `requires_note`, work orders `progress_percentage`. */
export interface WorkflowStatusesEditorFeatures {
  showRequiresNote?: boolean
  showProgressPercentage?: boolean
}

export interface WorkflowStatusesEditorProps<R extends WorkflowStatusFormRow> extends WorkflowStatusesEditorFeatures {
  rows: R[]
  /** The domain's group vocabulary (already translated); drives the select and the pinned-row badge. */
  groups: WorkflowStatusGroupOption[]
  onReorder: (orderedIds: string[]) => void
  onAddCustom: () => void
  onRemoveCustom: (id: string) => void
  onUpdateRow: (id: string, patch: WorkflowStatusRowPatch<R>) => void
  disabled?: boolean
  error?: string | null
}

/** Backend status `description` column limit (`max:500`). */
const STATUS_DESCRIPTION_MAX_LENGTH = 500

/** Bounds of a status' `progress_percentage` (backend unsigned tinyint, 0..100). */
const PROGRESS_PERCENTAGE_MIN = 0
const PROGRESS_PERCENTAGE_MAX = 100

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
export function WorkflowStatusesEditor<R extends WorkflowStatusFormRow>({
  rows,
  groups,
  showRequiresNote = false,
  showProgressPercentage = false,
  onReorder,
  onAddCustom,
  onRemoveCustom,
  onUpdateRow,
  disabled = false,
  error,
}: WorkflowStatusesEditorProps<R>) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <SortableList
        items={rows}
        isPinned={(row) => row.system_key !== null}
        pinnedRowClassName="bg-card"
        dragHandleLabel={t('workflowStatuses.dragHandleLabel')}
        onReorder={onReorder}
        renderItem={(row) => (
          <WorkflowStatusRowContent
            row={row}
            groups={groups}
            showRequiresNote={showRequiresNote}
            showProgressPercentage={showProgressPercentage}
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
        {t('workflowStatuses.add')}
      </Button>
    </div>
  )
}

interface WorkflowStatusRowContentProps<R extends WorkflowStatusFormRow> extends WorkflowStatusesEditorFeatures {
  row: R
  groups: WorkflowStatusGroupOption[]
  onUpdateRow: WorkflowStatusesEditorProps<R>['onUpdateRow']
  onRemoveCustom: WorkflowStatusesEditorProps<R>['onRemoveCustom']
  disabled: boolean
}

/** Empty input = no percentage (`null`); otherwise a whole number clamped to the backend bounds. */
function parseProgressPercentage(raw: string): number | null {
  if (raw.trim() === '') {
    return null
  }
  const parsed = Math.round(Number(raw))
  if (Number.isNaN(parsed)) {
    return null
  }
  return Math.min(PROGRESS_PERCENTAGE_MAX, Math.max(PROGRESS_PERCENTAGE_MIN, parsed))
}

function WorkflowStatusRowContent<R extends WorkflowStatusFormRow>({
  row,
  groups,
  showRequiresNote,
  showProgressPercentage,
  onUpdateRow,
  onRemoveCustom,
  disabled,
}: WorkflowStatusRowContentProps<R>) {
  const { t } = useTranslation()
  const isCustom = row.system_key === null
  const requiresNoteId = `workflow-status-requires-note-${row.id}`
  const progressId = `workflow-status-progress-${row.id}`
  const pinnedGroup = groups.find((group) => group.value === row.group)

  return (
    <div className="flex flex-1 flex-col gap-2">
      <Input
        aria-label={t('workflowStatuses.name')}
        value={row.name}
        disabled={disabled}
        onChange={(event) => onUpdateRow(row.id, { name: event.target.value } as WorkflowStatusRowPatch<R>)}
      />
      <Textarea
        aria-label={t('workflowStatuses.description')}
        placeholder={t('workflowStatuses.descriptionPlaceholder')}
        value={row.description ?? ''}
        rows={2}
        maxLength={STATUS_DESCRIPTION_MAX_LENGTH}
        disabled={disabled}
        className="min-h-14 text-xs"
        onChange={(event) =>
          onUpdateRow(row.id, {
            description: event.target.value === '' ? null : event.target.value,
          } as WorkflowStatusRowPatch<R>)
        }
      />
      <div className="flex items-center gap-2">
        <ColorTokenPicker
          value={row.color ?? ''}
          onChange={(color) =>
            onUpdateRow(row.id, { color: color === '' ? null : color } as WorkflowStatusRowPatch<R>)
          }
          disabled={disabled}
          className="min-w-0 flex-1"
        />
        {isCustom ? (
          <Select
            value={row.group}
            onValueChange={(next) => onUpdateRow(row.id, { group: next } as WorkflowStatusRowPatch<R>)}
            disabled={disabled}
          >
            <SelectTrigger className="min-w-0 flex-1" aria-label={t('workflowStatuses.groupLabel')}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {groups.map((group) => (
                <SelectItem key={group.value} value={group.value}>
                  {group.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Badge className={cn('shrink-0', pinnedGroup?.badgeClassName)}>{pinnedGroup?.label ?? row.group}</Badge>
        )}
        {isCustom ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="shrink-0 text-muted-foreground hover:text-destructive"
            aria-label={t('workflowStatuses.remove')}
            disabled={disabled}
            onClick={() => onRemoveCustom(row.id)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        ) : null}
      </div>
      {showProgressPercentage ? (
        <div className="flex items-center gap-2">
          <Label htmlFor={progressId} className="text-xs font-normal text-muted-foreground">
            {t('workflowStatuses.progressPercentage')}
          </Label>
          <Input
            id={progressId}
            type="number"
            inputMode="numeric"
            min={PROGRESS_PERCENTAGE_MIN}
            max={PROGRESS_PERCENTAGE_MAX}
            step={1}
            className="h-8 w-20 text-xs"
            value={row.progress_percentage ?? ''}
            disabled={disabled}
            onChange={(event) =>
              onUpdateRow(row.id, {
                progress_percentage: parseProgressPercentage(event.target.value),
              } as WorkflowStatusRowPatch<R>)
            }
          />
        </div>
      ) : null}
      {showRequiresNote ? (
        <div className="flex items-center gap-2">
          <Switch
            id={requiresNoteId}
            checked={row.requires_note ?? false}
            disabled={disabled}
            onCheckedChange={(checked) => onUpdateRow(row.id, { requires_note: checked } as WorkflowStatusRowPatch<R>)}
          />
          <Label htmlFor={requiresNoteId} className="text-xs font-normal text-muted-foreground">
            {t('workflowStatuses.requiresNote')}
          </Label>
          {row.requires_note ? <RequiresNoteBadge className="ml-auto" /> : null}
        </div>
      ) : null}
    </div>
  )
}
