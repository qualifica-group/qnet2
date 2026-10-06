import { describe, expect, it } from 'vitest'
import { isTaskCascadeEditable } from '@/features/tasks/task-inline-cascade'
import { taskDetail } from '@/features/tasks/task-fixtures'
import type { FieldPermission } from '@/features/authorization/types'

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

/** Every field editable except the ones listed. */
function permissionsLocking(...locked: string[]) {
  return (key: string): FieldPermission => (locked.includes(key) ? READ_ONLY : EDITABLE)
}

describe('isTaskCascadeEditable (spec 0195)', () => {
  it('lets a field without a cascade open whatever else is locked', () => {
    expect(isTaskCascadeEditable('title', taskDetail(), permissionsLocking('registry_id', 'referent_id'))).toBe(true)
  })

  it('closes the anagrafica when a linked record it would clear is locked', () => {
    expect(isTaskCascadeEditable('registry_id', taskDetail(), permissionsLocking('referent_id'))).toBe(false)
    expect(isTaskCascadeEditable('registry_id', taskDetail(), permissionsLocking('opportunity_id'))).toBe(false)
  })

  it('ignores a locked dependent that is already empty: clearing it sends nothing', () => {
    const task = taskDetail({ lead_id: null, work_order_stage_id: null })

    expect(isTaskCascadeEditable('registry_id', task, permissionsLocking('lead_id', 'work_order_stage_id'))).toBe(true)
  })

  it('closes the commessa when the anagrafica it sets is locked, even while the task has none', () => {
    const task = taskDetail({ registry_id: null, registry: null })

    expect(isTaskCascadeEditable('work_order_id', task, permissionsLocking('registry_id'))).toBe(false)
  })

  it('treats a disabled dependent as locked', () => {
    const disabled = (key: string): FieldPermission =>
      key === 'work_order_id' ? { ...EDITABLE, editable: false, disabled: true } : EDITABLE

    expect(isTaskCascadeEditable('opportunity_id', taskDetail(), disabled)).toBe(false)
  })

  it('closes the parent task only when it would clear a locked fase', () => {
    expect(isTaskCascadeEditable('parent_task_id', taskDetail({ work_order_stage_id: 4 }), permissionsLocking('work_order_stage_id'))).toBe(false)
    expect(isTaskCascadeEditable('parent_task_id', taskDetail(), permissionsLocking('work_order_stage_id'))).toBe(true)
  })
})
