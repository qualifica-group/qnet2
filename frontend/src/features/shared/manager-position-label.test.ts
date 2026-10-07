import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { managerSlotLabels } from '@/features/shared/manager-position-label'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('managerSlotLabels', () => {
  it('names every slot with the shared default when nothing is configured', () => {
    const labels = managerSlotLabels(i18n.t, undefined)

    expect(Object.keys(labels)).toHaveLength(MAX_MANAGER_SLOTS)
    expect(labels[1]).toBe('Account manager 1')
    expect(labels[MAX_MANAGER_SLOTS]).toBe(`Account manager ${MAX_MANAGER_SLOTS}`)
  })

  it('puts a configured label on its position and keeps the default elsewhere', () => {
    const labels = managerSlotLabels(i18n.t, { '2': 'Commercial' })

    expect(labels[1]).toBe('Account manager 1')
    expect(labels[2]).toBe('Commercial')
    expect(labels[3]).toBe('Account manager 3')
  })
})
