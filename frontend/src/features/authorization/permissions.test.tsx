import { describe, expect, it } from 'vitest'
import { renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import {
  ResourcePermissionsProvider,
  useResourcePermissions,
} from '@/features/authorization/permissions'
import type { ResourcePermissions } from '@/features/authorization/types'

function permissions(
  overrides: Partial<ResourcePermissions> = {},
): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: true,
      update: true,
      delete: false,
      export: true,
      import: false,
    },
    fields: {
      email: {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: true,
        disabled: false,
      },
      roles: {
        visible: true,
        hidden: false,
        editable: false,
        readonly: true,
        required: false,
        disabled: false,
      },
    },
    actions: {
      upload_avatar: false,
    },
    ...overrides,
  }
}

function wrapWith(permissionsValue: ResourcePermissions | null) {
  return ({ children }: { children: ReactNode }) => (
    <ResourcePermissionsProvider permissions={permissionsValue}>
      {children}
    </ResourcePermissionsProvider>
  )
}

describe('useResourcePermissions', () => {
  it('reads a known field descriptor from the provided metadata', () => {
    const { result } = renderHook(() => useResourcePermissions(), {
      wrapper: wrapWith(permissions()),
    })
    expect(result.current.field('roles')).toEqual({
      visible: true,
      hidden: false,
      editable: false,
      readonly: true,
      required: false,
      disabled: false,
    })
  })

  it('falls back to visible+editable for a field absent from the metadata', () => {
    const { result } = renderHook(() => useResourcePermissions(), {
      wrapper: wrapWith(permissions()),
    })
    expect(result.current.field('unknown_field')).toEqual({
      visible: true,
      hidden: false,
      editable: true,
      readonly: false,
      required: false,
      disabled: false,
    })
  })

  it('falls back gracefully with no provider at all (no crash)', () => {
    const { result } = renderHook(() => useResourcePermissions())
    expect(result.current.field('email').visible).toBe(true)
    expect(result.current.field('email').editable).toBe(true)
    expect(result.current.canAction('anything')).toBe(true)
    expect(result.current.canResource('delete')).toBe(true)
    expect(result.current.canRequestChange('anything')).toBe(false)
  })

  // Spec 0078: unlike field/canAction/canResource, an absent block must NOT
  // default to permissive — proposing a change is never assumed.
  describe('canRequestChange', () => {
    it('is true for a field listed in change_requestable_fields', () => {
      const { result } = renderHook(() => useResourcePermissions(), {
        wrapper: wrapWith(permissions({ change_requestable_fields: ['source_id'] })),
      })
      expect(result.current.canRequestChange('source_id')).toBe(true)
    })

    it('is false for a field not listed in change_requestable_fields', () => {
      const { result } = renderHook(() => useResourcePermissions(), {
        wrapper: wrapWith(permissions({ change_requestable_fields: ['source_id'] })),
      })
      expect(result.current.canRequestChange('operator_id')).toBe(false)
    })

    it('is false when the block is missing entirely', () => {
      const { result } = renderHook(() => useResourcePermissions(), {
        wrapper: wrapWith(permissions()),
      })
      expect(result.current.canRequestChange('source_id')).toBe(false)
    })
  })

  it('reads canAction/canResource from the provided metadata', () => {
    const { result } = renderHook(() => useResourcePermissions(), {
      wrapper: wrapWith(permissions()),
    })
    expect(result.current.canAction('upload_avatar')).toBe(false)
    expect(result.current.canAction('unknown_action')).toBe(true)
    expect(result.current.canResource('delete')).toBe(false)
    expect(result.current.canResource('view')).toBe(true)
  })
})
