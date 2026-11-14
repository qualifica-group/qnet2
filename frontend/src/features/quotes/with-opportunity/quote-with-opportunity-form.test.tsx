import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { QuoteWithOpportunityForm } from '@/features/quotes/with-opportunity/quote-with-opportunity-form'
import { checkOpenOpportunity } from '@/features/quotes/with-opportunity/with-opportunity-api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0396 D-2 shell: the two-step strip, the step-1 gate on screen (AC-013)
 * and the absence of the Lead. The HTTP layer is mocked; the opportunity and
 * offer sections are the real ones.
 */

const FULL_ACCESS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ isPending: false, isError: false, data: { permissions: FULL_ACCESS } }),
}))

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return {
    ...actual,
    fetchQuoteNextCode: vi.fn().mockResolvedValue('QUO-0007'),
    fetchQuoteFormContext: vi.fn().mockResolvedValue({ applicable_attributes: [], attribute_layout: null }),
  }
})

vi.mock('@/features/quotes/with-opportunity/with-opportunity-api', () => ({
  checkOpenOpportunity: vi.fn(),
  createQuoteWithOpportunity: vi.fn(),
}))

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: () =>
      Promise.resolve({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
  }
})

const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (...args: unknown[]) => toastErrorMock(...args) } }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  toastErrorMock.mockReset()
  vi.mocked(checkOpenOpportunity).mockReset()
})

describe('QuoteWithOpportunityForm', () => {
  it('opens on step 1 with the two-step strip and no Lead section', async () => {
    const onCancel = vi.fn()
    render(<QuoteWithOpportunityForm onSuccess={vi.fn()} onCancel={onCancel} />, { wrapper: wrapper() })

    const opportunityTab = await screen.findByRole('tab', { name: 'Opportunity' })

    expect(opportunityTab).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Quote' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.queryByText(i18n.t('opportunities.form.sections.lead.title'))).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Next' }).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
  })

  it('AC-013: Next with the required fields empty stays on step 1, without the duplicate check', async () => {
    render(<QuoteWithOpportunityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })
    await screen.findByRole('tab', { name: 'Opportunity' })

    fireEvent.click(screen.getAllByRole('button', { name: 'Next' })[0])

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalled())
    expect(screen.getByRole('tab', { name: 'Opportunity' })).toHaveAttribute('aria-selected', 'true')
    expect(checkOpenOpportunity).not.toHaveBeenCalled()
  })

  it('asking for the Quote tab runs the same gate', async () => {
    render(<QuoteWithOpportunityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })
    await screen.findByRole('tab', { name: 'Opportunity' })

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Quote' }))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalled())
    expect(screen.getByRole('tab', { name: 'Opportunity' })).toHaveAttribute('aria-selected', 'true')
  })

  it('Cancel hands over to the host (its leave guard)', async () => {
    const onCancel = vi.fn()
    render(<QuoteWithOpportunityForm onSuccess={vi.fn()} onCancel={onCancel} />, { wrapper: wrapper() })
    await screen.findByRole('tab', { name: 'Opportunity' })

    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])

    expect(onCancel).toHaveBeenCalledTimes(1)
  })
})
