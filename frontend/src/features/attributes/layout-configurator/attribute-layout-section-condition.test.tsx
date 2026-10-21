import { useState } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { AttributeLayoutConfigurator } from '@/features/attributes/layout-configurator/attribute-layout-configurator'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** Spec 0288 AC-012 through the real configurator: Select options, saved blob, disabled states, preview. */

function attribute(code: string, name: string, type: EffectiveAttribute['type'], sortOrder: number): EffectiveAttribute {
  return {
    id: sortOrder + 1,
    code,
    name,
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

const ATTRIBUTES = [
  attribute('has_warranty', 'Has warranty', 'boolean', 0),
  attribute('warranty_years', 'Warranty years', 'text', 1),
  attribute('sku', 'SKU', 'text', 2),
]

function makeSection(id: string, title: string, codes: string[], visibleWhen: string | null = null): LayoutBlob['sections'][number] {
  return {
    id,
    title,
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

const BASE: LayoutBlob = {
  sections: [makeSection('general', 'General', ['has_warranty', 'sku']), makeSection('warranty', 'Warranty', ['warranty_years'])],
}

/** `onSave` receives every blob the configurator emits: the last call is what a save would persist. */
function Harness({ initialBlob = BASE, onSave }: { initialBlob?: LayoutBlob; onSave?: (blob: LayoutBlob) => void }) {
  const [blob, setBlob] = useState(initialBlob)
  const handleChange = (next: LayoutBlob) => {
    setBlob(next)
    onSave?.(next)
  }
  return <AttributeLayoutConfigurator blob={blob} onChange={handleChange} attributes={ATTRIBUTES} mode="create" />
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('section condition Select', () => {
  it('offers "Always visible" plus the allowed booleans and stores the choice in the blob', async () => {
    const onSave = vi.fn()
    render(<Harness onSave={onSave} />)

    // Section "General" holds the only boolean: it has nothing to choose; "Warranty" can pick it
    const selects = screen.getAllByRole('combobox', { name: 'Visible only if active' })
    expect(selects[0]).toBeDisabled()
    fireEvent.click(selects[1])
    expect(await screen.findByRole('option', { name: 'Always visible' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Has warranty' }))

    expect(onSave.mock.lastCall?.[0].sections[1].visible_when).toEqual({ attribute_code: 'has_warranty' })

    // The preview hides the section until the control is on, then labels the condition
    fireEvent.click(screen.getByRole('checkbox', { name: /Has warranty/ }))
    expect(screen.getByText('Visible if: Has warranty')).toBeInTheDocument()
  })

  it('shows the help text and disables the Select when no boolean is available', () => {
    render(<Harness initialBlob={{ sections: [makeSection('only', 'Only', ['sku'])] }} />)

    expect(screen.getByRole('combobox', { name: 'Visible only if active' })).toBeDisabled()
    expect(screen.getByText(/No yes\/no attribute available/)).toBeInTheDocument()
  })

  it('disables the Select of a section that controls another one', () => {
    render(<Harness initialBlob={{ sections: [BASE.sections[0], makeSection('warranty', 'Warranty', ['warranty_years'], 'has_warranty')] }} />)

    const selects = screen.getAllByRole('combobox', { name: 'Visible only if active' })
    expect(selects[0]).toBeDisabled()
    expect(screen.getByText(/cannot be conditional/)).toBeInTheDocument()
    expect(selects[1]).toBeEnabled()
  })

  it('clears the condition from the Select with "Always visible"', async () => {
    const onSave = vi.fn()
    render(<Harness onSave={onSave} initialBlob={{ sections: [BASE.sections[0], makeSection('warranty', 'Warranty', ['warranty_years'], 'has_warranty')] }} />)

    fireEvent.click(screen.getAllByRole('combobox', { name: 'Visible only if active' })[1])
    fireEvent.click(await screen.findByRole('option', { name: 'Always visible' }))

    expect(onSave.mock.lastCall?.[0].sections[1].visible_when).toBeNull()
  })
})

describe('preview applies the visibility', () => {
  it('hides the conditional section until the preview toggle is switched on', () => {
    render(<Harness initialBlob={{ sections: [BASE.sections[0], makeSection('warranty', 'Warranty', ['warranty_years'], 'has_warranty')] }} />)

    expect(screen.queryByRole('heading', { name: 'Warranty' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: /Has warranty/ }))

    expect(screen.getByRole('heading', { name: 'Warranty' })).toBeInTheDocument()
  })
})
