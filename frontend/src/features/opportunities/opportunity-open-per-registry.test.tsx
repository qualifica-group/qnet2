import { beforeAll, beforeEach, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, render, renderHook, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ExistingOpportunityAlert } from '@/features/opportunities/existing-opportunity-alert'
import {
  useOpportunityForm,
  useOpportunityFormSubmit,
  type OpportunityFormValues,
} from '@/features/opportunities/use-opportunity-form'

/**
 * User directive 2026-08-31: creating an opportunity for an anagrafica that
 * already has an open one is refused, and the refusal links to the opportunity
 * that blocks it. The rule itself lives server-side
 * (RegistryOpenOpportunityGuard); what is under test here is the form reading
 * the 422 it answers with.
 */

const createOpportunityMock = vi.fn()

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** A submittable create payload: the rule under test fires server-side, after the schema. */
const VALUES = {
  registry_id: 5,
  referent_id: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: 1,
  source_id: null,
  operational_site_id: null,
  state_id: null,
  product_lines: [{ business_function_id: 1, product_category_id: 2 }],
  products_of_interest: [3],
  manager_slots: [],
  rewards: [],
  start_date: null,
  expected_close_date: null,
  estimated_value: null,
  success_probability: null,
  general_notes: null,
} as unknown as OpportunityFormValues

const BLOCKING_MESSAGE =
  "Questa anagrafica ha già un'opportunità aperta: OPP_12. Aggiungi l'offerta a quella, invece di creare un'opportunità duplicata."

/** The envelope the API answers the refusal with (BaseApiController::fail). */
function openOpportunityRefusal() {
  return {
    isAxiosError: true,
    response: {
      status: 422,
      data: {
        success: false,
        message: BLOCKING_MESSAGE,
        errors: {
          registry_id: [BLOCKING_MESSAGE],
          existing_opportunity_id: ['12'],
        },
      },
    },
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderSubmit() {
  return renderHook(
    () => {
      const { form } = useOpportunityForm({ mode: { type: 'create' } })
      return useOpportunityFormSubmit({
        form,
        mode: { type: 'create' },
        leadSubmission: { blocked: false, fromLead: null },
        onSuccess: vi.fn(),
      })
    },
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createOpportunityMock.mockReset()
})

it('surfaces the blocking opportunity carried by the 422', async () => {
  createOpportunityMock.mockRejectedValue(openOpportunityRefusal())
  const { result } = renderSubmit()

  await act(async () => {
    await result.current.onSubmit(VALUES)
  })

  expect(result.current.blockingOpportunity).toEqual({
    id: 12,
    message: BLOCKING_MESSAGE,
    // The products the refused form carried: what the offer on OPP_12 starts from.
    productIds: [3],
  })
  // The dedicated alert owns the message: it is not repeated as a generic error.
  expect(result.current.serverError).toBeNull()
})

it('falls back to the generic error when the 422 carries no blocking opportunity', async () => {
  createOpportunityMock.mockRejectedValue(new Error('network down'))
  const { result } = renderSubmit()

  await act(async () => {
    await result.current.onSubmit(VALUES)
  })

  expect(result.current.blockingOpportunity).toBeNull()
  expect(result.current.serverError).not.toBeNull()
})

it('links to the opportunity that blocks the create', () => {
  render(
    <MemoryRouter>
      <ExistingOpportunityAlert opportunityId={12} message={BLOCKING_MESSAGE} />
    </MemoryRouter>,
  )

  expect(screen.getByRole('alert')).toHaveTextContent(BLOCKING_MESSAGE)
  expect(screen.getByRole('link')).toHaveAttribute('href', '/opportunities/12')
})

it('offers to add the offer on the blocking opportunity, products included', () => {
  render(
    <MemoryRouter>
      <ExistingOpportunityAlert opportunityId={12} message={BLOCKING_MESSAGE} productIds={[3, 4]} />
    </MemoryRouter>,
  )

  expect(screen.getByRole('link', { name: "Aggiungi l'offerta a questa opportunità" })).toHaveAttribute(
    'href',
    '/quotes/new?opportunity_id=12&product_ids=3%2C4',
  )
  // The plain "go there" link stays available beside it.
  expect(screen.getByRole('link', { name: "Vai all'opportunità" })).toHaveAttribute(
    'href',
    '/opportunities/12',
  )
})

it('omits the offer shortcut when the refused form carried no product', () => {
  render(
    <MemoryRouter>
      <ExistingOpportunityAlert opportunityId={12} message={BLOCKING_MESSAGE} productIds={[]} />
    </MemoryRouter>,
  )

  expect(screen.getAllByRole('link')).toHaveLength(1)
})
