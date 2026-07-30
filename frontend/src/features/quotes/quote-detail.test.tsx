import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import axios from 'axios'
import i18n from '@/i18n'
import { QuoteDetailView } from '@/features/quotes/quote-detail'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0070 AC-304/AC-314: the "Download quote" button is gated by
 * `quote.permissions.actions.generate_document` (via `quotes.view`
 * server-side, mirrors the existing `view_activity` action-gating
 * convention), shows a loading state while the request is in flight, and
 * the Layout field renders the persisted name or the kit's empty
 * placeholder.
 */

const generateQuoteDocumentMock = vi.fn()
vi.mock('@/features/quotes/quote-document-api', () => ({
  generateQuoteDocument: (...args: unknown[]) => generateQuoteDocumentMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const BASE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { generate_document: true },
}

function quoteFixture(overrides: Partial<QuoteDetailWithPermissions> = {}): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    opportunity_id: 55,
    opportunity: { id: 55, name: 'OPP_55' },
    quote_status_id: 1,
    quote_status: { id: 1, name: 'Bozza', color: 'slate', group: 'open' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    company_id: null,
    company: null,
    company_site_id: null,
    company_site: null,
    operational_site_id: null,
    operational_site: null,
    layout_id: null,
    layout: null,
    internal_notes: null,
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: BASE_PERMISSIONS,
    ...overrides,
  }
}

/** Reads a `DetailField`'s value (the sibling `<dd>` of its `<dt>` label). */
function detailValueFor(label: string): string | null {
  const dt = screen.getByText(label)
  return dt.parentElement?.querySelector('dd')?.textContent ?? null
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  generateQuoteDocumentMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('QuoteDetailView — Download quote button (AC-304)', () => {
  it('shows the button when permissions.actions.generate_document is true', () => {
    render(<QuoteDetailView quote={quoteFixture()} />)
    expect(screen.getByRole('button', { name: 'Download quote' })).toBeInTheDocument()
  })

  it('hides the button when permissions.actions.generate_document is false', () => {
    const quote = quoteFixture({ permissions: { ...BASE_PERMISSIONS, actions: { generate_document: false } } })
    render(<QuoteDetailView quote={quote} />)
    expect(screen.queryByRole('button', { name: 'Download quote' })).not.toBeInTheDocument()
  })

  it('generates the document and shows a success toast on click', async () => {
    generateQuoteDocumentMock.mockResolvedValue(undefined)
    render(<QuoteDetailView quote={quoteFixture()} />)

    screen.getByRole('button', { name: 'Download quote' }).click()

    await waitFor(() => expect(generateQuoteDocumentMock).toHaveBeenCalledWith(9, 'QUO-0009'))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Word document generated successfully.'))
  })

  it("shows the backend's message on a 422 (AC-303)", async () => {
    const error = new axios.AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: { success: false, message: 'No layout is available to generate this document.' },
    } as never)
    generateQuoteDocumentMock.mockRejectedValue(error)
    render(<QuoteDetailView quote={quoteFixture()} />)

    screen.getByRole('button', { name: 'Download quote' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('No layout is available to generate this document.'),
    )
  })
})

describe('QuoteDetailView — Layout field (AC-314)', () => {
  it('shows the persisted layout name', () => {
    const quote = quoteFixture({ layout_id: 12, layout: { id: 12, name: 'Offerta economica' } })
    render(<QuoteDetailView quote={quote} />)
    expect(detailValueFor('Layout')).toBe('Offerta economica')
  })

  it('shows the empty placeholder when no layout is set', () => {
    render(<QuoteDetailView quote={quoteFixture()} />)
    expect(detailValueFor('Layout')).toBe('—')
  })
})
