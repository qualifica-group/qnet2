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
 * Directive 2026-07-29: Commerciale, Segnalatore and Supervisore are inherited
 * from the picked Opportunita' when creating an Offerta, straight from its
 * for-select `meta` (no extra fetch). A PREFILL, not a lock — the three fields
 * stay editable, and spec 0065 D-3 keeps them a snapshot afterwards.
 * Split out of `quote-form-body.test.tsx` (engineering.md §6): this suite
 * stubs the select, that one exercises the real one.
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

/** An opportunity carrying all three roles, and one carrying none. */
const OPPORTUNITY_WITH_ROLES: OpportunityForSelectItem = {
  id: 55,
  label: 'OPP_55',
  meta: {
    commercial: { id: 71, name: 'Sara Conti' },
    reporter: { id: 81, name: 'Elio Fabbri' },
    supervisor: { id: 61, name: 'Ivo Bianchi' },
  },
}

const OPPORTUNITY_WITHOUT_ROLES: OpportunityForSelectItem = {
  id: 56,
  label: 'OPP_56',
  meta: { commercial: null, reporter: null, supervisor: null },
}

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `lead-form-body-site.test.tsx`). The Opportunity trigger fires
 * `onItemChange` with its `meta`, so the inheritance handler under test is
 * exercisable without a real dropdown.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      {[OPPORTUNITY_WITH_ROLES, OPPORTUNITY_WITHOUT_ROLES].map((item) => (
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
      <button
        type="button"
        onClick={() => {
          onChange(null)
          onItemChange?.(null)
        }}
      >
        {`clear ${labels.triggerLabel}`}
      </button>
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

describe('QuoteFormBody — role inheritance from the picked Opportunity', () => {
  it('fills commercial/reporter/supervisor from the opportunity meta', async () => {
    renderCreateForm()

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITH_ROLES.id}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Commercial')).toHaveTextContent('71'))
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('81')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61')
  })

  it('leaves the three empty for an opportunity with no roles, and re-derives them on every pick', async () => {
    renderCreateForm()

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITH_ROLES.id}` }).click()
    await waitFor(() => expect(screen.getByTestId('value-Commercial')).toHaveTextContent('71'))

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITHOUT_ROLES.id}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Commercial')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('clears the three when the opportunity itself is cleared', async () => {
    renderCreateForm()

    screen.getByRole('button', { name: `select Opportunity ${OPPORTUNITY_WITH_ROLES.id}` }).click()
    await waitFor(() => expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61'))

    screen.getByRole('button', { name: 'clear Opportunity' }).click()

    await waitFor(() => expect(screen.getByTestId('value-Supervisor')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Commercial')).toHaveTextContent('')
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('')
  })
})
