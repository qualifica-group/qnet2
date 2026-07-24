import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { Form } from '@/components/ui/form'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { AttributeLayoutFormShape, LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Spec 0062 MT-2.1. AC-007 (flat fallback), AC-011 (same renderer for
 * preview and runtime), AC-012 (sections/grid/registry dispatch/required
 * marker/"Altre informazioni"), AC-016 (collapsible sections, a11y).
 * AC-013 (static grid classes) is covered by `attribute-layout-grid.test.ts`.
 */

function attribute(overrides: Partial<EffectiveAttribute> & Pick<EffectiveAttribute, 'code' | 'type'>): EffectiveAttribute {
  return {
    id: overrides.id ?? 1,
    code: overrides.code,
    name: overrides.name ?? overrides.code,
    type: overrides.type,
    description: overrides.description ?? null,
    help_text: overrides.help_text ?? null,
    placeholder: overrides.placeholder ?? null,
    icon: overrides.icon ?? null,
    config: overrides.config ?? null,
    relation_target: overrides.relation_target ?? null,
    is_required: overrides.is_required ?? false,
    sort_order: overrides.sort_order ?? 0,
    inherited: overrides.inherited ?? false,
    context: overrides.context ?? 'product',
    options: overrides.options ?? [],
  }
}

const COMPANY_NAME = attribute({
  code: 'company_name',
  name: 'Company name',
  type: 'text',
  is_required: true,
  sort_order: 1,
  help_text: 'Legal registered name',
})
const EMPLOYEE_COUNT = attribute({ code: 'employee_count', name: 'Employee count', type: 'integer', sort_order: 2 })
const IS_ACTIVE = attribute({ code: 'is_active', name: 'Is active', type: 'boolean', sort_order: 3 })
const TIER = attribute({
  code: 'tier',
  name: 'Tier',
  type: 'enum',
  sort_order: 4,
  options: [
    { value: 'gold', label: 'Gold', color: null, icon: null, sort_order: 0, is_default: false },
    { value: 'silver', label: 'Silver', color: null, icon: null, sort_order: 1, is_default: false },
  ],
})

const ALL_ATTRIBUTES = [COMPANY_NAME, EMPLOYEE_COUNT, IS_ACTIVE, TIER]

const LAYOUT_WITH_ONE_SECTION: LayoutBlob = {
  sections: [
    {
      id: 's1',
      title: 'Identification',
      description: 'Core identity fields',
      variant: 'highlighted',
      collapsible: false,
      default_collapsed: false,
      is_advanced: false,
      columns: 2,
      sort_order: 0,
      rows: [
        {
          id: 'r1',
          items: [
            { attribute_code: 'company_name', width: 'full' },
            { attribute_code: 'employee_count', width: 'half' },
          ],
        },
      ],
    },
  ],
}

function Harness({
  layout,
  attributes,
  mode = 'create',
  readOnly = false,
}: {
  layout: LayoutBlob | null
  attributes: EffectiveAttribute[]
  mode?: LayoutFormMode
  readOnly?: boolean
}) {
  const form = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: {} } })
  return (
    <Form {...form}>
      <form>
        <AttributeLayoutRenderer
          layout={layout}
          attributes={attributes}
          control={form.control}
          mode={mode}
          readOnly={readOnly}
        />
      </form>
    </Form>
  )
}

describe('AttributeLayoutRenderer', () => {
  it('AC-012: renders configured sections, dispatches each attribute to the right control, marks required, applies the grid', () => {
    render(<Harness layout={LAYOUT_WITH_ONE_SECTION} attributes={ALL_ATTRIBUTES} />)

    expect(screen.getByRole('heading', { name: 'Identification' })).toBeInTheDocument()
    expect(screen.getByText('Core identity fields')).toBeInTheDocument()

    const nameField = screen.getByRole('textbox', { name: 'Company name' })
    expect(nameField).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Employee count' })).toBeInTheDocument()

    // required marker (FormLabel's `*`) sits next to the required field only
    const nameLabel = screen.getByText('Company name').closest('label')
    expect(nameLabel).toHaveTextContent('*')

    // row grid: columns=2 -> 'grid-cols-1 sm:grid-cols-2'; item spans: full=2cols, half=1col
    const row = nameField.closest('[class*="grid-cols"]')
    expect(row).toHaveClass('grid-cols-1', 'sm:grid-cols-2')
    expect(nameField.closest('.col-span-1')).not.toBeNull()
    const nameSpanWrapper = nameField.closest('[class*="col-span"]')
    expect(nameSpanWrapper).toHaveClass('sm:col-span-2')
  })

  it('AC-012/AC-016: attributes not placed in any section render inside a collapsed trailing "Altre informazioni" section', () => {
    render(<Harness layout={LAYOUT_WITH_ONE_SECTION} attributes={ALL_ATTRIBUTES} />)

    expect(screen.queryByRole('checkbox', { name: 'Is active' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Tier' })).not.toBeInTheDocument()

    const trigger = screen.getByRole('button', { name: 'Altre informazioni' })
    expect(trigger).toHaveAttribute('data-state', 'closed')
    expect(trigger).toHaveAttribute('aria-expanded', 'false')

    fireEvent.click(trigger)

    expect(trigger).toHaveAttribute('data-state', 'open')
    expect(screen.getByRole('checkbox', { name: 'Is active' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tier' })).toBeInTheDocument()
  })

  it('AC-007: layout=null falls back to a flat, one-per-row list with no section grouping', () => {
    render(<Harness layout={null} attributes={ALL_ATTRIBUTES} />)

    expect(screen.queryByRole('heading')).not.toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Company name' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Employee count' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Is active' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tier' })).toBeInTheDocument()
    expect(screen.getByText('Company name').closest('label')).toHaveTextContent('*')
    expect(screen.getByText('Legal registered name')).toBeInTheDocument()
  })

  it('AC-011: the same renderer backs the runtime form and a live preview side by side', () => {
    render(
      <>
        <Harness layout={LAYOUT_WITH_ONE_SECTION} attributes={ALL_ATTRIBUTES} />
        <Harness layout={LAYOUT_WITH_ONE_SECTION} attributes={ALL_ATTRIBUTES} />
      </>,
    )

    expect(screen.getAllByRole('heading', { name: 'Identification' })).toHaveLength(2)
    expect(screen.getAllByRole('textbox', { name: 'Company name' })).toHaveLength(2)
  })

  it('mode=view forces every control read-only', () => {
    render(<Harness layout={null} attributes={ALL_ATTRIBUTES} mode="view" />)

    expect(screen.getByRole('textbox', { name: 'Company name' })).toHaveAttribute('readonly')
    expect(screen.getByRole('spinbutton', { name: 'Employee count' })).toHaveAttribute('readonly')
    expect(screen.getByRole('checkbox', { name: 'Is active' })).toBeDisabled()
  })
})
