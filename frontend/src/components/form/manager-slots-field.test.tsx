import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'

/**
 * Spec 0080: `labels` overrides the per-slot denomination the caller
 * resolved (e.g. a Product Category's configured G.A. labels), without
 * touching `value`/`selectedItems`/slot logic. AC-043 pins the Registries
 * path (no `labels` prop at all) to today's exact default strings — the
 * non-regression this component's callers rely on. Amendment A1: the slot
 * ceiling moved from 4 to `MAX_MANAGER_SLOTS` (12); "Add slot" disables at it.
 */

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button" aria-label={labels.triggerLabel} />
  ),
}))

function renderField(options: { labels?: Record<number, string>; value?: (number | null)[] } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const value = options.value ?? [null, null]
  return render(
    <QueryClientProvider client={client}>
      <ManagerSlotsField value={value} onChange={vi.fn()} selectedItems={[]} labels={options.labels} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ManagerSlotsField', () => {
  it('AC-043: with no `labels` prop, every slot keeps the default "Account manager n" name (Registries non-regression)', () => {
    renderField()

    expect(screen.getByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  it('AC-044: a configured position overrides only its own slot, the rest keep the default', () => {
    renderField({ labels: { 1: 'Commercial' } })

    expect(screen.getByRole('button', { name: 'Commercial' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  it('carries the same override into the slot number badge, unconfigured slots keep the default tooltip', () => {
    const { container } = renderField({ labels: { 1: 'Commercial' } })

    expect(container.querySelector('[title="Commercial"]')).not.toBeNull()
    expect(container.querySelector('[title="Account manager 2"]')).not.toBeNull()
  })

  it('shows the resolved denominations as visible text, unconfigured positions keeping the default name', () => {
    renderField({ labels: { 1: 'Commercial' } })

    expect(screen.getByText('Commercial')).toBeInTheDocument()
    expect(screen.getByText('Account manager 2')).toBeInTheDocument()
  })

  it('keeps the compact number badge when the caller resolved no denomination (Registries non-regression)', () => {
    renderField()

    expect(screen.getByText('1')).toBeInTheDocument()
    expect(screen.queryByText('Account manager 1')).toBeNull()
  })

  it('AC-052/AC-053: renders all 12 slots and their overrides, including positions past the 4th', () => {
    renderField({
      value: Array.from({ length: MAX_MANAGER_SLOTS }, () => null),
      labels: { 5: 'Field consultant', 12: 'Tutor' },
    })

    expect(screen.getByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Field consultant' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Tutor' })).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /Account manager \d+|Field consultant|Tutor/ })).toHaveLength(
      MAX_MANAGER_SLOTS,
    )
  })

  it('"Add" stays enabled below the ceiling', () => {
    renderField({ value: Array.from({ length: MAX_MANAGER_SLOTS - 1 }, () => null) })

    expect(screen.getByRole('button', { name: 'Add account manager' })).toBeEnabled()
  })

  it('"Add" disables once the array reaches MAX_MANAGER_SLOTS', () => {
    renderField({ value: Array.from({ length: MAX_MANAGER_SLOTS }, () => null) })

    expect(screen.getByRole('button', { name: 'Add account manager' })).toBeDisabled()
  })
})
