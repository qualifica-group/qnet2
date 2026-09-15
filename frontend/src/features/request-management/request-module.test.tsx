import { describe, expect, it } from 'vitest'
import { renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import {
  ENROLLEE_MODULE,
  REQUEST_MODULE,
  RequestModuleProvider,
  useRequestModule,
} from '@/features/request-management/request-module'

/**
 * Spec 0130 frozen `frontend_contract` (+ D-9 delta): `RequestModuleConfig` is
 * the ONLY place the two modules differ — `key`/`apiBasePath`/`routeBasePath`
 * frozen to their spec values, `permission()` derives `${key}.${ability}`,
 * `allowsCreate` is the explicit D-8 flag (never derived from `can()`), and
 * `assignmentDomain` is the D-9 discriminant `POST /assignment/selection-
 * scope` reads (that route has no module segment of its own).
 */
describe('REQUEST_MODULE / ENROLLEE_MODULE configs', () => {
  it('REQUEST_MODULE is frozen to request-management, and allows creation', () => {
    expect(REQUEST_MODULE.key).toBe('request-management')
    expect(REQUEST_MODULE.apiBasePath).toBe('/request-management')
    expect(REQUEST_MODULE.routeBasePath).toBe('/request-management')
    expect(REQUEST_MODULE.labelKey).toBe('navigation.requestManagement')
    expect(REQUEST_MODULE.permission('update')).toBe('request-management.update')
    expect(REQUEST_MODULE.allowsCreate).toBe(true)
    expect(REQUEST_MODULE.assignmentDomain).toBe('quotes')
  })

  it('ENROLLEE_MODULE is frozen to enrollee-management, and D-8 forbids creation', () => {
    expect(ENROLLEE_MODULE.key).toBe('enrollee-management')
    expect(ENROLLEE_MODULE.apiBasePath).toBe('/enrollee-management')
    expect(ENROLLEE_MODULE.routeBasePath).toBe('/enrollee-management')
    expect(ENROLLEE_MODULE.labelKey).toBe('navigation.enrolleeManagement')
    expect(ENROLLEE_MODULE.permission('update')).toBe('enrollee-management.update')
    expect(ENROLLEE_MODULE.allowsCreate).toBe(false)
    expect(ENROLLEE_MODULE.assignmentDomain).toBe('enrollees')
  })
})

function wrapper(module = ENROLLEE_MODULE) {
  return ({ children }: { children: ReactNode }) => (
    <RequestModuleProvider module={module}>{children}</RequestModuleProvider>
  )
}

describe('useRequestModule (spec 0130 AC-017)', () => {
  it('defaults to REQUEST_MODULE when unwrapped, so existing call sites keep their current behaviour', () => {
    const { result } = renderHook(() => useRequestModule())

    expect(result.current).toBe(REQUEST_MODULE)
  })

  it('resolves the module provided by the nearest RequestModuleProvider', () => {
    const { result } = renderHook(() => useRequestModule(), { wrapper: wrapper(ENROLLEE_MODULE) })

    expect(result.current).toBe(ENROLLEE_MODULE)
  })
})
