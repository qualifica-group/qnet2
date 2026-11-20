import { describe, expect, it } from 'vitest'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import { freezeContractedPermissions, isContractedConfirmRequired, isContractedFrozen } from '@/features/quotes/contracted-quote'

const EDITABLE: FieldPermission = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

describe('contracted quote helpers', () => {
  it('is frozen only when under contract without the permission', () => {
    expect(isContractedFrozen({ is_contracted: true, can_update_contracted: false })).toBe(true)
    expect(isContractedFrozen({ is_contracted: true, can_update_contracted: true })).toBe(false)
    expect(isContractedFrozen({ is_contracted: false, can_update_contracted: false })).toBe(false)
    expect(isContractedConfirmRequired({ is_contracted: true, can_update_contracted: true })).toBe(true)
  })

  it('makes every field read-only except the free operational ones', () => {
    const permissions: ResourcePermissions = {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: { title: EDITABLE, manager_slots: EDITABLE, next_callback_at: EDITABLE },
      actions: {},
    }

    const frozen = freezeContractedPermissions(permissions)

    expect(frozen.fields.title).toMatchObject({ editable: false, readonly: true })
    expect(frozen.fields.manager_slots.editable).toBe(true)
    expect(frozen.fields.next_callback_at.editable).toBe(true)
    expect(frozen.resource.update).toBe(true)
  })
})
