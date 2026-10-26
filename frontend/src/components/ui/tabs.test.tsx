import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Tabs, TabsList, TabsTrigger, TABS_TRIGGER_CLASS } from '@/components/ui/tabs'

const triggerClasses = TABS_TRIGGER_CLASS.split(' ').filter(Boolean)

/** Every tab strip of the app shares one look (decisione utente 2026-10-26). */
describe('tab look', () => {
  it('styles a Radix trigger with the shared compact classes', () => {
    render(
      <Tabs value="a">
        <TabsList>
          <TabsTrigger value="a">Alpha</TabsTrigger>
          <TabsTrigger value="b">Beta</TabsTrigger>
        </TabsList>
      </Tabs>,
    )

    const trigger = screen.getByRole('tab', { name: 'Alpha' })
    expect(trigger).toHaveClass(...triggerClasses)
    expect(trigger).toHaveAttribute('data-state', 'active')
  })

  it('gives the FilterTabs buttons the same classes and active state as a Radix trigger', () => {
    render(
      <FilterTabs
        aria-label="Filtro"
        value="a"
        options={[
          { value: 'a', label: 'Alpha' },
          { value: 'b', label: 'Beta' },
        ]}
        onValueChange={() => undefined}
      />,
    )

    const active = screen.getByRole('tab', { name: 'Alpha' })
    expect(active).toHaveClass(...triggerClasses)
    expect(active).toHaveAttribute('data-state', 'active')
    expect(screen.getByRole('tab', { name: 'Beta' })).toHaveAttribute('data-state', 'inactive')
  })
})
