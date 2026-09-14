import { describe, expect, it } from 'vitest'
import { findModuleRecordByPath } from '@/features/modules/module-registry'

describe('findModuleRecordByPath', () => {
  it('resolves a registered record path to its domain and id', () => {
    expect(findModuleRecordByPath('/request-management/345')).toEqual({ domain: 'request-management', id: 345 })
  })

  it('returns null for a path no registered module owns', () => {
    expect(findModuleRecordByPath('/unknown/1')).toBeNull()
  })

  it('returns null for a path without a numeric id', () => {
    expect(findModuleRecordByPath('/opportunities/new')).toBeNull()
  })
})
