import { useState, type Dispatch, type SetStateAction } from 'react'
import { isBlankPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft, PersonalDataType } from '@/features/personal-data/types'

/**
 * Spec 0410 D-3: the category of the first product line decides which type the
 * new-client card starts on. A new type is applied only while the card is
 * still blank, so nothing the operator typed is lost; the operator can always
 * switch the type by hand afterwards.
 *
 * Returns the key the card must be mounted with: `PersonalDataCardForm` reads
 * its value once, at mount, so a type changed from outside needs a remount.
 */
export function useCategoryRegistryType(
  registryType: PersonalDataType | null,
  identityDraft: PersonalDataDraft,
  setIdentityDraft: Dispatch<SetStateAction<PersonalDataDraft>>,
): number {
  const [seenType, setSeenType] = useState<PersonalDataType | null>(null)
  const [identityCardKey, setIdentityCardKey] = useState(0)

  // Adjusted during render when the category type changes (react.dev "storing
  // information from previous renders"), not in an effect: only a NEW type
  // acts, so a type the operator picked by hand survives the card's own edits.
  if (registryType !== seenType) {
    setSeenType(registryType)
    if (registryType !== null && identityDraft.type !== registryType && isBlankPersonalDataDraft(identityDraft)) {
      setIdentityDraft({ ...identityDraft, type: registryType })
      setIdentityCardKey((key) => key + 1)
    }
  }

  return identityCardKey
}
