import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import {
  QuoteLiveSummary,
  QuoteSummary,
  typologyBucketsFromPersistedSummary,
} from '@/features/quotes/quote-summary'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteSummary as QuoteSummaryData } from '@/features/quotes/types'

/**
 * Spec 0099 (AC-040/041/051/052/060/061): the "Riepilogo per Tipologia
 * Prodotto" card, on both alimentations — the persisted detail block and the
 * form's live preview.
 */

const TYPOLOGY_OPTIONS: ForSelectItem[] = [
  { id: 1, label: 'Consulenza' },
  { id: 2, label: 'Ente' },
  { id: 3, label: 'Formazione' },
]

/** Product 10 and 11 are "Ente" (id 2); product 20 is "Consulenza" (id 1); nothing is "Formazione". */
const TYPOLOGY_BY_PRODUCT: Record<number, number> = { 10: 2, 11: 2, 20: 1 }

const BASE_VALUES: QuoteFormValues = {
  code: 'QUO-0001',
  title: 'Test quote',
  opportunity_id: 1,
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
  // The brief's own example: Ente 2000 + 3000, Consulenza 2500.
  offer_lines: [
    { product_id: 10, quantity: 1, unit_price: 2000, vat_rate_id: null },
    { product_id: 11, quantity: 1, unit_price: 3000, vat_rate_id: null },
    { product_id: 20, quantity: 1, unit_price: 2500, vat_rate_id: null },
  ],
  // A cost row on an "Ente" product: it must NOT reach any bucket (D-6).
  cost_lines: [{ product_id: 10, quantity: 1, unit_price: 400, vat_rate_id: null }],
}

function Harness({
  values = BASE_VALUES,
  options = TYPOLOGY_OPTIONS,
}: {
  values?: QuoteFormValues
  options?: ForSelectItem[]
}) {
  const form = useForm<QuoteFormValues>({ defaultValues: values })
  return (
    <QuoteLiveSummary
      control={form.control}
      vatRatePercentFor={() => null}
      productTypologyIdFor={(productId) => TYPOLOGY_BY_PRODUCT[productId] ?? null}
      typologyOptions={options}
    />
  )
}

/** The card's own subtree, so amounts elsewhere in the summary never match by accident. */
function typologyCard() {
  return screen.getByText(i18n.t('quotes.form.summary.productTypologies')).closest('div')!
    .parentElement!
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLiveSummary — per-typology block (spec 0099)', () => {
  it('AC-041: groups the offer rows by typology and sums them', () => {
    render(<Harness />)
    const card = typologyCard()

    expect(within(card).getByText('Ente')).toBeInTheDocument()
    expect(within(card).getByText('5,000.00')).toBeInTheDocument()
    expect(within(card).getByText('Consulenza')).toBeInTheDocument()
    expect(within(card).getByText('2,500.00')).toBeInTheDocument()
  })

  it('AC-042: a cost row on an Ente product does not change the Ente bucket', () => {
    render(<Harness />)
    // 5,000.00, not 5,400.00 — the cost line is excluded.
    expect(within(typologyCard()).queryByText('5,400.00')).not.toBeInTheDocument()
  })

  it('AC-051: a configured typology with no line still shows, at 0.00', () => {
    render(<Harness />)
    const card = typologyCard()

    expect(within(card).getByText('Formazione')).toBeInTheDocument()
    expect(within(card).getByText('0.00')).toBeInTheDocument()
  })

  it('AC-061: in create mode (no lines yet) every typology shows at 0.00', () => {
    render(<Harness values={{ ...BASE_VALUES, offer_lines: [], cost_lines: [] }} />)
    const card = typologyCard()

    for (const option of TYPOLOGY_OPTIONS) {
      expect(within(card).getByText(option.label)).toBeInTheDocument()
    }
    expect(within(card).getAllByText('0.00')).toHaveLength(TYPOLOGY_OPTIONS.length)
  })

  it('AC-050: the buckets sum to the revenue net shown by the summary', () => {
    render(<Harness />)
    const card = typologyCard()
    const amounts = within(card)
      .getAllByText(/^[\d,]+\.\d{2}$/)
      .map((node) => Number(node.textContent!.replace(/,/g, '')))

    expect(amounts.reduce((sum, value) => sum + value, 0)).toBe(7500)
  })

  it('AC-053: no typology name is hardcoded — the card renders whatever the module returns', () => {
    render(
      <Harness
        values={{ ...BASE_VALUES, offer_lines: [], cost_lines: [] }}
        options={[{ id: 99, label: 'Tipologia Inventata' }]}
      />,
    )

    expect(within(typologyCard()).getByText('Tipologia Inventata')).toBeInTheDocument()
  })
})

describe('typologyBucketsFromPersistedSummary (spec 0099, AC-040)', () => {
  const persisted: QuoteSummaryData = {
    revenue: { net: '7500.00', vat: '0.00', gross: '7500.00' },
    cost: { net: '0.00', vat: '0.00', gross: '0.00' },
    margin: { net: '7500.00' },
    product_typologies: [
      { id: 1, name: 'Consulenza', net: '2500.00' },
      { id: 2, name: 'Ente', net: '5000.00' },
      { id: 3, name: 'Formazione', net: '0.00' },
    ],
  }

  it('maps the decimal strings onto the card shape', () => {
    expect(typologyBucketsFromPersistedSummary(persisted)).toEqual([
      { id: 1, name: 'Consulenza', net: 2500 },
      { id: 2, name: 'Ente', net: 5000 },
      { id: 3, name: 'Formazione', net: 0 },
    ])
  })

  it('AC-040: the detail renders the block from the persisted summary', () => {
    render(
      <QuoteSummary
        totals={totalsFromPersistedSummaryStub()}
        typologyBuckets={typologyBucketsFromPersistedSummary(persisted)}
      />,
    )
    const card = typologyCard()

    expect(within(card).getByText('Ente')).toBeInTheDocument()
    expect(within(card).getByText('5,000.00')).toBeInTheDocument()
    expect(within(card).getByText('Formazione')).toBeInTheDocument()
  })

  it('tolerates a summary without the block (an older cached payload)', () => {
    const legacy = { ...persisted } as QuoteSummaryData
    delete (legacy as { product_typologies?: unknown }).product_typologies

    expect(typologyBucketsFromPersistedSummary(legacy)).toEqual([])
  })
})

/** Minimal totals so `QuoteSummary` renders; this block is not what these cases assert. */
function totalsFromPersistedSummaryStub() {
  return {
    revenue: { net: 7500, vat: 0, gross: 7500 },
    cost: { net: 0, vat: 0, gross: 0 },
    margin: { net: 7500 },
  }
}
