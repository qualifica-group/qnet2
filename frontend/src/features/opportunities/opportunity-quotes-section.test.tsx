import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import axios from 'axios'
import i18n from '@/i18n'
import { OpportunityQuotesSection } from '@/features/opportunities/opportunity-quotes-section'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { TableActionDefinition, TableRow, TableRowScope } from '@/features/table/types'

/**
 * Spec 0067: the Opportunity detail's Quotes panel — a thin wiring layer
 * over the SAME `<TableView domain="quotes">`, `deleteQuote` and
 * `useModuleOpener` the standalone Quotes page uses. `TableView` and
 * `useModuleOpener` are stubbed (owned by other teammates, spec'd/tested at
 * their own layer); this suite is scoped to what THIS panel owns: gating,
 * the empty-state/counter discriminant (D-9), the forced-Sheet options and
 * the delete/activity flows (mirrors `quotes-table.test.tsx`).
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const openCreateWithMock = vi.fn()
const openViewMock = vi.fn()
const openEditMock = vi.fn()
const useModuleOpenerMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (...args: unknown[]) => {
    useModuleOpenerMock(...args)
    return {
      openCreate: vi.fn(),
      openCreateWith: openCreateWithMock,
      openView: openViewMock,
      openEdit: openEditMock,
      openDuplicate: vi.fn(),
      sheet: null,
    }
  },
}))

const deleteQuoteMock = vi.fn()
vi.mock('@/features/quotes/api', () => ({
  QUOTES_DOMAIN: 'quotes',
  deleteQuote: (...args: unknown[]) => deleteQuoteMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) },
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: ({ resource, id }: { resource: string; id: number }) => (
    <div>{`activity:${resource}:${id}`}</div>
  ),
}))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; lockedQuoteId?: number | null }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}:${props.lockedQuoteId}`}</div>
  },
}))

const generateQuoteDocumentMock = vi.fn()
vi.mock('@/features/quotes/quote-document-api', () => ({
  generateQuoteDocument: (...args: unknown[]) => generateQuoteDocumentMock(...args),
}))

const ROW: TableRow = {
  id: 9,
  actions: ['view', 'edit', 'delete', 'activity', 'notes', 'generate_document'],
  title: 'Offerta Acme',
  code: 'QUO-0009',
  opportunity: { id: 42, name: 'Opportunita Acme' },
}
const refreshMock = vi.fn()
// Captured on every stub render so a test can simulate the grid re-reporting
// its live total AFTER an imperative `refresh()` (AC-062: the real
// `TableView` purges/refetches on `refresh()`, which re-fires
// `onRowCountChanged` — the stub does not refetch on its own, so the test
// drives that second call explicitly).
let capturedOnRowCountChanged: ((count: number | null) => void) | undefined

interface TableViewStubProps {
  domain: string
  rowScope?: TableRowScope
  onAction?: (action: TableActionDefinition, row: TableRow) => void
  onRowCountChanged?: (count: number | null) => void
  iconMap?: Record<string, unknown>
}

/** Captured so a test can assert the panel advertises the Quotes icon overrides. */
let capturedIconMap: Record<string, unknown> | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, TableViewStubProps>(function TableViewStub(
    { domain, rowScope, onAction, onRowCountChanged, iconMap },
    ref,
  ) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    capturedOnRowCountChanged = onRowCountChanged
    capturedIconMap = iconMap
    return (
      <div role="region" aria-label={`table-${domain}-${rowScope?.opportunityId ?? 'none'}`}>
        {(['view', 'edit', 'delete', 'activity', 'notes', 'generate_document'] as const).map((key) => (
          <button
            key={key}
            type="button"
            onClick={() =>
              onAction?.(
                { key, label: `actions.${key}`, icon: key, type: 'action', confirm: false },
                ROW,
              )
            }
          >
            {key} row
          </button>
        ))}
        <button type="button" onClick={() => onRowCountChanged?.(7)}>
          report count
        </button>
      </div>
    )
  }),
}))

function opportunity(quotesCount: number) {
  return { id: 42, quotes_count: quotesCount }
}

