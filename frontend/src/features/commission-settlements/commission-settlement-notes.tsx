import { useState, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

const NOTES_MAX_LENGTH = 2000

interface CommissionSettlementNotesProps {
  notes: string | null
  /** True only while the settlement is a DRAFT and the actor holds `update`. */
  editable: boolean
  isSaving: boolean
  onSave: (notes: string | null) => void
}

/** Notes of a settlement: editable (PATCH) only in a draft, read-only text otherwise. */
export function CommissionSettlementNotes({ notes, editable, isSaving, onSave }: CommissionSettlementNotesProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState(notes ?? '')

  if (!editable) {
    return notes ? <p className="whitespace-pre-wrap text-sm">{notes}</p> : <DetailEmpty />
  }

  const trimmed = draft.trim()
  const unchanged = trimmed === (notes ?? '').trim()

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor="settlement-notes" className="sr-only">
        {t('commissionSettlements.notes.label')}
      </Label>
      <Textarea
        id="settlement-notes"
        rows={3}
        maxLength={NOTES_MAX_LENGTH}
        value={draft}
        onChange={(event: ChangeEvent<HTMLTextAreaElement>) => setDraft(event.target.value)}
      />
      <div className="flex justify-end">
        <Button
          size="sm"
          variant="secondary"
          disabled={unchanged || isSaving}
          onClick={() => onSave(trimmed === '' ? null : trimmed)}
        >
          {t('commissionSettlements.notes.save')}
        </Button>
      </div>
    </div>
  )
}
