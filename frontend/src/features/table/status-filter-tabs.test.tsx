import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import '@/i18n'
import { StatusFilterTabs } from '@/features/table/status-filter-tabs'

const STATUSES = ['Da validare', 'Programmato', 'Disdetto']

describe('StatusFilterTabs', () => {
  it('offers all first then each status, and reports the pick', () => {
    const onSelect = vi.fn()
    render(<StatusFilterTabs statuses={STATUSES} active="" onSelect={onSelect} label="Stati" />)

    expect(screen.getByRole('tablist', { name: 'Stati' })).toBeInTheDocument()
    const tabs = screen.getAllByRole('tab')
    expect(tabs.map((tab) => tab.textContent)).toEqual(['Tutti', ...STATUSES])
    expect(tabs[0]).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(tabs[3])
    expect(onSelect).toHaveBeenCalledWith('Disdetto')
  })

  it('shows the count of each status tab, none on all and none when zero', () => {
    render(
      <StatusFilterTabs
        statuses={STATUSES}
        active="Programmato"
        counts={{ 'Da validare': 1200, Programmato: 3 }}
        onSelect={vi.fn()}
        label="Stati"
      />,
    )

    const [all, toValidate, scheduled, terminated] = screen.getAllByRole('tab')
    expect(all.textContent).toBe('Tutti')
    expect(toValidate.textContent).toMatch(/^Da validare \(1\.?200\)$/)
    expect(scheduled.textContent).toBe('Programmato (3)')
    expect(scheduled).toHaveAttribute('aria-selected', 'true')
    expect(terminated.textContent).toBe('Disdetto')
  })

  it('selects no tab when the grid filter matches none of them', () => {
    render(<StatusFilterTabs statuses={STATUSES} active={null} onSelect={vi.fn()} label="Stati" />)

    expect(screen.getAllByRole('tab').every((tab) => tab.getAttribute('aria-selected') === 'false')).toBe(true)
  })
})
