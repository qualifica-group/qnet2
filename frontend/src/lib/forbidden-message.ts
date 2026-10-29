import axios from 'axios'
import type { i18n as I18nInstance } from 'i18next'
import type { ApiErrorResponse } from '@/api/types'
import { abilityLabel, resourceLabel } from '@/features/roles/permission-labels'

const HTTP_FORBIDDEN = 403

/** Resource prefix of a permission name, e.g. `opportunities.delete` -> `opportunities`. */
function permissionResource(permission: string): string {
  const dot = permission.indexOf('.')
  return dot === -1 ? permission : permission.slice(0, dot)
}

/**
 * Localized sentence for a 403 answer, or `null` when the error is not a 403.
 * A missing permission is composed from the client-side labels (the server only
 * sends the raw key); otherwise the server's own reason wins, and a generic
 * sentence is the last defence for an empty 403.
 */
export function forbiddenMessage(error: unknown, i18n: I18nInstance): string | null {
  if (!axios.isAxiosError<Partial<ApiErrorResponse>>(error) || error.response?.status !== HTTP_FORBIDDEN) {
    return null
  }
  const data = error.response.data
  const permission = data?.authorization?.permission
  if (data?.authorization?.reason === 'missing_permission' && permission) {
    return i18n.t('common.missingPermission', {
      ability: abilityLabel(permission, i18n),
      module: resourceLabel(permissionResource(permission), i18n),
    })
  }
  const message = typeof data?.message === 'string' ? data.message.trim() : ''
  return message !== '' ? message : i18n.t('common.forbidden')
}

/** Message to show for a failed request: the specific 403 sentence, else the caller's fallback. */
export function apiErrorMessage(error: unknown, fallback: string, i18n: I18nInstance): string {
  return forbiddenMessage(error, i18n) ?? fallback
}
