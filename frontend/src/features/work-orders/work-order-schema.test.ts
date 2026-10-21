import { beforeAll, describe, expect, it } from 'vitest'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import i18n from '@/i18n'
import { buildCreateWorkOrderSchema, buildUpdateWorkOrderSchema } from '@/features/work-orders/work-order-schema'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

const VALID_BASE = {
  code: 'COM-0001',
  quote_id: 4,
  title: 'Installazione impianto',
  type: 'processing' as const,
  start_date: '2026-03-01',
  supervisor_ids: [21],
  participant_slots: [31, null],
  callback_date: null,
  description: null,
  internal_notes: null,
  quote_line_ids: [1, 2],
  task_template_id: null,
  attribute_values: {},
}

function requiredTextAttribute(): ApplicableAttributeSummary {
  return {
    id: 1,
    code: 'site_access',
    name: 'Site access',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: true,
    sort_order: 0,
    options: [],
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

// REQUIREMENT CHANGED (user directive 2026-10-06): the forced closure left the
// form for an action with its own dialog (`work-order-force-close-dialog`),
// which owns the "reason required" rule now.
describe('buildCreateWorkOrderSchema — create-only rules', () => {
  it('rejects a create submit with no linked offer', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, quote_id: null })

    expect(result.success).toBe(false)
    const issue = !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'quote_id')
    expect(issue).toBeDefined()
  })

  // REQUIREMENT CHANGED (user decision 2026-10-20): the Responsabili are optional.
  it('accepts a create submit without responsabili', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))

    expect(schema.safeParse({ ...VALID_BASE, supervisor_ids: [] }).success).toBe(true)
  })

  it('rejects an empty code (D-1: the create form always prefills it)', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, code: '' })

    expect(result.success).toBe(false)
  })
})

describe('buildUpdateWorkOrderSchema', () => {
  it('does not require a linked offer (D-5: quote_id is read-only, not validated here)', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse({ ...VALID_BASE, quote_id: null })

    expect(result.success).toBe(true)
  })
})

/** Spec 0124 D-9: "Modello di Task", optional in both modes, never required. */
describe('buildCreateWorkOrderSchema / buildUpdateWorkOrderSchema — task_template_id (spec 0124)', () => {
  it('accepts null on create', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    expect(schema.safeParse({ ...VALID_BASE, task_template_id: null }).success).toBe(true)
  })

  it('accepts a picked id on create', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    expect(schema.safeParse({ ...VALID_BASE, task_template_id: 3 }).success).toBe(true)
  })

  it('accepts either shape on update too (D-5: read-only there, but structurally valid)', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n))
    expect(schema.safeParse({ ...VALID_BASE, task_template_id: 3 }).success).toBe(true)
  })
})

/** Spec 0098: the dynamic `attribute_values` shape, mirroring `quote-schema.test.ts`. */
describe('buildCreateWorkOrderSchema / buildUpdateWorkOrderSchema — attribute_values (spec 0098)', () => {
  it('rejects a create submit missing a required applicable attribute', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: null } })

    expect(result.success).toBe(false)
    const issue =
      !result.success && result.error.issues.find((entry) => entry.path.join('.') === 'attribute_values.site_access')
    expect(issue).toBeDefined()
  })

  it('accepts a create submit with the required attribute filled in', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: 'Gate 3' } })

    expect(result.success).toBe(true)
  })

  it('rejects an update submit missing a required applicable attribute', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n), [requiredTextAttribute()])
    const result = schema.safeParse({ ...VALID_BASE, attribute_values: { site_access: null } })

    expect(result.success).toBe(false)
  })

  it('no applicable attribute at all: an empty attribute_values map is valid', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const result = schema.safeParse(VALID_BASE)

    expect(result.success).toBe(true)
  })
})

// Spec 0215 AC-029: the title is optional (blank = automatic), only its length is capped.
describe('work order schema - optional title', () => {
  it('accepts a blank title in create and update, rejects one over 191 characters', () => {
    const create = buildCreateWorkOrderSchema(i18n.t.bind(i18n))
    const update = buildUpdateWorkOrderSchema(i18n.t.bind(i18n))

    expect(create.safeParse({ ...VALID_BASE, title: '' }).success).toBe(true)
    expect(update.safeParse({ ...VALID_BASE, title: '' }).success).toBe(true)
    expect(create.safeParse({ ...VALID_BASE, title: 'a'.repeat(192) }).success).toBe(false)
  })
})

// Spec 0288 AC-011: a required attribute in a hidden conditional section does not apply.
function layoutSection(id: string, code: string, visibleWhen: string | null): LayoutSection {
  return {
    id,
    title: id,
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 1,
    sort_order: 0,
    rows: [{ id: `${id}-row`, items: [{ attribute_code: code, width: 'full' }] }],
    visible_when: visibleWhen ? { attribute_code: visibleWhen } : null,
  }
}

const CONDITIONAL_LAYOUT: LayoutBlob = {
  sections: [layoutSection('main', 'has_site', null), layoutSection('extra', 'site_access', 'has_site')],
}

function conditionalAttribute(code: string, type: string, isRequired: boolean) {
  return {
    id: 1,
    code,
    name: code,
    type,
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: isRequired,
    sort_order: 0,
    options: [],
  }
}

const CONDITIONAL_ATTRIBUTES = [
  conditionalAttribute('has_site', 'boolean', false),
  conditionalAttribute('site_access', 'text', true),
]

function hasRequiredIssue(result: { success: boolean; error?: { issues: { path: PropertyKey[] }[] } }): boolean {
  return (
    !result.success && (result.error?.issues ?? []).some((issue) => issue.path.join('.') === 'attribute_values.site_access')
  )
}

describe('work order schemas - conditional sections (spec 0288 AC-011)', () => {
  it('create: a hidden section skips the required attribute, a visible one enforces it', () => {
    const schema = buildCreateWorkOrderSchema(i18n.t.bind(i18n), CONDITIONAL_ATTRIBUTES, CONDITIONAL_LAYOUT)

    expect(hasRequiredIssue(schema.safeParse({ ...VALID_BASE, attribute_values: { has_site: false, site_access: null } }))).toBe(false)
    expect(hasRequiredIssue(schema.safeParse({ ...VALID_BASE, attribute_values: { has_site: true, site_access: null } }))).toBe(true)
  })

  it('update: a hidden section skips the required attribute, a visible one enforces it', () => {
    const schema = buildUpdateWorkOrderSchema(i18n.t.bind(i18n), CONDITIONAL_ATTRIBUTES, CONDITIONAL_LAYOUT)

    expect(hasRequiredIssue(schema.safeParse({ ...VALID_BASE, attribute_values: { has_site: false, site_access: null } }))).toBe(false)
    expect(hasRequiredIssue(schema.safeParse({ ...VALID_BASE, attribute_values: { has_site: true, site_access: null } }))).toBe(true)
  })
})
