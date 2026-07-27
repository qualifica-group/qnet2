import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { ProductAttributeValuesSection } from '@/features/products/product-attribute-values-section'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * Spec 0062 AC-014 (product detail): `layout=null` -> the pre-existing flat,
 * valued-only `DetailField` list (AC-007, byte-for-byte); a configured layout
 * -> the SAME agnostic renderer as the form, mode="view"/readOnly.
 */

const RAM_ATTRIBUTE: ApplicableAttribute = {
  id: 1,
  code: 'ram_gb',
  name: 'RAM (GB)',
  type: 'integer',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

const TIER_ATTRIBUTE: ApplicableAttribute = {
  id: 2,
  code: 'tier',
  name: 'Tier',
  type: 'enum',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 1,
  options: [
    { value: 'gold', label: 'Gold', color: null },
    { value: 'silver', label: 'Silver', color: null },
  ],
}

const LAYOUT: LayoutBlob = {
  sections: [
    {
      id: 's1',
      title: 'Specifications',
      description: null,
      variant: 'default',
      collapsible: false,
      default_collapsed: false,
      columns: 1,
      sort_order: 0,
      rows: [{ id: 'r1', items: [{ attribute_code: 'ram_gb', width: 'full' }] }],
    },
  ],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductAttributeValuesSection — flat fallback (AC-007)', () => {
  it('renders nothing when no attribute carries a value', () => {
    const { container } = render(
      <ProductAttributeValuesSection layout={null} attributes={[RAM_ATTRIBUTE]} values={{}} />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  it('renders one DetailField per valued attribute, formatted through its type', () => {
    render(
      <ProductAttributeValuesSection
        layout={null}
        attributes={[RAM_ATTRIBUTE, TIER_ATTRIBUTE]}
        values={{ ram_gb: 32, tier: 'gold' }}
      />,
    )

    expect(screen.getByText('RAM (GB)')).toBeInTheDocument()
    expect(screen.getByText('32')).toBeInTheDocument()
    expect(screen.getByText('Tier')).toBeInTheDocument()
    expect(screen.getByText('Gold')).toBeInTheDocument()
  })
})

describe('ProductAttributeValuesSection — configured layout (spec 0062 AC-014)', () => {
  it('renders the section title and the field seeded from the current value, read-only', () => {
    render(
      <ProductAttributeValuesSection
        layout={LAYOUT}
        attributes={[RAM_ATTRIBUTE, TIER_ATTRIBUTE]}
        values={{ ram_gb: 32 }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Specifications' })).toBeInTheDocument()
    const ramField = screen.getByRole('spinbutton', { name: 'RAM (GB)' })
    expect(ramField).toHaveValue(32)
    expect(ramField).toHaveAttribute('readonly')

    // Unplaced attribute -> the synthetic trailing "Other information" section, collapsed.
    expect(screen.getByRole('button', { name: 'Other information' })).toHaveAttribute('data-state', 'closed')
  })
})
