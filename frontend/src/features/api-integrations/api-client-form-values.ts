import type { ApiClient, ApiClientPayload } from '@/features/api-integrations/types'
import type { ApiClientFormValues } from '@/features/api-integrations/api-client-schema'

export const EMPTY_API_CLIENT_FORM: ApiClientFormValues = {
  name: '',
  description: '',
  rate_limit_per_minute: null,
  expires_at: null,
  is_active: true,
}

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/** ISO 8601 instant -> `YYYY-MM-DDTHH:mm` in the browser timezone (the `datetime-local` wire format). */
export function toDateTimeLocal(iso: string | null): string | null {
  if (!iso) {
    return null
  }
  const date = new Date(iso)
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function apiClientToFormValues(client: ApiClient): ApiClientFormValues {
  return {
    name: client.name,
    description: client.description ?? '',
    rate_limit_per_minute: client.rate_limit_per_minute,
    expires_at: toDateTimeLocal(client.expires_at),
    is_active: client.is_active,
  }
}

function toIsoInstant(local: string | null): string | null {
  return local ? new Date(local).toISOString() : null
}

/** Create sends every field; `is_active` is edit-only. */
export function buildCreatePayload(values: ApiClientFormValues): ApiClientPayload {
  return {
    name: values.name.trim(),
    description: values.description.trim() === '' ? null : values.description.trim(),
    rate_limit_per_minute: values.rate_limit_per_minute,
    expires_at: toIsoInstant(values.expires_at),
  }
}

/**
 * Edit sends the full set but leaves `expires_at` out when untouched: the
 * server would reject an unchanged, already-past expiry (`after:now`).
 */
export function buildUpdatePayload(
  values: ApiClientFormValues,
  initial: ApiClientFormValues,
): ApiClientPayload {
  const { expires_at: expiresAt, ...rest } = buildCreatePayload(values)
  return {
    ...rest,
    is_active: values.is_active,
    ...(values.expires_at === initial.expires_at ? {} : { expires_at: expiresAt }),
  }
}
