import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteCostsTab } from '@/features/quotes/quote-costs-tab'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0065 AC-073: the Cost tab's product picker NEVER sends `category_ids`. */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const EMPTY_VALUES: QuoteFormValues = {
  code: 'QUO-0001',
  title: '',
  opportunity_id: 55,
  quote_workflow_status_id: null,
  note: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  manager_slots: [],
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  layout_id: null,
  payment_method_id: null,
  internal_notes: null,
  rewards: [],
  attribute_values: {},
  offer_lines: [],
  cost_lines: [],
}

function Harness() {
  const form = useForm<QuoteFormValues>({ defaultValues: EMPTY_VALUES })
  return (
    <Form {...form}>
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteCostsTab
          control={form.control}
          knownLines={[]}
          vatRatePercentFor={() => null}
          rememberVatRatePercent={vi.fn()}
        />
      </ResourcePermissionsProvider>
    </Form>
  )
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('QuoteCostsTab (spec 0065 AC-073)', () => {
  it('never sends category_ids to the product picker, even with an opportunity selected', async () => {
    render(<Harness />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 product' }))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.anything()))

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.not.objectContaining({ params: expect.anything() }),
    )
  })
})
