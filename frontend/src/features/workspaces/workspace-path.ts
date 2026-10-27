/** URL prefix under which every page is mounted a second time, scoped to one app (spec 0317 D-7). */
export const WORKSPACE_PATH_PREFIX = '/app'

const WORKSPACE_PATH_PATTERN = new RegExp(`^${WORKSPACE_PATH_PREFIX}/[^/]+(/.*)?$`)

/** `route` is an unprefixed path starting with "/" (or "" for the app root). */
export function workspacePath(slug: string, route: string): string {
  return `${WORKSPACE_PATH_PREFIX}/${slug}${route}`
}

/** `/app/hr/invoices` -> `/invoices`; `/app/hr` -> `/`; any other path is returned as is. */
export function stripWorkspacePrefix(pathname: string): string {
  const match = WORKSPACE_PATH_PATTERN.exec(pathname)
  if (!match) {
    return pathname
  }
  return match[1] ?? '/'
}
