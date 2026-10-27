/**
 * Per-browser convenience (spec 0317 D-7): the app the user last worked in, so
 * an unprefixed URL can be redirected into it. Storage can be unavailable
 * (private mode, quota, disabled), so every access is guarded.
 */
const storageKey = (userId: number) => `qnet.activeWorkspace.${userId}`

export function readRememberedWorkspace(userId: number | undefined): string | null {
  if (userId === undefined) {
    return null
  }
  try {
    return window.localStorage.getItem(storageKey(userId))
  } catch {
    return null
  }
}

export function rememberWorkspace(userId: number | undefined, slug: string): void {
  if (userId === undefined) {
    return
  }
  try {
    window.localStorage.setItem(storageKey(userId), slug)
  } catch {
    // Remembering the app is a convenience: losing it only costs one extra click.
  }
}

export function forgetWorkspace(userId: number | undefined): void {
  if (userId === undefined) {
    return
  }
  try {
    window.localStorage.removeItem(storageKey(userId))
  } catch {
    // See rememberWorkspace.
  }
}
