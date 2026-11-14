import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import axios from 'axios'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { fetchQuoteFormContext } from '@/features/quotes/api'
import { useQuoteWithOpportunity } from '@/features/quotes/with-opportunity/use-quote-with-opportunity'
import { checkOpenOpportunity, createQuoteWithOpportunity } from '@/features/quotes/with-opportunity/with-opportunity-api'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * Spec 0396 FE contract of the procedure: the step-1 gate (AC-013/014), the
 * carry-over onto the offer (AC-015, D-5), the single save (AC-016) and the
 * per-block 422 mapping (AC-017). Only the HTTP layer is mocked.
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, fetchQuoteFormContext: vi.fn() }
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const CREATED = { id: 88, opportunity_id: 41, code: 'QUO-0007' } as QuoteDetail

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderWizard() {
  const onSuccess = vi.fn()
  const view = renderHook(
    () => {
      const wizard = useQuoteWithOpportunity({ initialCode: 'QUO-0007', onSuccess })
      // Reading the errors during render subscribes the probe to them.
      const opportunityErrors = wizard.opportunityForm.formState.errors
      const quoteErrors = wizard.quoteForm.form.formState.errors
      return { wizard, opportunityErrors, quoteErrors }
    },
    { wrapper: wrapper() },
  )
  return { ...view, onSuccess }
}

type Rendered = ReturnType<typeof renderWizard>

function fillOpportunity(rendered: Rendered, overrides: { commercial_id?: number | null } = {}) {
  const form = rendered.result.current.wizard.opportunityForm
  act(() => {
    form.setValue('registry_id', 7)
    form.setValue('supervisor_id', 9)
    form.setValue('commercial_id', overrides.commercial_id === undefined ? 3 : overrides.commercial_id)
    form.setValue('reporter_id', 4)
    form.setValue('operational_site_id', 6)
    form.setValue('manager_slots', [11, null, null, null])
    form.setValue('product_lines', [{ root_category_id: null, product_category_id: 21 }])
  })
}

function fillQuote(rendered: Rendered) {
  const { form } = rendered.result.current.wizard.quoteForm
  act(() => {
    form.setValue('offer_lines', [
      { product_id: 9, quantity: 1, unit_of_measure: null, unit_price: 100, vat_rate_id: 1, commissions: [] },
    ])
  })
}

function unprocessable(errors: Record<string, string[]>) {
  return new axios.AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'The given data was invalid.', errors },
  } as never)
}

