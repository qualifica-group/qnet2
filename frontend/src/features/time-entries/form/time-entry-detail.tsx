/**
 * The time entry detail (spec 0226): a header with the type chip, then the
 * record's rows, each editable in place (no edit form, D-1), and "Elimina" in
 * the footer when the actor may delete it (D-4). Shared by the detail Sheet
 * and the `/time-entries/:id` page.
 */

import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { timeEntryRecordValues } from '@/features/time-entries/form/time-entry-record'
import { TimeEntryRecord } from '@/features/time-entries/form/time-entry-record-sections'
import { useDeleteTimeEntry } from '@/features/time-entries/form/use-time-entry-mutations'
import { useTimeEntryInlineEdit } from '@/features/time-entries/form/use-time-entry-inline-edit'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TimeEntry } from '@/features/time-entries/types'
import { formatDate } from '@/lib/formatting/date-display'

interface TimeEntryDeleteButtonProps {
  entryId: number
  onDeleted: () => void
}

function TimeEntryDeleteButton({ entryId, onDeleted }: TimeEntryDeleteButtonProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const deleteMutation = useDeleteTimeEntry()

  const handleDelete = async () => {
    const confirmed = await confirm({
      title: t('timeEntries.form.deleteTitle'),
      description: t('timeEntries.form.deleteDescription'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    try {
      await deleteMutation.mutateAsync(entryId)
    } catch {
      // The mutation's own error toast already told the user.
      return
    }
    onDeleted()
  }

  return (
    <Button type="button" variant="destructive" size="sm" onClick={() => void handleDelete()} disabled={deleteMutation.isPending}>
      <Trash2 aria-hidden="true" />
      {t('timeEntries.form.delete')}
    </Button>
  )
}

interface TimeEntryDetailProps {
  entry: TimeEntry
  /** Called once the entry is deleted, so the caller can close or navigate away. */
  onDeleted: () => void
}

export function TimeEntryDetail({ entry, onDeleted }: TimeEntryDetailProps) {
  const { form, inline, controls } = useTimeEntryInlineEdit(entry)

  return (
    <Form {...form}>
      <div className="flex flex-1 flex-col overflow-hidden">
        <TimeEntryRecord
          title={entry.title}
          subtitle={[entry.user.name, formatDate(entry.date), formatMinutesLabel(entry.minutes)].join(' · ')}
          values={timeEntryRecordValues(entry)}
          form={form}
          inline={inline}
          controls={controls}
          canEdit={entry.permissions.update}
        />
        {entry.permissions.delete ? (
          <div className="flex justify-end border-t p-4">
            <TimeEntryDeleteButton entryId={entry.id} onDeleted={onDeleted} />
          </div>
        ) : null}
      </div>
    </Form>
  )
}