function renderPanel(quotesCount: number) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <OpportunityQuotesSection opportunity={opportunity(quotesCount)} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  openCreateWithMock.mockReset()
  openViewMock.mockReset()
  openEditMock.mockReset()
  useModuleOpenerMock.mockReset()
  deleteQuoteMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
  refreshMock.mockReset()
  notesSectionMock.mockReset()
  generateQuoteDocumentMock.mockReset()
  capturedOnRowCountChanged = undefined
  capturedIconMap = undefined
})

describe('OpportunityQuotesSection — permission gating (AC-040/041)', () => {
  it('renders nothing without quotes.viewAny', () => {
    canMock.mockReturnValue(false)
    renderPanel(3)
    expect(screen.queryByText('Quotes')).not.toBeInTheDocument()
  })

  it('hides "New quote" in both the header and the empty state without quotes.create', () => {
    canMock.mockImplementation((permission) => permission === 'quotes.viewAny')
    renderPanel(0)
    expect(screen.queryByRole('button', { name: 'New quote' })).not.toBeInTheDocument()
  })

  it('shows "New quote" in both the header and the empty state with quotes.create', () => {
    renderPanel(0)
    expect(screen.getAllByRole('button', { name: 'New quote' })).toHaveLength(2)
  })
})

/**
 * Spec 0083 (D-5, AC-053): the gate that used to hide the panel on an
 * opportunity whose products could not proceed to an offer is removed — the
 * panel is visible on any opportunity with `quotes.viewAny`.
 */
describe('OpportunityQuotesSection — visible on any opportunity (AC-053)', () => {
  it('renders the panel regardless of the opportunity', () => {
    renderPanel(3)

    expect(screen.getByRole('region', { name: 'table-quotes-42' })).toBeInTheDocument()
  })
})

describe('OpportunityQuotesSection — empty state discriminant (AC-032)', () => {
  it('shows the empty state instead of the grid when quotes_count is 0', () => {
    renderPanel(0)
    expect(screen.getByText('No quotes yet')).toBeInTheDocument()
    expect(screen.queryByRole('region')).not.toBeInTheDocument()
  })

  it('mounts the grid, not the empty state, when quotes_count is greater than 0', () => {
    renderPanel(3)
    expect(screen.queryByText('No quotes yet')).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'table-quotes-42' })).toBeInTheDocument()
  })
})

describe('OpportunityQuotesSection — the grid mounts scoped to the opportunity (AC-030)', () => {
  it('passes domain="quotes" and rowScope={opportunityId} to TableView', () => {
    renderPanel(3)
    expect(screen.getByRole('region', { name: 'table-quotes-42' })).toBeInTheDocument()
  })
})

describe('OpportunityQuotesSection — counter (AC-031)', () => {
  it('starts from quotes_count and switches to the grid live total once reported', () => {
    renderPanel(3)
    expect(screen.getByLabelText('3 quotes')).toBeInTheDocument()

    act(() => screen.getByRole('button', { name: 'report count' }).click())

    expect(screen.getByLabelText('7 quotes')).toBeInTheDocument()
  })
})

describe('OpportunityQuotesSection — creation reveals the grid (AC-050/054)', () => {
  it('opens the create Sheet with the opportunity id preset', () => {
    renderPanel(0)
    act(() => screen.getAllByRole('button', { name: 'New quote' })[0].click())
    expect(openCreateWithMock).toHaveBeenCalledWith({ opportunity_id: 42 })
  })

  it('reveals the grid once the create Sheet reports a save, without a page reload', () => {
    renderPanel(0)
    expect(screen.queryByRole('region')).not.toBeInTheDocument()

    // No grid was ever mounted at this point, so there is nothing to
    // `refresh()` yet — mounting the grid fresh is what surfaces the newly
    // created quote (its own `useTableConfig`/datasource fetch on mount).
    const onSaved = useModuleOpenerMock.mock.calls[0][1].onSaved as () => void
    act(() => onSaved())

    expect(screen.queryByText('No quotes yet')).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'table-quotes-42' })).toBeInTheDocument()
  })

  it('refreshes the already-mounted grid on a save (edit case, AC-061)', () => {
    renderPanel(3)
    expect(screen.getByRole('region', { name: 'table-quotes-42' })).toBeInTheDocument()

    const onSaved = useModuleOpenerMock.mock.calls[0][1].onSaved as () => void
    act(() => onSaved())

    expect(refreshMock).toHaveBeenCalled()
  })
})

