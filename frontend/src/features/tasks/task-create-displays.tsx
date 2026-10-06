import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskPeopleList } from '@/features/tasks/task-people-list'
import { useTaskWorkOrderStageOptions } from '@/features/tasks/use-task-work-order-stage-options'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/*
 * What a CLOSED row of the create form shows (spec 0195 D-8): the draft's
 * current value, rendered like the detail renders the persisted one. The form
 * only holds ids, so names and badges are resolved through
 * `useForSelectLabels` — the very cache the pickers fill for their own
 * trigger, so a picked value costs no extra request.
 */

/** Stable empty id set: a fresh `[]` per render would break the hook's memo. */
const NO_IDS: number[] = []

/** The badge attributes every lookup for-select item carries in `meta`. */
interface LookupMeta {
  color?: string
  icon?: string | null
}

/** A placeholder while a label resolves: never the bare id. */
function PendingLabel() {
  return <span className="text-muted-foreground">…</span>
}

interface IdDisplayProps {
  resource: string
  id: number | null
}

/** The label of `id`: resolved by the server, else from a ref this form already holds (prefill, clone source). */
function knownName(id: number, known: RelationFieldRef[] | undefined): string | undefined {
  return known?.find((ref) => ref.id === id)?.name
}

/** A relation (anagrafica, referente, commessa…): its label, or the detail's empty dash. */
export function ForSelectLabelDisplay({ resource, id, known }: IdDisplayProps & { known?: RelationFieldRef | null }) {
  const labels = useForSelectLabels({ resource, ids: id !== null ? [id] : NO_IDS })
  if (id === null) {
    return <DetailEmpty />
  }
  return <>{labels.get(id)?.label ?? knownName(id, known ? [known] : undefined) ?? <PendingLabel />}</>
}

/** A configured lookup (stato, tipologia, priorita'…): the same colored badge the detail shows. */
export function LookupBadgeDisplay({ resource, id }: IdDisplayProps) {
  const labels = useForSelectLabels({ resource, ids: id !== null ? [id] : NO_IDS })
  if (id === null) {
    return <DetailEmpty />
  }
  const item = labels.get(id)
  if (!item) {
    return <PendingLabel />
  }
  const meta = (item as typeof item & { meta?: LookupMeta }).meta
  return <TaskLookupBadge value={{ id, name: item.label, color: meta?.color ?? '', icon: meta?.icon ?? null }} />
}

/** Assegnatari/osservatori/richiedente: the detail's people list. */
export function PeopleDisplay({ ids, known }: { ids: number[]; known?: RelationFieldRef[] }) {
  const labels = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids })
  if (ids.length === 0) {
    return <DetailEmpty />
  }
  const people = ids.map((id) => ({ id, name: labels.get(id)?.label ?? knownName(id, known) }))
  if (people.some((person) => person.name === undefined)) {
    return <PendingLabel />
  }
  return <TaskPeopleList people={people.map((person) => ({ id: person.id, name: person.name ?? '' }))} />
}

/** The fase among the commessa's own open fasi (the same list the editor offers). */
export function WorkOrderStageDisplay({ workOrderId, stageId }: { workOrderId: number | null; stageId: number | null }) {
  const { options } = useTaskWorkOrderStageOptions(workOrderId, null)
  if (stageId === null) {
    return <DetailEmpty />
  }
  return <>{options.find((stage) => stage.id === stageId)?.name ?? <PendingLabel />}</>
}

export function TextDisplay({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? <>{value}</> : <DetailEmpty />
}

export function RichTextDisplay({ html }: { html: string | null }) {
  return html ? <RichTextContent html={html} /> : <DetailEmpty />
}

export function DateDisplay({ value }: { value: string | null }) {
  return <>{formatDate(value) || <DetailEmpty />}</>
}

export function MinutesDisplay({ value }: { value: number | null }) {
  return value !== null ? <>{formatMinutesLabel(value)}</> : <DetailEmpty />
}

export function YesNoDisplay({ value }: { value: boolean }) {
  const { t } = useTranslation()
  return <>{t(value ? 'common.yes' : 'common.no')}</>
}

/** The draft rule in one word (its frequency), or "Non ricorrente". */
export function RecurrenceDisplay({ recurrence }: { recurrence: TaskFormValues['recurrence'] }) {
  const { t } = useTranslation()
  return (
    <>
      {recurrence.enabled && recurrence.frequency !== null
        ? t(`tasks.form.recurrence.frequencyOption.${recurrence.frequency}`)
        : t('tasks.form.summary.recurrenceOff')}
    </>
  )
}
