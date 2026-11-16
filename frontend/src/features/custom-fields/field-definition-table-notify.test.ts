import { describe, expect, it } from 'vitest'
import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  buildTableConfig,
  hydrateTableDefinition,
  type TableDefinitionBag,
} from '@/features/custom-fields/field-definition-table'
import { fieldDefinitionTableFields, validateTableDefinition } from '@/features/custom-fields/field-definition-table-schema'
import type { TableFieldConfig } from '@/features/custom-fields/types'

/** Spec 0390 AC-010: the row event notification of a `table` definition. */

const t = ((key: string) => key) as unknown as TFunction

const CONFIG: TableFieldConfig = {
  columns: [
    { key: 'inspection_date', label: 'Date', type: 'date', required: true },
    { key: 'alert', label: 'Alert', type: 'boolean', required: false },
  ],
  selectable: { key: 'active', label: 'Active' },
  notify: {
    trigger: 'alert',
    email_template_id: 4,
    record_team: true,
    user_ids: [7],
    role_ids: [2],
    emails: ['audit@example.com'],
  },
}

const schema = z
  .object({ table: fieldDefinitionTableFields() })
  .superRefine((values, ctx) => validateTableDefinition(values.table, t, ctx))

function issues(bag: TableDefinitionBag): string[] {
  const result = schema.safeParse({ table: bag })
  return result.success ? [] : result.error.issues.map((issue) => `${issue.path.join('.')}:${issue.message}`)
}

describe('table notification bag', () => {
  it('hydrates a persisted notification and projects it back unchanged', () => {
    const bag = hydrateTableDefinition(CONFIG)

    expect(bag.notify_enabled).toBe(true)
    expect(bag.notify_trigger).toBe('alert')
    expect(buildTableConfig(bag).notify).toEqual(CONFIG.notify)
  })

  it('maps "every added row" to a null trigger and omits a disabled notification', () => {
    const bag = hydrateTableDefinition({ ...CONFIG, notify: { ...CONFIG.notify!, trigger: null } })

    expect(bag.notify_trigger).toBe('')
    expect(buildTableConfig(bag).notify?.trigger).toBeNull()
    expect(buildTableConfig({ ...bag, notify_enabled: false })).not.toHaveProperty('notify')
  })

  it('defaults a new notification to the record team', () => {
    const bag = hydrateTableDefinition({ columns: CONFIG.columns })

    expect(bag.notify_enabled).toBe(false)
    expect(bag.notify_record_team).toBe(true)
  })
})

describe('table notification validation', () => {
  it('accepts a boolean column or the row selection as trigger', () => {
    const bag = hydrateTableDefinition(CONFIG)

    expect(issues(bag)).toEqual([])
    expect(issues({ ...bag, notify_trigger: 'active' })).toEqual([])
  })

  it('rejects a trigger that is not a yes/no switch of the table', () => {
    const bag = hydrateTableDefinition(CONFIG)

    expect(issues({ ...bag, notify_trigger: 'inspection_date' })).toEqual([
      'table.notify_trigger:customFields.tableEditor.notify.errors.trigger',
    ])
  })

  it('requires at least one recipient', () => {
    const bag = {
      ...hydrateTableDefinition(CONFIG),
      notify_record_team: false,
      notify_user_ids: [],
      notify_role_ids: [],
      notify_emails: [],
    }

    expect(issues(bag)).toEqual(['table.notify_record_team:customFields.tableEditor.notify.errors.noRecipient'])
    expect(issues({ ...bag, notify_enabled: false })).toEqual([])
  })
})
