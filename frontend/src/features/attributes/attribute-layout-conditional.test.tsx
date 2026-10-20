import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { Form } from '@/components/ui/form'
import i18n from '@/i18n'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import { AttributeLayoutView } from '@/features/attributes/attribute-layout-view'
import { attributeLayoutBlobSchema } from '@/features/attributes/attribute-layout-schema'
import type { AttributeLayoutFormShape, LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { DisplayableAttribute } from '@/features/attributes/attribute-value-display'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** Spec 0288: AC-009 (renderer), AC-010 (detail view), AC-013 (blob schema). */

function attribute(code: string, name: string, type: EffectiveAttribute['type'], sortOrder: number): EffectiveAttribute {
  return {
    id: sortOrder,
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
  attribute('toggle', 'Has warranty', 'boolean', 0),
  attribute('months', 'Warranty months', 'integer', 1),
  attribute('note', 'Note', 'text', 2),
]

function section(id: string, sortOrder: number, code: string, visibleWhen?: string): LayoutSection {
  return {
    id,
    title: id,
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 1,
    sort_order: sortOrder,
    rows: [{ id: `${id}-r`, items: [{ attribute_code: code, width: 'full' }] }],
    ...(visibleWhen ? { visible_when: { attribute_code: visibleWhen } } : {}),
  }
}

const LAYOUT: LayoutBlob = {
  sections: [section('General', 0, 'toggle'), section('Warranty', 1, 'months', 'toggle'), section('Notes', 2, 'note')],
}

function Harness({ showConditionHints = false }: { showConditionHints?: boolean }) {
  const form = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: { toggle: false } } })
  return (
    <Form {...form}>
      <form>
        <AttributeLayoutRenderer
          layout={LAYOUT}
          attributes={ATTRIBUTES}
          control={form.control}
          mode="create"
          showConditionHints={showConditionHints}
        />
      </form>
    </Form>
  )
}

const headings = () => screen.getAllByRole('heading').map((heading) => heading.textContent)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AttributeLayoutRenderer conditional sections', () => {
  it('AC-009: hides the section with the toggle off, shows it in place when on, hides it again when off', () => {
    render(<Harness />)
    expect(headings()).toEqual(['General', 'Notes'])
    expect(screen.queryByRole('spinbutton', { name: 'Warranty months' })).not.toBeInTheDocument()

    const toggle = screen.getByRole('checkbox', { name: 'Has warranty' })
    fireEvent.click(toggle)
    expect(headings()).toEqual(['General', 'Warranty', 'Notes'])
    expect(screen.getByRole('spinbutton', { name: 'Warranty months' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Has warranty' }))
    expect(headings()).toEqual(['General', 'Notes'])
  })

  it('shows the "Visible if" hint on a visible conditional section only when asked', () => {
    const { unmount } = render(<Harness />)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Has warranty' }))
    expect(screen.queryByText('Visible if: Has warranty')).not.toBeInTheDocument()
    unmount()

    render(<Harness showConditionHints />)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Has warranty' }))
    expect(screen.getByText('Visible if: Has warranty')).toBeInTheDocument()
  })
})

describe('AttributeLayoutView conditional sections', () => {
  const displayable: DisplayableAttribute[] = ATTRIBUTES.map((a) => ({
    id: a.id,
    code: a.code,
    name: a.name,
    type: a.type,
    config: null,
    sort_order: a.sort_order,
    options: [],
  }))

  it('AC-010: omits the conditional section unless the control value is true', () => {
    const { rerender } = render(<AttributeLayoutView layout={LAYOUT} attributes={displayable} values={{ toggle: false, months: 12 }} />)
    expect(headings()).toEqual(['General', 'Notes'])

    rerender(<AttributeLayoutView layout={LAYOUT} attributes={displayable} values={{ toggle: true, months: 12 }} />)
    expect(headings()).toEqual(['General', 'Warranty', 'Notes'])
  })
})

describe('attributeLayoutBlobSchema visible_when', () => {
  const parse = (visibleWhen: unknown) =>
    attributeLayoutBlobSchema.safeParse({
      sections: [{ ...section('s', 0, 'a'), ...(visibleWhen === undefined ? {} : { visible_when: visibleWhen }) }],
    })

  it('AC-013: accepts null, an object or the absent key; rejects an object without attribute_code', () => {
    expect(parse(undefined).success).toBe(true)
    expect(parse(null).success).toBe(true)
    expect(parse({ attribute_code: 'toggle' }).success).toBe(true)
    expect(parse({}).success).toBe(false)
    expect(parse({ attribute_code: '' }).success).toBe(false)
  })
})
