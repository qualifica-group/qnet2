/**
 * Footer action bar of the time entry form (spec 0122 MT-F2): "Reimposta"
 * (create only, confirmed), "Annulla", the submit button ("Aggiungi"/
 * "Aggiorna", "Salvataggio…" while pending) and, in edit mode, "Elimina"
 * (destructive, confirmed). Self-contained: it owns the delete mutation
 * itself, so a caller only needs the entry id and an `onDeleted` callback.
 */

import { useTranslation } from 'react-i18next'
import { RotateCcw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useDeleteTimeEntry } from '@/features/time-entries/form/use-time-entry-mutations'
import { cn } from '@/lib/utils'

interface TimeEntryFormActionsProps {
  mode: 'create' | 'edit'
  isSubmitting: boolean
  onCancel: () => void
  /** Create mode only: resets the form back to its defaults (behind a confirm). */
  onReset?: () => void
  /** Edit mode only: enables the "Elimina" action. */
  entryId?: number
  onDeleted?: () => void
  className?: string
}

export function TimeEntryFormActions({
  mode,
  isSubmitting,
  onCancel,
  onReset,
  entryId,
  onDeleted,
  className,
}: TimeEntryFormActionsProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const deleteMutation = useDeleteTimeEntry()

  const handleReset = async () => {
    const confirmed = await confirm({
      title: t('timeEntries.form.resetTitle'),
      description: t('timeEntries.form.resetDescription'),
      confirmLabel: t('timeEntries.form.resetConfirm'),
      cancelLabel: t('timeEntries.form.keepEditing'),
      tone: 'warning',
    })
    if (confirmed) {
      onReset?.()
    }
  }

  const handleDelete = async () => {
    if (entryId === undefined) {
      return
    }
    const confirmed = await confirm({
      title: t('timeEntries.form.deleteTitle'),
      description: t('timeEntries.form.deleteDescription'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    await deleteMutation.mutateAsync(entryId)
    onDeleted?.()
  }

  return (
    <div className={cn('flex flex-wrap items-center justify-end gap-2', className)}>
      {mode === 'create' && onReset ? (
        <Button
          type="button"
          variant="outline"
          className="bg-card"
          onClick={() => void handleReset()}
          disabled={isSubmitting}
        >
          <RotateCcw className="size-4" aria-hidden="true" />
          {t('timeEntries.form.reset')}
        </Button>
      ) : null}

      {mode === 'edit' && entryId !== undefined ? (
        <Button
          type="button"
          variant="destructive"
          onClick={() => void handleDelete()}
          disabled={isSubmitting || deleteMutation.isPending}
        >
          <Trash2 className="size-4" aria-hidden="true" />
          {t('timeEntries.form.delete')}
        </Button>
      ) : null}

      <Button type="button" variant="outline" className="bg-card" onClick={onCancel} disabled={isSubmitting}>
        {t('timeEntries.form.cancel')}
      </Button>

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting
          ? t('timeEntries.form.saving')
          : t(mode === 'edit' ? 'timeEntries.form.update' : 'timeEntries.form.add')}
      </Button>
    </div>
  )
}
