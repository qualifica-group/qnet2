import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, matchRoutes, useLocation } from 'react-router-dom'
import { devRoutes } from '@/routes/dev-routes'
import { RedirectKeepingLocation } from '@/routes/redirect-keeping-location'
import { router } from '@/routes/router'

/** Prints where the router ended up, so the redirect target can be asserted. */
function LocationProbe() {
  const { pathname, search, hash } = useLocation()
  return <p>{`${pathname}${search}${hash}`}</p>
}

function renderRedirect(from: string, path: string, to: string) {
  return render(
    <MemoryRouter initialEntries={[from]}>
      <Routes>
        <Route path={path} element={<RedirectKeepingLocation to={to} />} />
        <Route path="*" element={<LocationProbe />} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('devRoutes', () => {
  it.each([
    '/dev/migrations',
    '/dev/system-health',
    '/dev/api-clients',
    '/dev/api-docs',
  ])('registers %s inside the app router', (pathname) => {
    expect(matchRoutes(router.routes, { pathname })).not.toBeNull()
    expect(matchRoutes(devRoutes, { pathname })).not.toBeNull()
  })

  it.each([
    ['/migrations', 'migrations/*'],
    ['/migrations/users', 'migrations/*'],
    ['/admin/system-health', 'admin/system-health'],
    ['/admin/api-integrations', 'admin/api-integrations'],
  ])('resolves the former URL %s to a redirect route', (pathname, routePath) => {
    expect(matchRoutes(devRoutes, { pathname })?.at(-1)?.route.path).toBe(routePath)
  })
})

describe('RedirectKeepingLocation', () => {
  it('keeps the #operationId hash and the search of the old URL', () => {
    renderRedirect(
      '/admin/api-integrations?tab=docs#getUsers',
      '/admin/api-integrations',
      '/dev/api-clients',
    )

    expect(screen.getByText('/dev/api-clients?tab=docs#getUsers')).toBeInTheDocument()
  })

  it('redirects the former migrations URL', () => {
    renderRedirect('/migrations', '/migrations/*', '/dev/migrations')

    expect(screen.getByText('/dev/migrations')).toBeInTheDocument()
  })
})
