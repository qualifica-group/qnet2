import axios from 'axios'
import type { TFunction } from 'i18next'
import i18n from '@/i18n'
import { forbiddenMessage } from '@/lib/forbidden-message'

/**
 * Maps a failed wizard request onto a localized message (status-only,
 * mirroring `features/imports/use-import.ts`/`features/migrations/resolve-error-message.ts`):
 * no server message parsing, just the well-known statuses this contract can
 * return (see spec 0033 `data_contract` `<errors>`).
 */
export function resolveImportWizardErrorMessage(error: unknown, t: TFunction): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  const forbidden = forbiddenMessage(error, i18n)
  if (forbidden) return forbidden
  if (status === 404) return t('errors.notFound')
  if (status === 422) return t('errors.validation')
  if (status === 409) return t('errors.invalidState')
  return t('errors.generic')
}
