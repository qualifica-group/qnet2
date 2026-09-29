import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/email-templates/email-template-form-payload'
import type { EmailTemplate } from '@/features/email-templates/types'
import type { EmailTemplateFormValues } from '@/features/email-templates/use-email-template-form'

/** Spec 0175 `data_contract`: `module` is immutable after create, never a PATCH key. */

const formValues: EmailTemplateFormValues = {
  name: 'Follow-up',
  module: 'work_orders',
  subject: 'Update on {work_order.code}',
  body: '<p>Hello {client.name}</p>',
  description: 'Sent after a site visit',
  is_active: true,
}

function original(overrides: Partial<EmailTemplate> = {}): EmailTemplate {
  return {
    id: 5,
    name: 'Follow-up',
    module: 'work_orders',
    subject: 'Update on {work_order.code}',
    body: '<p>Hello {client.name}</p>',
    description: 'Sent after a site visit',
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0175)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Follow-up',
      module: 'work_orders',
      subject: 'Update on {work_order.code}',
      body: '<p>Hello {client.name}</p>',
      description: 'Sent after a site visit',
      is_active: true,
    })
  })

  it('coerces a null body to an empty string (schema already validated it non-empty)', () => {
    expect(buildCreatePayload({ ...formValues, body: null }).body).toBe('')
  })
})

describe('buildUpdatePayload (spec 0175)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed subject', () => {
    expect(buildUpdatePayload({ ...formValues, subject: 'Renamed' }, original())).toEqual({
      subject: 'Renamed',
    })
  })

  it('includes only the changed body', () => {
    expect(buildUpdatePayload({ ...formValues, body: '<p>Updated</p>' }, original())).toEqual({
      body: '<p>Updated</p>',
    })
  })

  it('never carries module, even on a fully changed form', () => {
    const payload = buildUpdatePayload(
      { ...formValues, name: 'Renamed', subject: 'Renamed', is_active: false },
      original(),
    )
    expect(payload).not.toHaveProperty('module')
  })
})
