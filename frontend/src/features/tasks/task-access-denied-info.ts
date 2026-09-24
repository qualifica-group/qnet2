import axios from 'axios'

/** One `errors.access_contacts` entry (spec 0155 D-7): the requester and the creator, deduplicated, server-side. */
export interface TaskAccessContact {
  id: number
  name: string
  email: string
}

export interface TaskAccessDeniedInfo {
  message: string
  contacts: TaskAccessContact[]
}

function isTaskAccessContact(value: unknown): value is TaskAccessContact {
  if (typeof value !== 'object' || value === null) {
    return false
  }
  const candidate = value as Record<string, unknown>
  return (
    typeof candidate.id === 'number' &&
    typeof candidate.name === 'string' &&
    typeof candidate.email === 'string'
  )
}

/**
 * Reads `GET /api/tasks/{task}`'s 403 shape (spec 0155 D-7): `{ success:
 * false, message, errors: { access_contacts: [{id,name,email}] } }`. `null`
 * for anything else (network error, a 403 with no such body, another status)
 * — the caller falls back to the generic error state. The response body is
 * server-controlled but still external network data, so every field is
 * runtime-checked rather than merely cast.
 */
export function taskAccessDeniedInfo(error: unknown): TaskAccessDeniedInfo | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 403) {
    return null
  }
  const data = error.response.data as
    | { message?: unknown; errors?: { access_contacts?: unknown } }
    | undefined
  const rawContacts = data?.errors?.access_contacts
  const contacts = Array.isArray(rawContacts) ? rawContacts.filter(isTaskAccessContact) : []
  return {
    message: typeof data?.message === 'string' ? data.message : '',
    contacts,
  }
}
