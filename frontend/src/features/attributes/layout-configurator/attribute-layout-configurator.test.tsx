import { useState } from 'react'
import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { AttributeLayoutConfigurator } from '@/features/attributes/layout-configurator/attribute-layout-configurator'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Spec 0062 AC-010 (section CRUD, controlled end-to-end through the real
 * component tree) and AC-011 (the live preview reflects every change,
 * immediately, via the SAME `AttributeLayoutRenderer` runtime uses). AC-009's
 * drag placement/move logic is covered at the handler seam in
 * `use-layout-configurator-actions.test.ts` (simulating a real pointer drag
 * over jsdom is impractical) and the underlying pure tree ops in
 * `layout-configurator-tree.test.ts`; this file additionally covers the
 * width-change control (also part of AC-009) end-to-end.
 */

function attribute(overrides: Partial<EffectiveAttribute> & Pick<EffectiveAttribute, 'code'>): EffectiveAttribute {
  return {
    id: 1,
    code: overrides.code,
    name: overrides.name ?? overrides.code,
    type: overrides.type ?? 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    sort_order: overrides.sort_order ?? 0,
    inherited: false,
    context: 'product',
    options: [],
  }
}

const SKU = attribute({ code: 'sku', name: 'SKU', sort_order: 0 })
const COMPANY_NAME = attribute({ code: 'company_name', name: 'Company name', sort_order: 1 })
const ATTRIBUTES = [SKU, COMPANY_NAME]

const INITIAL_BLOB: LayoutBlob = {
  sections: [
    {
      id: 's1',
      title: 'Identification',
      description: null,
      variant: 'default',
      collapsible: false,
      default_collapsed: false,
      is_advanced: false,
      columns: 2,
      sort_order: 0,
      rows: [{ id: 'r1', items: [{ attribute_code: 'sku', width: 'full' }] }],
    },
  ],
}

function Harness({ initialBlob = INITIAL_BLOB }: { initialBlob?: LayoutBlob }) {
  const [blob, setBlob] = useState(initialBlob)
  return <AttributeLayoutConfigurator blob={blob} onChange={setBlob} attributes={ATTRIBUTES} mode="create" />
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AttributeLayoutConfigurator', () => {
  it('renders the palette (unplaced) and the preview (placed), from the same blob', () => {
    render(<Harness />)

    // sku is placed -> in the preview, not the palette
    const paletteRegion = screen.getByText('Unplaced attributes').closest('div') as HTMLElement
    expect(within(paletteRegion).getByText('Company name')).toBeInTheDocument()
    expect(within(paletteRegion).queryByText('SKU')).not.toBeInTheDocument()

    expect(screen.getByRole('heading', { name: 'Identification' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'SKU' })).toBeInTheDocument()
  })

  it('AC-010/AC-011: renaming a section updates its title AND the live preview heading immediately', () => {
    render(<Harness />)

    const titleInput = screen.getByLabelText('Title')
    fireEvent.change(titleInput, { target: { value: 'Overview' } })

    expect(screen.queryByRole('heading', { name: 'Identification' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Overview' })).toBeInTheDocument()
  })

  it('AC-010: adding a section appends a second, empty, editable section', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'Add section' }))

    expect(screen.getAllByLabelText('Title')).toHaveLength(2)
  })

  it('AC-010: removing a section drops it from the editor and the preview, its item returns to the palette', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'Remove section' }))

    expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Identification' })).not.toBeInTheDocument()
    const paletteRegion = screen.getByText('Unplaced attributes').closest('div') as HTMLElement
    expect(within(paletteRegion).getByText('SKU')).toBeInTheDocument()
  })

  it('AC-010: reordering sections moves the second one above the first', () => {
    const twoSections: LayoutBlob = {
      sections: [
        { ...INITIAL_BLOB.sections[0], id: 's1', title: 'First', rows: [] },
        { ...INITIAL_BLOB.sections[0], id: 's2', title: 'Second', sort_order: 1, rows: [] },
      ],
    }
    render(<Harness initialBlob={twoSections} />)

    fireEvent.click(screen.getAllByRole('button', { name: 'Move section down' })[0])

    const headings = screen.getAllByRole('heading').map((heading) => heading.textContent)
    expect(headings.indexOf('Second')).toBeLessThan(headings.indexOf('First'))
  })

  it('AC-009/AC-011: changing an item width updates its preview grid span immediately', async () => {
    render(<Harness />)

    const skuField = screen.getByRole('textbox', { name: 'SKU' })
    const wrapperBefore = skuField.closest('[class*="col-span"]') as HTMLElement
    expect(wrapperBefore).toHaveClass('@xs:col-span-2')

    fireEvent.click(screen.getByRole('combobox', { name: 'Width' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Half' }))

    const wrapperAfter = screen.getByRole('textbox', { name: 'SKU' }).closest('[class*="col-span"]') as HTMLElement
    expect(wrapperAfter).toHaveClass('col-span-1')
    expect(wrapperAfter).not.toHaveClass('@xs:col-span-2')
  })

  it('AC-010: adding a row gives the section an extra empty drop zone', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))

    expect(screen.getAllByText('Drop an attribute here')).toHaveLength(1)
  })
})
