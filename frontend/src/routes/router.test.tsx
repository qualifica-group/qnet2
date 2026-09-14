import { describe, expect, it } from 'vitest'
import { matchRoutes } from 'react-router-dom'
import { router } from '@/routes/router'

/**
 * Spec 0122 MT-F7: the three time-entries routes are declared by hand (D-1,
 * not a module-registry entity — see the comment in `router.tsx`). Asserts
 * they resolve structurally, without mounting `AppLayout`/`ProtectedRoute`.
 */
describe('router — time-entries routes', () => {
  it('resolves /time-entries to the dashboard route', () => {
    const matches = matchRoutes(router.routes, { pathname: '/time-entries' })
    expect(matches?.at(-1)?.route.path).toBe('time-entries')
  })

  it('resolves /time-entries/new to the create route, not the :id route', () => {
    const matches = matchRoutes(router.routes, { pathname: '/time-entries/new' })
    expect(matches?.at(-1)?.route.path).toBe('time-entries/new')
  })

  it('resolves /time-entries/42 to the :id (edit) route', () => {
    const matches = matchRoutes(router.routes, { pathname: '/time-entries/42' })
    expect(matches?.at(-1)?.route.path).toBe('time-entries/:id')
    expect(matches?.at(-1)?.params.id).toBe('42')
  })
})
