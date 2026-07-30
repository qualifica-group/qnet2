import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConvertLeadsDialog } from '@/features/leads/convert-leads-dialog'
import type { TableRow } from '@/features/table/types'

/**
 * Confirm popup of the leads mass conversion (spec 0071). Covers what this
 * component alone owns: the selection summary, the client-side block on
 * leads that already have an opportunity, the inline rendering of the
 * server's blocker list, and the pending/close-on-success gating. The wiring
 * into the table is covered by `leads-table-convert.test.tsx`.
 */

const convertLeadsToOpportunitiesMock = vi.fn()

vi.mock('@/features/leads/api', () => ({
  convertLeadsToOpportunities: (...args: unknown[]) => convertLeadsToOpportunitiesMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function leadRow(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 1, actions: [], registry: { id: 1, name: 'Acme' }, ...overrides }
}

/** A 422 refusal, in the exact envelope the backend answers with. */
function blockedError(blockers: Array<{ id: number; reason: string }>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'blocked', errors: { reason: 'not_convertible', blockers } },
    statusText: 'Unprocessable Content',
    headers: {},
    config: { headers: undefined as never },
  })
}

const onConverted = vi.fn()
const onOpenChange = vi.fn()

function renderDialog(rows: TableRow[]) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConvertLeadsDialog
        open
        onOpenChange={onOpenChange}
        rows={rows}
        onConverted={onConverted}
      />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ConvertLeadsDialog', () => {
  it('AC-032: states how many leads will be converted without calling the API', () => {
    renderDialog([leadRow({ id: 11 }), leadRow({ id: 22 })])

    expect(screen.getByText(/2 selected lead\(s\)/i)).toBeInTheDocument()
    expect(convertLeadsToOpportunitiesMock).not.toHaveBeenCalled()
  })

  it('AC-033: blocks the confirm when the selection holds an already converted lead', () => {
    renderDialog([
      leadRow({ id: 11 }),
      leadRow({ id: 22, lead_status: 'converted_to_opportunity', registry: { id: 9, name: 'Globex' } }),
    ])

    expect(screen.getByRole('alert')).toHaveTextContent('1 selected lead(s) already have an opportunity')
    expect(screen.getByRole('alert')).toHaveTextContent('Globex')
    expect(screen.getByRole('button', { name: 'Convert' })).toBeDisabled()
  })

  it('AC-034: posts every selected id on confirm', async () => {
    convertLeadsToOpportunitiesMock.mockResolvedValue({ converted: 2, opportunity_ids: [5, 6] })
    renderDialog([leadRow({ id: 11 }), leadRow({ id: 22 })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() =>
      expect(convertLeadsToOpportunitiesMock).toHaveBeenCalledWith({ lead_ids: [11, 22] }),
    )
  })

  it('AC-035: reports the conversion and closes on success', async () => {
    convertLeadsToOpportunitiesMock.mockResolvedValue({ converted: 2, opportunity_ids: [5, 6] })
    renderDialog([leadRow({ id: 11 }), leadRow({ id: 22 })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() => expect(onConverted).toHaveBeenCalledWith(2))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('AC-036: lists the refused leads inline and stays open', async () => {
    convertLeadsToOpportunitiesMock.mockRejectedValue(
      blockedError([{ id: 22, reason: 'not_derivable' }]),
    )
    renderDialog([leadRow({ id: 11 }), leadRow({ id: 22, registry: { id: 9, name: 'Globex' } })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument())
    expect(screen.getByRole('alert')).toHaveTextContent('Globex')
    expect(screen.getByRole('alert')).toHaveTextContent(
      'its campaign has no business function or product category',
    )
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('AC-036: falls back to the lead id when the Anagrafica is empty', async () => {
    convertLeadsToOpportunitiesMock.mockRejectedValue(
      blockedError([{ id: 22, reason: 'already_converted' }]),
    )
    renderDialog([leadRow({ id: 22, registry: null })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('#22'))
  })

  it('AC-037: toasts a generic error on an unexpected failure', async () => {
    convertLeadsToOpportunitiesMock.mockRejectedValue(new Error('network down'))
    renderDialog([leadRow({ id: 11 })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to convert the leads. Please try again.'),
    )
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
  })

  it('AC-038: disables the confirm while the batch is running', async () => {
    convertLeadsToOpportunitiesMock.mockImplementation(() => new Promise(() => {}))
    renderDialog([leadRow({ id: 11 })])

    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Converting…' })).toBeDisabled())
  })
})
