import axios from 'axios'

/**
 * Collects every `personal_data.*` (or bare `personal_data`) message of a 422
 * response into one banner string. The buffered anagraphic card is NOT an RHF
 * field of its owner form, so its server errors cannot be routed inline into
 * the owner-agnostic `PersonalDataCardForm`/`ContactsManager`/
 * `AddressesManager`: they surface in the owner's banner instead.
 */
export function personalDataServerErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => key === 'personal_data' || key.startsWith('personal_data.'))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}
