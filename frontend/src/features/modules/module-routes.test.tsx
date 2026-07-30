import { isValidElement } from 'react'
import { describe, expect, it } from 'vitest'
import { buildModuleRoutes } from '@/features/modules/module-routes'
import ModuleDetailPage from '@/features/modules/module-detail-page'
import ModuleFormPage from '@/features/modules/module-form-page'

/**
 * Spec 0068 AC-113. `buildModuleRoutes()` is a plain function over the real
 * `MODULE_REGISTRY` (built at import time via `import.meta.glob`, which has
 * already picked up `payment-method-screens.tsx`'s `moduleScreen`), so it is
 * callable directly — no router needs mounting. Asserts what the function
 * actually returns for the `payment-methods` domain (relative paths, no
 * leading slash — `basePath.replace(/^\//, '')`), not an imagined shape.
 */

/** Narrows a `RouteObject.element` (`ReactNode`) to its component type + props, or fails loudly. */
function elementOf(route: { element?: React.ReactNode } | undefined) {
  const element = route?.element
  if (!isValidElement(element)) {
    throw new Error('Expected route to carry a JSX element.')
  }
  return element as React.ReactElement<Record<string, unknown>>
}

describe('buildModuleRoutes — payment-methods deep-links (AC-113)', () => {
  it('generates new/:id/:id-edit/:id-duplicate for the payment-methods domain', () => {
    const routes = buildModuleRoutes()
    const paymentMethodRoutes = routes.filter(
      (route) => typeof route.path === 'string' && route.path.startsWith('payment-methods'),
    )
    const byPath = new Map(paymentMethodRoutes.map((route) => [route.path, route]))

    expect([...byPath.keys()].sort()).toEqual([
      'payment-methods/:id',
      'payment-methods/:id/duplicate',
      'payment-methods/:id/edit',
      'payment-methods/new',
    ])

    const newRoute = elementOf(byPath.get('payment-methods/new'))
    expect(newRoute.type).toBe(ModuleFormPage)
    expect(newRoute.props).toMatchObject({ domain: 'payment-methods' })

    const detailRoute = elementOf(byPath.get('payment-methods/:id'))
    expect(detailRoute.type).toBe(ModuleDetailPage)
    expect(detailRoute.props).toMatchObject({ domain: 'payment-methods' })

    const editRoute = elementOf(byPath.get('payment-methods/:id/edit'))
    expect(editRoute.type).toBe(ModuleFormPage)
    expect(editRoute.props).toMatchObject({ domain: 'payment-methods' })

    const duplicateRoute = elementOf(byPath.get('payment-methods/:id/duplicate'))
    expect(duplicateRoute.type).toBe(ModuleFormPage)
    expect(duplicateRoute.props).toMatchObject({
      domain: 'payment-methods',
      variant: 'duplicate',
    })
  })

  it('does not generate a route for a domain absent from the registry', () => {
    const routes = buildModuleRoutes()
    const bogus = routes.filter(
      (route) => typeof route.path === 'string' && route.path.startsWith('not-a-real-domain'),
    )

    expect(bogus).toHaveLength(0)
  })
})
