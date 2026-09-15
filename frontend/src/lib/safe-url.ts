/**
 * Allow-list of URL schemes safe to hand to the DOM (`href`/`src`). React
 * does not block `javascript:`/`data:` at runtime (react-security.md "Unsafe
 * URL Schemes") — every URL that reaches an attribute from user input must be
 * validated through `safeUrl` first.
 */
export const DEFAULT_SAFE_URL_PROTOCOLS = ['http:', 'https:', 'mailto:'] as const

/**
 * Validates a user-provided URL against a scheme allow-list. Returns the
 * original string when safe, `undefined` otherwise, so a caller can omit the
 * attribute entirely instead of rendering a broken/dangerous link.
 */
export function safeUrl(
  url: string | null | undefined,
  allowedProtocols: readonly string[] = DEFAULT_SAFE_URL_PROTOCOLS,
): string | undefined {
  if (!url) {
    return undefined
  }
  try {
    const parsed = new URL(url)
    return allowedProtocols.includes(parsed.protocol) ? url : undefined
  } catch {
    return undefined
  }
}
