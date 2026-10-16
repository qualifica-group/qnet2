import { describe, expect, it } from 'vitest'
import { MANAGEMENT_CONTROL_ROUTES } from '@/routes/management-control-routes'

describe('MANAGEMENT_CONTROL_ROUTES', () => {
  it('AC-001 exposes the six pages of the module under management-control/', () => {
    expect(MANAGEMENT_CONTROL_ROUTES.map((route) => route.path)).toEqual([
      'management-control/income-statement',
      'management-control/exposure',
      'management-control/cash-flow',
      'management-control/trend',
      'management-control/billing',
      'management-control/accounts',
    ])
    expect(MANAGEMENT_CONTROL_ROUTES.every((route) => route.element !== undefined)).toBe(true)
  })
})
