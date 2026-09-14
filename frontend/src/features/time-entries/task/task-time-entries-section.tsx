/**
 * "Segnatempo" tab body of the Task detail (spec 0122 MT-F6, D-9): the
 * "Nuovo intervallo" editor gated on `can_create` (dashed placeholder
 * otherwise), the day-grouped list, the shared edit Sheet and the delete
 * confirmation — same building blocks the dashboard's day card already uses
 * (`TimeEntryEditSheet`, `useDeleteTimeEntry` from `form/`), wired to the
 * task-scoped read endpoint instead of the dashboard's own.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { TimeEntryEditSheet } from '@/features/time-entries/form/time-entry-edit-sheet'
import { useDeleteTimeEntry } from '@/features/time-entries/form/use-time-entry-mutations'
import { TaskTimeEntryEditor } from '@/features/time-entries/task/task-time-entry-editor'
import { TaskTimeEntriesList } from '@/features/time-entries/task/task-time-entries-list'
import { useTaskTimeEntries } from '@/features/time-entries/task/use-task-time-entries'
import type { TimeEntry } from '@/features/time-entries/types'

interface TaskTimeEntriesSectionProps {
  taskId: number
}

/** `403` (task not visible / `time-entries.create` missing on the resolver) gets the dashed forbidden box, never the generic retry banner. */
function isForbiddenError(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 403
}

export function TaskTimeEntriesSection({ taskId }: TaskTimeEntriesSectionProps) {
  const { t } = useTranslation()
  const query = useTaskTimeEntries(taskId, true)
  const deleteMutation = useDeleteTimeEntry()
  const [editingEntryId, setEditingEntryId] = useState<number | null>(null)
  const [deletingEntry, setDeletingEntry] = useState<TimeEntry | null>(null)

  if (isForbiddenError(query.error)) {
    return (
      <div className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
        {t('timeEntries.task.forbidden')}
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="flex flex-col items-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
        <p className="text-sm text-destructive" role="alert">
          {t('timeEntries.page.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void query.refetch()}>
          {t('timeEntries.page.retry')}
        </Button>
      </div>
    )
  }

  const canCreate = query.data?.can_create ?? false
  const entries = query.data?.items ?? []

  const confirmDelete = () => {
    if (!deletingEntry) {
      return
    }
    deleteMutation.mutate(deletingEntry.id)
    setDeletingEntry(null)
  }

  return (
    <div className="grid gap-4">
      {canCreate ? (
        <TaskTimeEntryEditor taskId={taskId} />
      ) : (
        <div className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
          {t('timeEntries.task.forbidden')}
        </div>
      )}

      <TaskTimeEntriesList
        entries={entries}
        isLoading={query.isPending}
        onEditEntry={setEditingEntryId}
        onRequestDelete={setDeletingEntry}
      />

      {editingEntryId !== null ? (
        <TimeEntryEditSheet
          open={editingEntryId !== null}
          onOpenChange={(open) => !open && setEditingEntryId(null)}
          timeEntryId={editingEntryId}
        />
      ) : null}

      <AlertDialog open={deletingEntry !== null} onOpenChange={(open) => !open && setDeletingEntry(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('timeEntries.table.deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('timeEntries.table.deleteDescription')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('timeEntries.table.cancel')}</AlertDialogCancel>
            <AlertDialogAction disabled={deleteMutation.isPending} onClick={confirmDelete}>
              {t('timeEntries.table.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
