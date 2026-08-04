/**
 * Validates a notification's `action_url` as a safe, internal navigation
 * target (spec 0078, F-8/AC-027): it must start with `/` and NOT with `//`
 * (a protocol-relative URL that would navigate to an external host). Any
 * other value — an absolute URL, `javascript:`, `data:`, or `null` — is
 * rejected. Standalone and pure so the security rule is unit-testable on its
 * own, mirroring the `safeUrl()` allow-list pattern (frontend.md §10). Split
 * from `notification-item.tsx` so that component-only file keeps Fast Refresh
 * happy (`react-refresh/only-export-components`).
 */
export function safeInternalPath(actionUrl: string | null): string | null {
  if (!actionUrl || !actionUrl.startsWith('/') || actionUrl.startsWith('//')) {
    return null
  }
  return actionUrl
}
