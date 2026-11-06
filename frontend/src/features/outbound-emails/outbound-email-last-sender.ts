import type { ComposeContext } from '@/features/outbound-emails/types'

/** Keyed by the login email, so two users sharing a browser never see each other's choice (spec 0361, D-3). */
const STORAGE_KEY_PREFIX = 'outbound-emails.last-from-address:'

function storageKey(loginEmail: string): string {
  return `${STORAGE_KEY_PREFIX}${loginEmail.toLowerCase()}`
}

function readLastSender(loginEmail: string): string | null {
  try {
    return window.localStorage.getItem(storageKey(loginEmail))
  } catch {
    return null
  }
}

/** Remembers the sender of a successful send (D-3). */
export function storeLastSender(loginEmail: string, address: string): void {
  try {
    window.localStorage.setItem(storageKey(loginEmail), address)
  } catch {
    // Storage can be unavailable (private mode, quota): the composer then proposes the login email.
  }
}

/** Sender proposed on a draft without one: the last used if still allowed, else the login email (D-3). */
export function defaultSender(context: ComposeContext): string {
  const last = readLastSender(context.sender.email)
  return last !== null && context.sender_options.includes(last) ? last : context.sender.email
}