describe('OpportunityQuotesSection — forced Sheet (D-3/AC-060)', () => {
  it('opens the quotes module opener with forceMode "modal"', () => {
    renderPanel(3)
    expect(useModuleOpenerMock).toHaveBeenCalledWith(
      'quotes',
      expect.objectContaining({ forceMode: OPEN_MODE_MODAL }),
    )
  })
})

describe('OpportunityQuotesSection — row actions (AC-060/061/065)', () => {
  it('delegates view to openView', () => {
    renderPanel(3)
    act(() => screen.getByRole('button', { name: 'view row' }).click())
    expect(openViewMock).toHaveBeenCalledWith(ROW)
  })

  it('delegates edit to openEdit', () => {
    renderPanel(3)
    act(() => screen.getByRole('button', { name: 'edit row' }).click())
    expect(openEditMock).toHaveBeenCalledWith(ROW)
  })

  it('opens the activity dialog for the row, reusing ResourceActivityDialog(resource="quotes")', () => {
    renderPanel(3)
    act(() => screen.getByRole('button', { name: 'activity row' }).click())
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText(`activity:quotes:${ROW.id}`)).toBeInTheDocument()
  })

  // Senza queste tre, il pannello del dettaglio divergeva dalle altre due
  // superfici Offerte: `notes`/`generate_document` erano affordance morte e le
  // loro icone cadevano sul fallback `MoreHorizontal`.
  it('advertises the Quotes icon overrides, so notes/generate_document are not the fallback icon', () => {
    renderPanel(3)
    expect(capturedIconMap).toHaveProperty('messages-square')
    expect(capturedIconMap).toHaveProperty('file-text')
  })

  it("opens the notes dialog on the parent Opportunity's thread, locked to the Offerta", () => {
    renderPanel(3)
    act(() => screen.getByRole('button', { name: 'notes row' }).click())

    expect(screen.getByRole('dialog', { name: 'QUO-0009' })).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ entityType: 'request-management', entityId: 42, lockedQuoteId: 9 }),
    )
  })

  it('delegates generate_document to the shared document generation', async () => {
    generateQuoteDocumentMock.mockResolvedValue({ blob: new Blob(), filename: 'QUO-0009.docx' })
    renderPanel(3)

    await act(async () => screen.getByRole('button', { name: 'generate_document row' }).click())

    expect(generateQuoteDocumentMock).toHaveBeenCalledWith(ROW.id, ROW.code)
  })
})

describe('OpportunityQuotesSection — delete (AC-062/063)', () => {
  it('shows a success toast and refreshes the grid on a successful delete', async () => {
    deleteQuoteMock.mockResolvedValue(undefined)
    renderPanel(3)

    await act(async () => screen.getByRole('button', { name: 'delete row' }).click())

    expect(deleteQuoteMock).toHaveBeenCalledWith(ROW.id)
    expect(toastSuccessMock).toHaveBeenCalledWith('Quote deleted successfully.')
    expect(refreshMock).toHaveBeenCalled()
  })

  it('decrements the counter once the refreshed grid reports its new total', async () => {
    deleteQuoteMock.mockResolvedValue(undefined)
    renderPanel(3)
    expect(screen.getByLabelText('3 quotes')).toBeInTheDocument()

    await act(async () => screen.getByRole('button', { name: 'delete row' }).click())
    expect(refreshMock).toHaveBeenCalled()

    // The real `TableView.refresh()` purges the SSRM cache and refetches,
    // which re-fires `onRowCountChanged` with the grid's new total (D-9).
    act(() => capturedOnRowCountChanged?.(2))

    expect(screen.getByLabelText('2 quotes')).toBeInTheDocument()
    expect(screen.queryByLabelText('3 quotes')).not.toBeInTheDocument()
  })

  it('shows the dedicated forbidden toast on a 403', async () => {
    const error = new axios.AxiosError('Forbidden', '403', undefined, undefined, {
      status: 403,
      data: { success: false, message: 'Forbidden' },
    } as never)
    deleteQuoteMock.mockRejectedValue(error)
    renderPanel(3)

    await act(async () => screen.getByRole('button', { name: 'delete row' }).click())

    expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this quote.')
  })
})
