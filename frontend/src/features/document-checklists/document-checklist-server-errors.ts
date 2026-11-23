import axios from 'axios'
import type { UseFormSetError } from 'react-hook-form'
import { notifyFormErrors } from '@/features/auth/form-errors'
import type { DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'

const TOP_LEVEL_FIELDS = ['name', 'code', 'description', 'is_active'] as const
const ITEMS_ROOT = 'items'
/** Item key the server calls `id` and the form calls `server_id` (RHF reserves `id`). */
const SERVER_ITEM_ID_FIELD = 'id'
const FORM_ITEM_ID_FIELD = 'server_id'
const ITEM_KEY = /^items\.(\d+)\.([a-z_]+)(?:\.\d+)?$/

type FormErrorPath = Parameters<UseFormSetError<DocumentChecklistFormValues>>[0]

/** Maps a server validation key (`items.2.max_files`, `items.0.allowed_file_types.1`) onto a form path, or null. */
export function serverKeyToFormPath(key: string): FormErrorPath | null {
  if ((TOP_LEVEL_FIELDS as readonly string[]).includes(key)) {
    return key as FormErrorPath
  }
  if (key === 'items') {
    return ITEMS_ROOT
  }
  const match = ITEM_KEY.exec(key)
  if (!match) {
    return null
  }
  const field = match[2] === SERVER_ITEM_ID_FIELD ? FORM_ITEM_ID_FIELD : match[2]
  return `items.${match[1]}.${field}` as FormErrorPath
}

/**
 * Puts a 422 on the field it belongs to (also inside the entries) and raises
 * the foreground toast. Returns true when the error was a handled 422.
 */
export function applyChecklistServerErrors(
  error: unknown,
  setError: UseFormSetError<DocumentChecklistFormValues>,
): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }

  const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
  const messages: string[] = []
  for (const [key, keyMessages] of Object.entries(errors)) {
    const message = keyMessages?.[0]
    if (!message) {
      continue
    }
    messages.push(message)
    const path = serverKeyToFormPath(key)
    if (path) {
      setError(path, { message })
    }
  }

  const summary = error.response.data?.message
  notifyFormErrors(messages.length > 0 || typeof summary !== 'string' ? messages : [summary])
  return true
}
