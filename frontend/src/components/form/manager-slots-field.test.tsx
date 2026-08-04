import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'

/**
 * Spec 0080: `labels` overrides the per-slot denomination the caller
 * resolved (e.g. a Product Category's configured G.A. labels), without
 * touching `value`/`selectedItems`/slot logic. AC-043 pins the Registries
 * path (no `labels` prop at all) to today's exact default strings — the
 * non-regression this component's callers rely on.
 */

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button" aria-label={labels.triggerLabel} />
  ),
}))

function renderField(labels?: Record<number, string>) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ManagerSlotsField value={[null, null]} onChange={vi.fn()} selectedItems={[]} labels={labels} />
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
    renderField({ 1: 'Commercial' })

    expect(screen.getByRole('button', { name: 'Commercial' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  it('carries the same override into the slot number badge, unconfigured slots keep the default tooltip', () => {
    const { container } = renderField({ 1: 'Commercial' })

    expect(container.querySelector('[title="Commercial"]')).not.toBeNull()
    expect(container.querySelector('[title="Account manager 2"]')).not.toBeNull()
  })
})
