import { useState } from 'react'
import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { usePersonalDataDraftInlineEdit } from '@/features/personal-data/use-personal-data-draft-inline-edit'
import { BANKS_FIELD } from '@/features/company-sites/company-site-rows'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'
import type { BankDraft } from '@/features/company-sites/types'

/**
 * The create draft's closed rows: the form's own and the anagraphic card's
 * (`usePersonalDataDraftInlineEdit`) plus the banks row, whose list lives
 * outside RHF too — "Ripristina" puts the list back as the row found it.
 */
export function useCompanySiteDraftInlineEdit(
  form: UseFormReturn<CompanySiteFormValues>,
  profileDraft: PersonalDataDraft,
  setProfileDraft: (next: PersonalDataDraft) => void,
  banks: BankDraft[],
  setBanks: (next: BankDraft[]) => void,
) {
  const { inline: base, cardSignal } = usePersonalDataDraftInlineEdit(form, profileDraft, setProfileDraft)
  const [banksSnapshot, setBanksSnapshot] = useState<BankDraft[] | null>(null)

  const start = (field: string) => {
    if (field === BANKS_FIELD) {
      setBanksSnapshot(banks)
    }
    base.start(field)
  }

  const cancel = () => {
    if (base.editingField === BANKS_FIELD && banksSnapshot) {
      setBanks(banksSnapshot)
    }
    base.cancel()
  }

  const inline: InlineEdit = { ...base, start, cancel }

  return { inline, cardSignal }
}
