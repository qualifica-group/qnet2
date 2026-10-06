import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import { BellRing, FileDown, Mail } from 'lucide-react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { InvoiceEmailComposerHost } from '@/features/invoices/invoice-email-composer-host'
import { useInvoiceActionState } from '@/features/invoices/use-invoice-action-state'
import { useInvoiceRowActions } from '@/features/invoices/use-invoice-row-actions'
import { createRowActionsRenderer } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const downloadInvoicePdfMock = vi.fn()
vi.mock('@/features/invoices/invoice-pdf-api', () => ({
  downloadInvoicePdf: (...args: unknown[]) => downloadInvoicePdfMock(...args),
}))

const createDraftMock = vi.fn()
const createReminderMock = vi.fn()
vi.mock('@/features/outbound-emails/api', () => ({
  outboundEmailsListQueryKey: (owner: { type: string; id: number }) => ['emails', owner.type, owner.id, 'list'],
  outboundEmailQueryKey: (owner: { type: string; id: number }, id: number) => ['emails', owner.type, owner.id, id],
  createOutboundEmailDraft: (...args: unknown[]) => createDraftMock(...args),
  createOutboundEmailReminder: (...args: unknown[]) => createReminderMock(...args),
}))

vi.mock('@/features/outbound-emails/outbound-email-composer-dialog', () => ({
  OutboundEmailComposerDialog: (props: { owner: { type: string; id: number }; emailId: number; justCreated: boolean; prefillDefaultTo?: boolean }) => (
    <div>{`composer:${props.owner.type}:${props.owner.id}:${props.emailId}:justCreated=${props.justCreated}:prefill=${props.prefillDefaultTo === true}`}</div>
  ),
}))

const CATALOG: TableActionDefinition[] = [
  { key: 'pdf', label: 'invoices.actions.pdf', icon: 'file-down', type: 'action', confirm: false },
  { key: 'email', label: 'invoices.actions.email', icon: 'mail', type: 'action', confirm: false },
  { key: 'remind', label: 'invoices.actions.remind', icon: 'bell-ring', type: 'action', confirm: false },
]
const ICONS = { 'file-down': FileDown, mail: Mail, 'bell-ring': BellRing }

function Harness({ row }: { row: TableRow }) {
  const actions = useInvoiceRowActions()
  const resolveActionState = useInvoiceActionState()
  const Cell = createRowActionsRenderer(CATALOG, actions.handleAction, { resolveActionState, iconMap: ICONS })
  return (
    <>
      {/* eslint-disable-next-line react-hooks/static-components -- test harness: the factory needs the hook handler */}
      <Cell {...({ data: row } as ICellRendererParams)} />
      <InvoiceEmailComposerHost target={actions.emailFlow.target} onClose={actions.emailFlow.closeComposer} />
    </>
  )
}

function renderRow(paymentStatus: string) {
  const row: TableRow = { id: 7, actions: ['pdf', 'email', 'remind'], payment_status: paymentStatus }
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <Harness row={row} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  downloadInvoicePdfMock.mockReset().mockResolvedValue(undefined)
  createDraftMock.mockReset().mockResolvedValue({ id: 55 })
  createReminderMock.mockReset().mockResolvedValue({ id: 66 })
})

describe('invoice document row actions', () => {
  it('downloads the PDF of the row', async () => {
    renderRow('not_due')
    fireEvent.click(screen.getByRole('button', { name: 'Download PDF' }))
    await waitFor(() => expect(downloadInvoicePdfMock).toHaveBeenCalledWith(7))
  })

  it('creates a draft with the PDF attached and opens the composer on it', async () => {
    renderRow('not_due')
    fireEvent.click(screen.getByRole('button', { name: 'Send email' }))
    await screen.findByText('composer:invoices:7:55:justCreated=true:prefill=true')
    expect(createDraftMock).toHaveBeenCalledWith({ type: 'invoices', id: 7 }, { attach_pdf: true })
  })

  it('disables the reminder unless the document is overdue', () => {
    renderRow('not_due')
    expect(screen.getByRole('button', { name: 'Reminder available only with overdue installments' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Send reminder' })).toBeNull()
  })

  it('starts the reminder on an overdue document and opens the composer', async () => {
    renderRow('overdue')
    fireEvent.click(screen.getByRole('button', { name: 'Send reminder' }))
    await screen.findByText('composer:invoices:7:66:justCreated=false:prefill=true')
    expect(createReminderMock).toHaveBeenCalledWith({ type: 'invoices', id: 7 })
  })
})
