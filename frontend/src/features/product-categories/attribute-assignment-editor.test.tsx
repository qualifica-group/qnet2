import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import type { AttributeCatalogEntry } from '@/features/attributes/use-attribute-catalog'
import type { AttributeAssignmentInput, ProductCategoryInheritedAttribute } from '@/features/product-categories/types'

/**
 * Spec 0061: the editor renders TWO independent, context-scoped sections
 * ("Product attributes" / "Opportunity attributes"), each with its own
 * picker/list/inherited list. Task #19 (self-explanatory row: helper text +
 * info tooltips) still holds per section.
 */

const useAttributeCatalogMock = vi.fn()

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => useAttributeCatalogMock(),
}))

const CATALOG: AttributeCatalogEntry[] = [
  { id: 1, code: 'color', name: 'Color', type: 'enum' },
  { id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer' },
]

function queryResult(data: AttributeCatalogEntry[] = CATALOG) {
  return { data, isPending: false, isError: false, refetch: vi.fn() }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useAttributeCatalogMock.mockReset()
  useAttributeCatalogMock.mockReturnValue(queryResult())
})

function renderEditor(
  value: AttributeAssignmentInput[] = [],
  inherited: ProductCategoryInheritedAttribute[] = [],
  onChange = vi.fn(),
  known: AttributeCatalogEntry[] = [],
) {
  render(
    <AttributeAssignmentEditor value={value} onChange={onChange} known={known} inherited={inherited} />,
  )
  return { onChange }
}

describe('AttributeAssignmentEditor — two-section model (spec 0061)', () => {
  it('renders both context sections with distinct titles and descriptions', () => {
    renderEditor()

    expect(screen.getByRole('heading', { name: 'Product attributes' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Opportunity attributes' })).toBeInTheDocument()
    expect(
      screen.getByText('Loaded in the Product card (create/edit) for products in this category.'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Loaded in the Opportunity preliminary info for requests in this category.'),
    ).toBeInTheDocument()
  })

  it('shows each assignment only under its own context section', () => {
    renderEditor([
      { attribute_id: 1, context: 'product', is_required: false, sort_order: 0 },
      { attribute_id: 2, context: 'opportunity', is_required: false, sort_order: 0 },
    ])

    const productHeading = screen.getByRole('heading', { name: 'Product attributes' })
    const productSection = productHeading.closest('div')?.parentElement as HTMLElement
    const opportunityHeading = screen.getByRole('heading', { name: 'Opportunity attributes' })
    const opportunitySection = opportunityHeading.closest('div')?.parentElement as HTMLElement

    expect(within(productSection).getByText('Color')).toBeInTheDocument()
    expect(within(productSection).queryByText('RAM (GB)')).not.toBeInTheDocument()
    expect(within(opportunitySection).getByText('RAM (GB)')).toBeInTheDocument()
    expect(within(opportunitySection).queryByText('Color')).not.toBeInTheDocument()
  })

  it('adding an attribute from the product picker tags it with context "product"', () => {
    const { onChange } = renderEditor()

    const [productPicker] = screen.getAllByRole('combobox')
    fireEvent.click(productPicker)
    fireEvent.click(screen.getByRole('option', { name: 'Color' }))

    expect(onChange).toHaveBeenCalledWith([
      { attribute_id: 1, context: 'product', is_required: false, sort_order: 0 },
    ])
  })

  it('the same attribute can be assigned to both sections independently (two rows)', () => {
    const value: AttributeAssignmentInput[] = [
      { attribute_id: 1, context: 'product', is_required: false, sort_order: 0 },
    ]
    const { onChange } = renderEditor(value)

    const [, opportunityPicker] = screen.getAllByRole('combobox')
    fireEvent.click(opportunityPicker)
    fireEvent.click(screen.getByRole('option', { name: 'Color' }))

    expect(onChange).toHaveBeenCalledWith([
      { attribute_id: 1, context: 'product', is_required: false, sort_order: 0 },
      { attribute_id: 1, context: 'opportunity', is_required: false, sort_order: 0 },
    ])
  })

  it('removing a row only affects its own context, not the sibling assignment of the same attribute', () => {
    const value: AttributeAssignmentInput[] = [
      { attribute_id: 1, context: 'product', is_required: false, sort_order: 0 },
      { attribute_id: 1, context: 'opportunity', is_required: true, sort_order: 2 },
    ]
    const { onChange } = renderEditor(value)

    const [removeButton] = screen.getAllByRole('button', { name: 'Remove attribute' })
    fireEvent.click(removeButton)

    expect(onChange).toHaveBeenCalledWith([
      { attribute_id: 1, context: 'opportunity', is_required: true, sort_order: 2 },
    ])
  })

  it('splits the read-only inherited list by context', () => {
    const inherited: ProductCategoryInheritedAttribute[] = [
      { attribute_id: 1, code: 'color', name: 'Color', type: 'enum', is_required: false, context: 'product' },
      { attribute_id: 2, code: 'ram_gb', name: 'RAM (GB)', type: 'integer', is_required: true, context: 'opportunity' },
    ]
    renderEditor([], inherited)

    const productHeading = screen.getByRole('heading', { name: 'Product attributes' })
    const productSection = productHeading.closest('div')?.parentElement as HTMLElement
    const opportunityHeading = screen.getByRole('heading', { name: 'Opportunity attributes' })
    const opportunitySection = opportunityHeading.closest('div')?.parentElement as HTMLElement

    expect(within(productSection).getByText('Inherited from ancestor categories')).toBeInTheDocument()
    expect(within(opportunitySection).getByText('Inherited from ancestor categories')).toBeInTheDocument()
  })

  it('shows a visible "Order" label next to the sort_order input', () => {
    renderEditor([{ attribute_id: 1, context: 'product', is_required: false, sort_order: 0 }])

    expect(screen.getAllByText('Order')[0]).toBeInTheDocument()
    expect(screen.getAllByLabelText('Order')[0]).toBeInTheDocument()
  })

  it('reveals what "Required" means in a tooltip', async () => {
    renderEditor([{ attribute_id: 1, context: 'product', is_required: false, sort_order: 0 }])

    fireEvent.focus(screen.getAllByLabelText('When on, the product MUST fill in this attribute.')[0])
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(
        'When on, the product MUST fill in this attribute.',
      )
    })
  })

  it('describes the enum type in a tooltip on the badge', async () => {
    renderEditor([{ attribute_id: 1, context: 'product', is_required: false, sort_order: 0 }])

    fireEvent.focus(screen.getAllByText('List of options')[0])
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(
        'A choice from a predefined list of options.',
      )
    })
  })

  // The picker only ever holds ONE search window of a catalogue larger than it
  // (the 100-row cap of the rows endpoint): an assigned attribute left outside
  // that window used to render as a bare `#<id>` with no data type.
  it('labels an assigned attribute the picker window does not carry', () => {
    useAttributeCatalogMock.mockReturnValue(queryResult([]))

    renderEditor(
      [{ attribute_id: 7, context: 'product', is_required: false, sort_order: 0 }],
      [],
      vi.fn(),
      [{ id: 7, code: 'total_hours', name: 'Ore complessive', type: 'integer' }],
    )

    expect(screen.getByText('Ore complessive')).toBeInTheDocument()
    expect(screen.queryByText('#7')).not.toBeInTheDocument()
    expect(screen.getAllByText('Integer number')[0]).toBeInTheDocument()
  })
})
