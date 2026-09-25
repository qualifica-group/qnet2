import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import { RelationIdCell } from '@/components/data-table/relation-id-cell'
import { fetchForSelect } from '@/features/for-select/api'

/**
 * Bug 2026-09-25 ("Sede corso" showed a number): an `attr.<code>` relation
 * cell holds the bare stored id (spec 0064 contract), so the cell resolves the
 * label through the column's `/for-select` resource. Only the HTTP boundary is
 * mocked; the label hook runs for real.
 */

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: vi.fn(),
}))

const fetchForSelectMock = vi.mocked(fetchForSelect)

function renderCell(value: unknown) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const params = { value } as unknown as ICellRendererParams
  return render(
    <QueryClientProvider client={client}>
      <RelationIdCell {...params} resource="operational-sites" />
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  fetchForSelectMock.mockReset()
})

describe('RelationIdCell', () => {
  it('resolves a bare id to its label through the column resource', async () => {
    fetchForSelectMock.mockResolvedValue({
      items: [{ id: 3, label: 'Sede Napoli' }],
      export_link: null,
      pagination: { total: 1, offset: 0, limit: 1, total_pages: 1 },
    })
    renderCell(3)

    expect(await screen.findByText('Sede Napoli')).toBeInTheDocument()
    expect(fetchForSelectMock).toHaveBeenCalledWith('operational-sites', expect.objectContaining({ ids: [3] }))
    expect(screen.queryByText('3')).not.toBeInTheDocument()
  })

  it('shows a picked {id, label} projection directly, without a request', () => {
    renderCell({ id: 3, name: 'Sede Napoli' })

    expect(screen.getByText('Sede Napoli')).toBeInTheDocument()
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })

  it('renders the empty cell for a missing value, without a request', () => {
    const { container } = renderCell(null)

    expect(container).not.toHaveTextContent(/\d/)
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})
