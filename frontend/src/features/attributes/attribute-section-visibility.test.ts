import { describe, expect, it } from 'vitest'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import {
  hiddenAttributeCodes,
  hiddenSectionIds,
  isSectionVisible,
} from '@/features/attributes/attribute-section-visibility'

function section(id: string, codes: string[], visibleWhen?: string | null): LayoutSection {
  return {
    id,
    title: id,
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 1,
    sort_order: 0,
    rows: [{ id: `${id}-r`, items: codes.map((code) => ({ attribute_code: code, width: 'full' as const })) }],
    ...(visibleWhen === undefined ? {} : { visible_when: visibleWhen ? { attribute_code: visibleWhen } : null }),
  }
}

const BASE: LayoutBlob = { sections: [section('main', ['toggle', 'name']), section('extra', ['detail'], 'toggle')] }

describe('attribute section visibility', () => {
  it('layout null hides nothing', () => {
    expect(hiddenAttributeCodes(null, { toggle: false }).size).toBe(0)
    expect(hiddenSectionIds(null, {}).size).toBe(0)
  })

  it.each([[false], [null], [undefined], ['true'], [1]])('hides the section when the toggle is %s', (value) => {
    expect([...hiddenAttributeCodes(BASE, { toggle: value })]).toEqual(['detail'])
    expect([...hiddenSectionIds(BASE, { toggle: value })]).toEqual(['extra'])
  })

  it('hides the section when the toggle value is absent', () => {
    expect([...hiddenAttributeCodes(BASE, {})]).toEqual(['detail'])
  })

  it('shows the section when the toggle is true', () => {
    expect(hiddenAttributeCodes(BASE, { toggle: true }).size).toBe(0)
    expect(isSectionVisible(BASE, BASE.sections[1], { toggle: true })).toBe(true)
    expect(isSectionVisible(BASE, BASE.sections[1], { toggle: false })).toBe(false)
  })

  it('treats null and absent visible_when as always visible', () => {
    const layout: LayoutBlob = { sections: [section('a', ['x'], null), section('b', ['y'])] }
    expect(hiddenAttributeCodes(layout, {}).size).toBe(0)
  })

  it('hides the section when the control is not placed in the layout', () => {
    const layout: LayoutBlob = { sections: [section('extra', ['detail'], 'ghost')] }
    expect([...hiddenAttributeCodes(layout, { ghost: true })]).toEqual(['detail'])
  })

  it('a chain from a merge is hidden when the container section is hidden', () => {
    const layout: LayoutBlob = {
      sections: [section('main', ['a']), section('mid', ['b'], 'a'), section('leaf', ['c'], 'b')],
    }
    expect([...hiddenAttributeCodes(layout, { a: false, b: true })].sort()).toEqual(['b', 'c'])
    expect([...hiddenAttributeCodes(layout, { a: true, b: true })]).toEqual([])
    expect([...hiddenAttributeCodes(layout, { a: true, b: false })]).toEqual(['c'])
  })

  it('a cycle hides its sections', () => {
    const layout: LayoutBlob = { sections: [section('s1', ['a'], 'b'), section('s2', ['b'], 'a')] }
    expect([...hiddenAttributeCodes(layout, { a: true, b: true })].sort()).toEqual(['a', 'b'])
  })

  it('a section not in the layout (synthetic) is visible', () => {
    expect(isSectionVisible(BASE, section('synthetic', ['z']), { toggle: false })).toBe(true)
  })
})
