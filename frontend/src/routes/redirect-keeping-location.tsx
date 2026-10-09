import { Navigate, useLocation } from 'react-router-dom'

/**
 * Redirect that keeps `search` and `hash`: the API docs deep links carry the
 * `#operationId` of an endpoint, which a plain `<Navigate to>` would drop.
 */
export function RedirectKeepingLocation({ to }: { to: string }) {
  const { search, hash } = useLocation()
  return <Navigate to={`${to}${search}${hash}`} replace />
}
