/**
 * The backend build this page load started against — the reference point the
 * backend half of the check compares every later poll to. Unlike the frontend,
 * the client carries no backend build id, so the first version it ever observes
 * is what "current" means for this session.
 *
 * Module-scoped on purpose. It belongs to the page load, not to any component,
 * so it must survive re-renders and remounts yet reset on the reload the banner
 * asks for — which module scope gives for free. `sessionStorage` would outlive
 * that reload and leave the banner up forever, and React state cannot be
 * written from render without violating the hooks rules.
 */
let baseline: string | null = null

/** Called from the poll (an effectful boundary), never from a render. */
export function rememberBackendVersion(observed: string | null): void {
  if (baseline === null) {
    baseline = observed
  }
}

export function backendBaselineVersion(): string | null {
  return baseline
}
