import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { QuoteFormScreen } from '@/features/quotes/quote-screens'
import type { ModuleFormScreenMode } from '@/features/modules/types'
import type { QuoteFormMode } from '@/features/quotes/types'

/**
 * Spec 0067 AC-050/AC-053: `QuoteFormScreen`'s create branch must read
 * `opportunity_id` from `mode.params` (the sole channel a `FormScreen` gets
 * create-time context through, spec 0045) and normalize it with
 * `parseEntityId`, mirroring `OpportunityFormScreen`'s `lead_id` relay
 * (`opportunity-screens.test.tsx`). Only the terminal `QuoteForm` is stubbed;
 * its own behaviour (prefill/lock/inheritance) is covered by
 * `quote-form-opportunity-params.test.tsx`.
 */

vi.mock('@/features/quotes/quote-form', () => ({
  QuoteForm: ({ mode }: { mode: QuoteFormMode }) => (
    <p>
      form ready, opportunity {mode.type === 'create' ? String(mode.params?.opportunity_id ?? 'none') : 'n/a'}
    </p>
  ),
  QuoteFormSkeleton: () => <p>loading</p>,
}))

function renderScreen(mode: ModuleFormScreenMode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <QuoteFormScreen mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </QueryClientProvider>,
  )
}

describe('QuoteFormScreen create adapter (spec 0067)', () => {
  it('AC-050: reads a numeric opportunity_id from mode.params (modal caller)', () => {
    renderScreen({ type: 'create', params: { opportunity_id: 55 } })

    expect(screen.getByText('form ready, opportunity 55')).toBeInTheDocument()
  })

  it('reads a string opportunity_id from mode.params identically (query-string caller)', () => {
    renderScreen({ type: 'create', params: { opportunity_id: '55' } })

    expect(screen.getByText('form ready, opportunity 55')).toBeInTheDocument()
  })

  it('AC-053: with no params, opens a free create with no forced opportunity', () => {
    renderScreen({ type: 'create' })

    expect(screen.getByText('form ready, opportunity none')).toBeInTheDocument()
  })
})
