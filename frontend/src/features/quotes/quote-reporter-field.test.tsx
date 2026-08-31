import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { QuoteReporterField } from '@/features/quotes/quote-reporter-field'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/**
 * User directive 2026-08-31: "Segnalatore diritto al buono anche su offerta,
 * cosi' come opportunita'". The control itself is the SHARED
 * `ReporterRewardsField` (its own interaction suite lives in
 * `reward-assignment-field.test.tsx`); what this file pins is the Offerta
 * form's own wiring — the block hangs off `reporter_id`, hydrates from the
 * offer's persisted assignments, and reads the `quotes.form.rewards.*`
 * namespace.
 */

// `RelationSelectField` renders a `<Can>` gate for the quick-create
// affordance; stubbing the abilities hook keeps this a focused unit test
// instead of mounting a whole AuthProvider (same technique as
// `contract-related-links.test.tsx`).
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: vi
      .fn()
      .mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
  }
})

const LABELS = {
  placeholder: 'Seleziona',
  emptyLabel: 'Nessun risultato',
  errorLabel: 'Errore',
  clearLabel: 'Rimuovi',
  retryLabel: 'Riprova',
}

function Harness({
  reporterId,
  rewards = [],
  initialRewards,
}: {
  reporterId: number | null
  rewards?: { reward_type_id: number }[]
  initialRewards?: RewardAssignmentRef[]
}) {
  const form = useForm<QuoteFormValues>({
    defaultValues: { reporter_id: reporterId, rewards } as QuoteFormValues,
  })

  return (
    <Form {...form}>
      <QuoteReporterField
        control={form.control}
        setValue={form.setValue}
        selected={reporterId === null ? null : { id: reporterId, name: 'Mario Rossi' }}
        initialRewards={initialRewards}
        labels={LABELS}
      />
    </Form>
  )
}

function renderField(props: Parameters<typeof Harness>[0]) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('QuoteReporterField', () => {
  it('hides the reward block until a Segnalatore is picked', () => {
    renderField({ reporterId: null })

    expect(screen.queryByText('Buoni assegnati')).not.toBeInTheDocument()
    expect(screen.getByText('Segnalatore')).toBeInTheDocument()
  })

  it('reveals the reward block once a Segnalatore is set', () => {
    renderField({ reporterId: 42 })

    expect(screen.getByText('Buoni assegnati')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aggiungi buono' })).toBeInTheDocument()
  })

  it('hydrates the persisted assignments of the offer being edited', () => {
    renderField({
      reporterId: 42,
      rewards: [{ reward_type_id: 3 }],
      initialRewards: [
        { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-08-01', notes: null },
      ],
    })

    expect(screen.getByText('Amazon 10€')).toBeInTheDocument()
  })

  it('keeps the block mounted, with the hint, when the reporter is cleared while chips remain', () => {
    renderField({ reporterId: null, rewards: [{ reward_type_id: 3 }] })

    expect(screen.getByText('Buoni assegnati')).toBeInTheDocument()
    expect(
      screen.getByText('Seleziona prima un segnalatore per assegnare un buono.'),
    ).toBeInTheDocument()
  })
})
