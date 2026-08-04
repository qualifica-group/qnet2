import { describe, expect, it } from 'vitest'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'

describe('safeInternalPath (spec 0078, constraint: action_url is always an internal path)', () => {
  it('accepts a root-relative path', () => {
    expect(safeInternalPath('/field-change-requests/12')).toBe('/field-change-requests/12')
  })

  it('rejects null', () => {
    expect(safeInternalPath(null)).toBeNull()
  })

  it('rejects an absolute URL', () => {
    expect(safeInternalPath('https://evil.com/x')).toBeNull()
  })

  it('rejects a protocol-relative URL', () => {
    expect(safeInternalPath('//evil.com/x')).toBeNull()
  })

  it('rejects a javascript: URL', () => {
    expect(safeInternalPath('javascript:alert(1)')).toBeNull()
  })

  it('rejects a data: URL', () => {
    expect(safeInternalPath('data:text/html,hi')).toBeNull()
  })
})
