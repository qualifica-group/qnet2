/**
 * Footer action bar of the time entry create form (spec 0122 MT-F2, spec 0226
 * D-6): "Reimposta" (confirmed), "Annulla" and the submit button ("Aggiungi",
 * "Salvataggio…" while pending). The detail has no submit of its own: its rows
 * save one at a time and its footer only deletes.
 */

import { useTranslation } from 'react-i18next'
import { RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'

interface TimeEntryFormActionsProps {
  isSubmitting: boolean
  onCancel: () => void
  /** Resets the draft back to its defaults (behind a confirm). */
  onReset: () => void
}

export function TimeEntryFormActions({ isSubmitting, onCancel, onReset }: TimeEntryFormActionsProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()

  const handleReset = async () => {
    const confirmed = await confirm({
      title: t('timeEntries.form.resetTitle'),
      description: t('timeEntries.form.resetDescription'),
      confirmLabel: t('timeEntries.form.resetConfirm'),
      cancelLabel: t('timeEntries.form.keepEditing'),
      tone: 'warning',
    })
    if (confirmed) {
      onReset()
    }
  }

  return (
    <div className="flex flex-wrap items-center justify-end gap-2">
      <Button type="button" variant="outline" className="bg-card" onClick={() => void handleReset()} disabled={isSubmitting}>
        <RotateCcw className="size-4" aria-hidden="true" />
        {t('timeEntries.form.reset')}
      </Button>

      <Button type="button" variant="outline" className="bg-card" onClick={onCancel} disabled={isSubmitting}>
        {t('timeEntries.form.cancel')}
      </Button>

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? t('timeEntries.form.saving') : t('timeEntries.form.add')}
      </Button>
    </div>
  )
}