async function reachQuoteStep(rendered: Rendered) {
  fillOpportunity(rendered)
  await act(async () => {
    await rendered.result.current.wizard.goNext()
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(checkOpenOpportunity).mockReset().mockResolvedValue(null)
  vi.mocked(createQuoteWithOpportunity).mockReset().mockResolvedValue(CREATED)
  vi.mocked(fetchQuoteFormContext).mockReset().mockResolvedValue({ applicable_attributes: [], attribute_layout: null })
})

describe('useQuoteWithOpportunity — the step-1 gate', () => {
  it('AC-013: Next with required fields empty keeps step 1 and puts the errors on its fields', async () => {
    const rendered = renderWizard()

    await act(async () => {
      await rendered.result.current.wizard.goNext()
    })

    expect(rendered.result.current.wizard.step).toBe('opportunity')
    expect(rendered.result.current.opportunityErrors.registry_id).toBeDefined()
    expect(checkOpenOpportunity).not.toHaveBeenCalled()
  })

  it('AC-014: a blocking open opportunity keeps step 1 and is offered as the way out', async () => {
    vi.mocked(checkOpenOpportunity).mockResolvedValue({ id: 5, name: 'Open deal' })
    const rendered = renderWizard()
    fillOpportunity(rendered)

    await act(async () => {
      await rendered.result.current.wizard.goNext()
    })

    expect(checkOpenOpportunity).toHaveBeenCalledWith({ registryId: 7, categoryIds: [21] }, expect.anything())
    expect(rendered.result.current.wizard.step).toBe('opportunity')
    expect(rendered.result.current.wizard.blocking).toEqual({
      id: 5,
      message: 'The registry already has an open opportunity: Open deal.',
    })
  })
})

describe('useQuoteWithOpportunity — step 2', () => {
  it('AC-015: opens on the offer with roles, sede and team carried over from step 1', async () => {
    const rendered = renderWizard()

    await reachQuoteStep(rendered)

    const values = rendered.result.current.wizard.quoteForm.form.getValues()
    expect(rendered.result.current.wizard.step).toBe('quote')
    expect(values.commercial_id).toBe(3)
    expect(values.reporter_id).toBe(4)
    expect(values.supervisor_id).toBe(9)
    expect(values.operational_site_id).toBe(6)
    expect(values.manager_slots.slice(0, 2)).toEqual([11, null])
  })

  it('D-7: the live Attributes follow the product lines of the unsaved opportunity', async () => {
    const rendered = renderWizard()

    await reachQuoteStep(rendered)

    await waitFor(() => expect(fetchQuoteFormContext).toHaveBeenCalledWith(null, [], [21]))
  })

  it('D-5: coming back and forward realigns only what the offer did not touch by hand', async () => {
    const rendered = renderWizard()
    await reachQuoteStep(rendered)
    act(() => {
      rendered.result.current.wizard.quoteForm.form.setValue('commercial_id', 77)
      rendered.result.current.wizard.goBack()
    })
    act(() => {
      rendered.result.current.wizard.opportunityForm.setValue('commercial_id', 30)
      rendered.result.current.wizard.opportunityForm.setValue('reporter_id', 40)
    })

    await act(async () => {
      await rendered.result.current.wizard.goNext()
    })

    const values = rendered.result.current.wizard.quoteForm.form.getValues()
    expect(values.commercial_id).toBe(77)
    expect(values.reporter_id).toBe(40)
  })
})

describe('useQuoteWithOpportunity — the single save', () => {
  it('AC-016: sends ONE {opportunity, quote} payload and hands the created offer to the host', async () => {
    const rendered = renderWizard()
    await reachQuoteStep(rendered)
    fillQuote(rendered)

    await act(async () => {
      await rendered.result.current.wizard.quoteForm.form.handleSubmit(rendered.result.current.wizard.saveBoth)()
    })

    expect(createQuoteWithOpportunity).toHaveBeenCalledTimes(1)
    const payload = vi.mocked(createQuoteWithOpportunity).mock.calls[0][0]
    expect(payload.opportunity).toMatchObject({
      registry_id: 7,
      supervisor_id: 9,
      commercial_id: 3,
      product_lines: [{ product_category_id: 21 }],
    })
    expect(payload.opportunity).not.toHaveProperty('lead_id')
    expect(payload.quote).toMatchObject({ commercial_id: 3, offer_lines: [expect.objectContaining({ product_id: 9 })] })
    expect(payload.quote).not.toHaveProperty('opportunity_id')
    expect(payload.quote).not.toHaveProperty('promote_managers_to_opportunity')
    expect(rendered.onSuccess).toHaveBeenCalledWith(CREATED)
  })

  async function saveRefusedWith(errors: Record<string, string[]>) {
    vi.mocked(createQuoteWithOpportunity).mockRejectedValue(unprocessable(errors))
    const rendered = renderWizard()
    await reachQuoteStep(rendered)
    fillQuote(rendered)
    await act(async () => {
      await rendered.result.current.wizard.quoteForm.form.handleSubmit(rendered.result.current.wizard.saveBoth)()
    })
    return rendered
  }

  it('AC-017: an opportunity.* refusal goes back to step 1 with the error on the field', async () => {
    const rendered = await saveRefusedWith({ 'opportunity.name': ['The name is too long.'] })

    expect(rendered.result.current.wizard.step).toBe('opportunity')
    expect(rendered.result.current.opportunityErrors.name?.message).toBe('The name is too long.')
    expect(rendered.onSuccess).not.toHaveBeenCalled()
  })

  it('AC-017: a quote.* refusal stays on step 2 with the error on the field', async () => {
    const rendered = await saveRefusedWith({ 'quote.offer_lines.0.product_id': ['The product is required.'] })

    expect(rendered.result.current.wizard.step).toBe('quote')
    expect(rendered.result.current.quoteErrors.offer_lines?.message).toBe('The product is required.')
  })

  it('AC-017: the duplicate refusal goes back to step 1 with the existing-opportunity alert', async () => {
    const rendered = await saveRefusedWith({
      'opportunity.registry_id': ['This registry already has an open opportunity.'],
      'opportunity.existing_opportunity_id': ['41'],
    })

    expect(rendered.result.current.wizard.step).toBe('opportunity')
    expect(rendered.result.current.wizard.blocking).toEqual({
      id: 41,
      message: 'This registry already has an open opportunity.',
    })
  })
})
