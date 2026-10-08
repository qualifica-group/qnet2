import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { isPersonalDataCardValid } from '@/features/personal-data/personal-data-issues'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { REGISTRY_CARD_FIELD } from '@/features/registries/registry-record'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/**
 * The create draft's closed rows (spec 0200 D-5, `useDraftInlineEdit`) plus
 * the anagraphic card's own row, which lives outside RHF: "Ripristina" puts
 * the card back as the row found it, "Fatto" keeps it only once it is
 * complete — otherwise the row stays open and the card marks what is missing
 * (`cardSignal`). A press outside just closes it: nothing typed is lost, and
 * Salva reopens it if it is still incomplete.
 */
export function useRegistryDraftInlineEdit(
  form: UseFormReturn<RegistryFormValues>,
  profileDraft: PersonalDataDraft,
  setProfileDraft: (next: PersonalDataDraft) => void,
) {
  const { t } = useTranslation()
  const draft = useDraftInlineEdit(form)
  const [cardSnapshot, setCardSnapshot] = useState<PersonalDataDraft | null>(null)
  const [cardSignal, setCardSignal] = useState(0)
  const editingCard = draft.editingField === REGISTRY_CARD_FIELD

  const start = (field: string) => {
    if (field === REGISTRY_CARD_FIELD) {
      setCardSnapshot(profileDraft)
    }
    draft.start(field)
  }

  const cancel = () => {
    if (editingCard && cardSnapshot) {
      setProfileDraft(cardSnapshot)
    }
    draft.cancel()
  }

  const save = () => {
    if (editingCard && !isPersonalDataCardValid(profileDraft, t)) {
      setCardSignal((signal) => signal + 1)
      return
    }
    draft.save()
  }

  const inline: InlineEdit = { ...draft, start, cancel, save, dismiss: draft.save }

  return { inline, cardSignal }
}
