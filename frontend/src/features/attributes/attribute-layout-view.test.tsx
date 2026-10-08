import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import { AttributeLayoutView } from '@/features/attributes/attribute-layout-view'
import type { DisplayableAttribute } from '@/features/attributes/attribute-value-display'

function attribute(code: string, overrides: Partial<DisplayableAttribute> = {}): DisplayableAttribute {
  return { id: 1, code, name: code, type: 'text', config: null, sort_order: 0, options: [], ...overrides }
}

function section(overrides: Partial<LayoutSection> & Pick<LayoutSection, 'id' | 'title' | 'rows'>): LayoutSection {
  return {
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 1,
    sort_order: 0,
    ...overrides,
  }
}

const ATTRIBUTES = [
  attribute('city', { name: 'City', sort_order: 0 }),
  attribute('zip', { name: 'ZIP', sort_order: 1 }),
  attribute('tier', { name: 'Tier', type: 'enum', sort_order: 2, options: [{ value: 'gold', label: 'Gold' }] }),
]

const LAYOUT: LayoutBlob = {
  sections: [
    section({
      id: 'second',
      title: 'Commercial',
      sort_order: 1,
      collapsible: true,
      default_collapsed: true,
      rows: [{ id: 'r2', items: [{ attribute_code: 'tier', width: 'full' }] }],
    }),
    section({
      id: 'first',
      title: 'Address',
      description: 'Where the work happens',
      variant: 'highlighted',
      columns: 2,
      sort_order: 0,
      rows: [
        {
          id: 'r1',
          items: [
            { attribute_code: 'city', width: 'half' },
            { attribute_code: 'zip', width: 'half' },
          ],
        },
      ],
    }),
  ],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AttributeLayoutView', () => {
  it('without a layout lists every attribute flat, in sort_order, with the empty placeholder when unset', () => {
    render(<AttributeLayoutView layout={null} attributes={[ATTRIBUTES[1], ATTRIBUTES[0]]} values={{ city: 'Napoli' }} />)

    const labels = screen.getAllByRole('term').map((term) => term.textContent)
    expect(labels).toEqual(['City', 'ZIP'])
    expect(screen.getByText('Napoli')).toBeInTheDocument()
    expect(screen.queryByRole('heading')).not.toBeInTheDocument()
  })

  it('renders the configured sections in sort_order with title, description and their values, never form controls', () => {
    render(<AttributeLayoutView layout={LAYOUT} attributes={ATTRIBUTES} values={{ city: 'Napoli', zip: '80100', tier: 'gold' }} />)

    const headings = screen.getAllByRole('heading').map((heading) => heading.textContent)
    expect(headings).toEqual(['Address', 'Commercial'])
    expect(screen.getByText('Where the work happens')).toBeInTheDocument()
    expect(screen.getByText('Gold')).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('renders every field as the label/value row the record sections use, with a heading band per section', () => {
    render(<AttributeLayoutView layout={LAYOUT} attributes={ATTRIBUTES} values={{ city: 'Napoli', zip: '80100' }} />)

    expect(screen.getByText('City').tagName).toBe('DT')
    expect(screen.getByRole('heading', { name: 'Address' }).parentElement?.parentElement).toHaveClass('bg-primary/10')
    expect(screen.getByRole('heading', { name: 'Commercial' }).parentElement?.parentElement).toHaveClass('bg-muted/50')
  })

  it('spreads a section configured on 2+ columns over the full width, half items side by side and full items across', () => {
    const layout: LayoutBlob = {
      sections: [
        section({
          id: 'wide',
          title: 'Wide',
          columns: 2,
          rows: [
            { id: 'r1', items: [{ attribute_code: 'city', width: 'half' }, { attribute_code: 'zip', width: 'half' }] },
            { id: 'r2', items: [{ attribute_code: 'tier', width: 'full' }] },
          ],
        }),
      ],
    }
    render(<AttributeLayoutView layout={layout} attributes={ATTRIBUTES} values={{}} />)

    expect(screen.getByRole('heading', { name: 'Wide' }).closest('section')).toHaveClass('@2xl:col-span-2')
    expect(screen.getByText('City').closest('dl')).toHaveClass('@2xl:grid-cols-2')
    expect(screen.getByText('City').parentElement).not.toHaveClass('@2xl:col-span-2')
    expect(screen.getByText('Tier').parentElement).toHaveClass('@2xl:col-span-2')
  })

  it('keeps a single-column section at half width, its fields one per row', () => {
    render(<AttributeLayoutView layout={LAYOUT} attributes={ATTRIBUTES} values={{ tier: 'gold' }} />)

    expect(screen.getByRole('heading', { name: 'Commercial' }).closest('section')).not.toHaveClass('@2xl:col-span-2')
    expect(screen.getByText('Gold')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('puts the attributes no section places under a trailing "Other information" section', () => {
    const layout: LayoutBlob = { sections: [LAYOUT.sections[1]] }
    render(<AttributeLayoutView layout={layout} attributes={ATTRIBUTES} values={{ tier: 'gold' }} />)

    const headings = screen.getAllByRole('heading').map((heading) => heading.textContent)
    expect(headings).toEqual(['Address', 'Other information'])
    expect(screen.getByText('Gold')).toBeInTheDocument()
  })

  it('drops a section none of whose attributes was handed in', () => {
    render(<AttributeLayoutView layout={LAYOUT} attributes={[ATTRIBUTES[2]]} values={{ tier: 'gold' }} />)

    expect(screen.queryByRole('heading', { name: 'Address' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Commercial' })).toBeInTheDocument()
  })
})
