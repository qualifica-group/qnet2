import { describe, expect, it } from 'vitest'
import {
  allowedConditionAttributes,
  clearBrokenConditions,
  controlsOtherSections,
} from '@/features/attributes/layout-configurator/layout-configurator-conditions'
import { placeAttribute, removeRow, removeSection } from '@/features/attributes/layout-configurator/layout-configurator-tree'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { CustomFieldType } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** Spec 0288 AC-012: allowed options (D-2) and automatic reset (D-7). */

function attribute(code: string, type: CustomFieldType, sortOrder = 0): EffectiveAttribute {
  return {
    id: sortOrder + 1,
    code,
    name: code,
    type,
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    sort_order: sortOrder,
    inherited: false,
    context: 'product',
    options: [],
  }
}

function section(id: string, codes: string[], visibleWhen: string | null = null): LayoutSection {
  return {
    id,
    title: id,
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 2,
    sort_order: 0,
    rows: [{ id: `${id}-row`, items: codes.map((code) => ({ attribute_code: code, width: 'half' as const })) }],
    visible_when: visibleWhen ? { attribute_code: visibleWhen } : null,
  }
}

const ATTRIBUTES = [
  attribute('own_toggle', 'boolean', 0),
  attribute('toggle_a', 'boolean', 2),
  attribute('toggle_b', 'boolean', 1),
  attribute('nested_toggle', 'boolean', 3),
  attribute('unplaced_toggle', 'boolean', 4),
  attribute('name', 'text', 5),
]

// s1 places toggle_a/toggle_b/name; s2 (target) places own_toggle; s3 is conditional on toggle_a and places nested_toggle
const BLOB: LayoutBlob = {
  sections: [
    section('s1', ['toggle_a', 'toggle_b', 'name']),
    section('s2', ['own_toggle']),
    section('s3', ['nested_toggle'], 'toggle_a'),
  ],
}

describe('allowedConditionAttributes', () => {
  it('lists only placed booleans, outside the section, in unconditional sections, sort_order-stable', () => {
    const codes = allowedConditionAttributes(BLOB, 's2', ATTRIBUTES).map((a) => a.code)

    // excluded: own_toggle (same section), nested_toggle (conditional section), unplaced_toggle, name (not boolean)
    expect(codes).toEqual(['toggle_b', 'toggle_a'])
  })

  it('is empty when no boolean sits in another unconditional section', () => {
    expect(allowedConditionAttributes(BLOB, 's1', ATTRIBUTES).map((a) => a.code)).toEqual(['own_toggle'])
    expect(allowedConditionAttributes({ sections: [section('s1', ['name'])] }, 's1', ATTRIBUTES)).toEqual([])
  })
})

describe('controlsOtherSections', () => {
  it('is true for the section placing the control of another section', () => {
    expect(controlsOtherSections(BLOB, 's1')).toBe(true)
    expect(controlsOtherSections(BLOB, 's2')).toBe(false)
    expect(controlsOtherSections(BLOB, 's3')).toBe(false)
  })
})

describe('automatic reset of broken conditions (D-7)', () => {
  it('returns the same blob when every condition is still valid', () => {
    expect(clearBrokenConditions(BLOB)).toBe(BLOB)
  })

  it('resets when the control item is sent back to the palette', () => {
    const next = placeAttribute(BLOB, 'toggle_a', { type: 'palette' })

    expect(next.sections.find((s) => s.id === 's3')?.visible_when).toBeNull()
  })

  it('resets when the control is moved into the section it controls', () => {
    const next = placeAttribute(BLOB, 'toggle_a', { type: 'row', sectionId: 's3', rowId: 's3-row', beforeCode: null })

    expect(next.sections.find((s) => s.id === 's3')?.visible_when).toBeNull()
  })

  it('keeps the condition when the control moves to another unconditional section', () => {
    const next = placeAttribute(BLOB, 'toggle_a', { type: 'row', sectionId: 's2', rowId: 's2-row', beforeCode: null })

    expect(next.sections.find((s) => s.id === 's3')?.visible_when).toEqual({ attribute_code: 'toggle_a' })
  })

  it('resets when the control moves into a conditional section (no chains)', () => {
    const blob: LayoutBlob = {
      sections: [
        section('s1', ['toggle_a']),
        section('s2', ['name'], 'toggle_a'),
        section('s3', ['toggle_b']),
        section('s4', [], 'toggle_b'),
      ],
    }
    const next = placeAttribute(blob, 'toggle_b', { type: 'row', sectionId: 's2', rowId: 's2-row', beforeCode: null })

    expect(next.sections.find((s) => s.id === 's4')?.visible_when).toBeNull()
  })

  it('resets when the control row or its whole section is removed', () => {
    expect(removeRow(BLOB, 's1', 's1-row').sections.find((s) => s.id === 's3')?.visible_when).toBeNull()
    expect(removeSection(BLOB, 's1').sections.find((s) => s.id === 's3')?.visible_when).toBeNull()
  })
})
