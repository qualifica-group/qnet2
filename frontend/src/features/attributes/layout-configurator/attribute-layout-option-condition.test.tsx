import { useState } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { AttributeLayoutConfigurator } from '@/features/attributes/layout-configurator/attribute-layout-configurator'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/** Spec 0424 AC-005: a section opened by any of some entries of a checkbox list, configured and previewed. */

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
    config: type === 'enum' ? { display: 'checklist' } : null,
    relation_target: null,
    is_required: false,
    sort_order: sortOrder,
    inherited: false,
    context: 'quote',
    options:
      type === 'enum'
        ? ['Food', 'Energy', 'Brc'].map((label, index) => ({
            value: label.toLowerCase(),
            label,
            color: null,
            icon: null,
            sort_order: index,
            is_default: false,
          }))
        : [],
  }
}

const ATTRIBUTES = [attribute('groups', 'Groups', 'enum', 0), attribute('food_note', 'Food note', 'text', 1)]

function makeSection(id: string, title: string, codes: string[], visibleWhen: LayoutSection['visible_when'] = null): LayoutSection {
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
    visible_when: visibleWhen,
  }
}

function Harness({ initialBlob, onSave }: { initialBlob: LayoutBlob; onSave?: (blob: LayoutBlob) => void }) {
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

describe('section condition on checkbox-list entries', () => {
  it('picks the choice attribute, then the entries that open the section', async () => {
    const onSave = vi.fn()
    render(
      <Harness
        onSave={onSave}
        initialBlob={{ sections: [makeSection('standards', 'Standards', ['groups']), makeSection('food', 'Food block', ['food_note'])] }}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox', { name: 'Visible only if active' })[1])
    fireEvent.click(await screen.findByRole('option', { name: 'Groups' }))
    expect(onSave.mock.lastCall?.[0].sections[1].visible_when).toEqual({ attribute_code: 'groups', option_values: [] })

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Entries that open it' }), { button: 0, ctrlKey: false })
    fireEvent.click(await screen.findByRole('menuitemcheckbox', { name: 'Food' }))
    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: 'Brc' }))

    expect(onSave.mock.lastCall?.[0].sections[1].visible_when).toEqual({ attribute_code: 'groups', option_values: ['food', 'brc'] })
  })

  it('shows the section in the preview while any of its entries is ticked', () => {
    render(
      <Harness
        initialBlob={{
          sections: [
            makeSection('standards', 'Standards', ['groups']),
            makeSection('food', 'Food block', ['food_note'], { attribute_code: 'groups', option_values: ['food', 'brc'] }),
          ],
        }}
      />,
    )

    expect(screen.queryByRole('heading', { name: 'Food block' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Energy' }))
    expect(screen.queryByRole('heading', { name: 'Food block' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Brc' }))
    expect(screen.getByRole('heading', { name: 'Food block' })).toBeInTheDocument()
    expect(screen.getByText('Visible if Groups holds at least one of: Food, Brc')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Food' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Brc' }))
    expect(screen.getByRole('heading', { name: 'Food block' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Food' }))
    expect(screen.queryByRole('heading', { name: 'Food block' })).not.toBeInTheDocument()
  })
})
