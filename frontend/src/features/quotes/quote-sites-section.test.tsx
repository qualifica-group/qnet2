import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityForSelectItem } from '@/features/opportunities/for-select-api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Directive 2026-07-30: Societa'/Societa' Sede/Sede operativa on the Offerta
 * form. The sede operativa is PREFILLED from the picked Opportunita' (same
 * rule as the three roles); the Societa' Sede picker is scoped to the chosen
 * Societa' and cleared when it changes. Stubs the select, like
 * `quote-form-opportunity-roles.test.tsx`.
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, createQuote: vi.fn(), updateQuote: vi.fn(), fetchQuoteNextCode: vi.fn() }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const OPPORTUNITY_WITH_SITE: OpportunityForSelectItem = {
  id: 55,
  label: 'OPP_55',
  meta: {
    commercial: null,
    reporter: null,
    supervisor: null,
    operational_site: { id: 91, label: 'Via Ereditata 1 - Milano' },
  },
}

const OPPORTUNITY_WITHOUT_SITE: OpportunityForSelectItem = {
  id: 56,
  label: 'OPP_56',
  meta: { commercial: null, reporter: null, supervisor: null, operational_site: null },
}

/** Two pickable options per stubbed select: ids 55/56 double as the company/site ids too. */
const OPTIONS = [OPPORTUNITY_WITH_SITE, OPPORTUNITY_WITHOUT_SITE]

/**
 * Stubs every single-select, keyed by its accessible trigger label, and
 * exposes both its current value and its `disabled` state so the cascade
 * (site picker locked until a company is chosen) is assertable.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{disabled ? 'yes' : 'no'}</span>
      {OPTIONS.map((item) => (
        <button
          key={item.id}
          type="button"
          onClick={() => {
            onChange(item.id)
            onItemChange?.(item)
          }}
        >
          {`select ${labels.triggerLabel} ${item.id}`}
        </button>
      ))}
    </div>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderCreateForm() {
  render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('QuoteFormBody — company and sites', () => {
  it('prefills the operational site from the picked opportunity, and clears it for one without', async () => {
    renderCreateForm()

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITH_SITE.id}` }).click()
    await waitFor(() => expect(screen.getByTestId('value-Operational site')).toHaveTextContent('91'))

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITHOUT_SITE.id}` }).click()
    await waitFor(() => expect(screen.getByTestId('value-Operational site')).toHaveTextContent(''))
  })

  it('keeps the company site picker locked until a company is picked', async () => {
    renderCreateForm()

    expect(screen.getByTestId('disabled-Company site')).toHaveTextContent('yes')

    screen.getByRole('button', { name: 'select Company 55' }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Company site')).toHaveTextContent('no'))
  })

  it('clears the picked company site when the company changes', async () => {
    renderCreateForm()

    screen.getByRole('button', { name: 'select Company 55' }).click()
    await waitFor(() => expect(screen.getByTestId('value-Company')).toHaveTextContent('55'))

    screen.getByRole('button', { name: 'select Company site 56' }).click()
    await waitFor(() => expect(screen.getByTestId('value-Company site')).toHaveTextContent('56'))

    screen.getByRole('button', { name: 'select Company 56' }).click()

    await waitFor(() => expect(screen.getByTestId('value-Company site')).toHaveTextContent(''))
  })
})
