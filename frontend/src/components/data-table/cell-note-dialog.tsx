/**
 * Note-collection dialog for a workflow-status inline edit that
 * `requires_note` (spec 0054 D-5): mounted only while a note is pending, so
 * it always opens fresh. Confirming submits ONCE with the trimmed note;
 * cancelling (Esc, overlay, close button, or the Cancel button) never calls
 * the server — the caller reverts the cell locally (AC-018). Enforcement of
 * "note required" stays server-side (D-5): this form mirrors that rule for a
 * responsive UI (frontend.md §5), it does not replace it — a resubmitted
 * empty note is still possible to route through and 422, handled the same
 * way as any other failed edit (0053's revert + toast).
 */
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'

/** Derived from the Zod schema built (with the localized message) inside the component below. */
type NoteFormValues = { note: string }

export interface CellNoteDialogProps {
  onConfirm: (note: string) => void
  onCancel: () => void
}

export function CellNoteDialog({ onConfirm, onCancel }: CellNoteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<NoteFormValues>({
    resolver: zodResolver(z.object({ note: z.string().trim().min(1, t('table.noteDialog.required')) })),
    defaultValues: { note: '' },
  })

  return (
    <Dialog open onOpenChange={(next) => (next ? undefined : onCancel())}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{t('table.noteDialog.title')}</DialogTitle>
          <DialogDescription>{t('table.noteDialog.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            className="flex flex-col gap-3"
            onSubmit={form.handleSubmit((values) => onConfirm(values.note.trim()))}
          >
            <FormField
              control={form.control}
              name="note"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('table.noteDialog.label')}</FormLabel>
                  <FormControl>
                    <Textarea rows={3} autoFocus {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" size="sm" onClick={onCancel}>
                {t('table.noteDialog.cancel')}
              </Button>
              <Button type="submit" size="sm">
                {t('table.noteDialog.confirm')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
