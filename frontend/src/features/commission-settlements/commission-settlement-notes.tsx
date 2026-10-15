import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordTextEditor } from '@/components/record-form/record-field-editors'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'

interface NotesFormValues {
  notes: string
}

interface CommissionSettlementNotesRowProps {
  notes: string | null
  /** True only while the settlement is a DRAFT and the actor holds `update`. */
  editable: boolean
  /** The notes PATCH; rejects on failure (the caller already toasts the server message). */
  onSave: (notes: string | null) => Promise<unknown>
}

/**
 * The notes of a settlement as a row edited in place (spec 0265 D-1): the
 * pencil opens the text, Save PATCHes it, Cancel restores it. Read-only once
 * the settlement leaves the draft.
 */
export function CommissionSettlementNotesRow({ notes, editable, onSave }: CommissionSettlementNotesRowProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const saved = { notes: notes ?? '' }
  // `values`: a refreshed settlement re-syncs the row; an open edit keeps its text.
  const form = useForm<NotesFormValues>({ defaultValues: saved, values: saved, resetOptions: { keepDirtyValues: true } })
  const current = useWatch({ control: form.control, name: 'notes' })

  const onSubmit = async ({ notes: next }: NotesFormValues) => {
    const trimmed = next.trim()
    try {
      await onSave(trimmed === '' ? null : trimmed)
      editing.close()
    } catch {
      // The failure is already toasted: the row stays open on the typed text.
    }
  }

  const inline = useRecordInlineEdit<NotesFormValues>({
    editing,
    form,
    onSubmit,
    serverError: null,
    clearServerError: () => undefined,
    genericErrorMessage: t('commissionSettlements.actions.genericError'),
  })

  return (
    <Form {...form}>
      <RecordInlineField
        field="notes"
        label={t('commissionSettlements.notes.label')}
        inline={inline}
        canEdit={editable}
        editor={<RecordTextEditor control={form.control} name="notes" label={t('commissionSettlements.notes.label')} multiline />}
      >
        {current.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{current}</span> : <DetailEmpty />}
      </RecordInlineField>
    </Form>
  )
}
