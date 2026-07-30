import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { QuoteCommissionRecipientMap } from './types'
import { QuoteCommissionsDialog } from './quote-commissions-dialog'

/**
 * The recipient is locked to whoever was picked upstream on the quote, so
 * these drive the component through the server-resolved recipient map rather
 * than through a picker: a role the map reports as null is not commissionable
 * at all, and no test may select a third party.
 */
vi.mock('./api', () => ({
  fetchQuoteCommissionRecipients: vi.fn(),
  quoteCommissionRecipientsQueryKey: (payload: Record<string, unknown>) => ['recipients', payload],
}))

const { fetchQuoteCommissionRecipients } = await import('./api')

const ALL_ROLES_PICKED: QuoteCommissionRecipientMap = {
  COMMERCIAL: { type: 'referent', id: 9, name: 'Mario Rossi' },
  REPORTER: { type: 'referent', id: 11, name: 'Elio Fabbri' },
  SUPERVISOR: { type: 'user', id: 12, name: 'Ivo Bianchi' },
  SUPPLIER: { type: 'registry', id: 13, name: 'ACME Spa' },
}

const editable: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const permissions: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {
    commissions: editable,
    commission_recipient: editable,
    commission_type: editable,
    commission_value: editable,
    commission_internal_note: editable,
  },
  actions: {},
}

const COMMISSION_CONTEXT = { commercialId: 9, reporterId: 11, supervisorId: 12 }

function renderDialog(
  children: ReactNode,
  resolvedPermissions = permissions,
  confirm = vi.fn().mockResolvedValue(true),
) {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <ConfirmContext.Provider value={confirm}>
        <ResourcePermissionsProvider permissions={resolvedPermissions}>
          {children}
        </ResourcePermissionsProvider>
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => i18n.changeLanguage('en'))

beforeEach(() => {
  vi.mocked(fetchQuoteCommissionRecipients).mockReset()
  vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue(ALL_ROLES_PICKED)
})

describe('QuoteCommissionsDialog', () => {
  it('adds a manual commission locked to the role holder picked upstream', async () => {
    const onSave = vi.fn()
    const onOpenChange = vi.fn()
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={onOpenChange}
        lineNumber={1}
        productName="Router"
        productId={4}
        commissionContext={COMMISSION_CONTEXT}
        quantity={2}
        unitPrice={100}
        commissions={[]}
        disabled={false}
        onSave={onSave}
      />,
    )
    expect(screen.getAllByText('No commission for this role.')).toHaveLength(4)
    await waitFor(() =>
      expect(screen.getAllByRole('button', { name: 'Add manual commission' })[0]).toBeEnabled(),
    )

    fireEvent.click(screen.getAllByRole('button', { name: 'Add manual commission' })[0])

    // The recipient is displayed, never chosen.
    expect(screen.getByLabelText('Recipient')).toHaveTextContent('Mario Rossi')
    expect(screen.queryByRole('button', { name: 'Recipient' })).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Value'), { target: { value: '10' } })
    expect(screen.getByLabelText('Calculated amount')).toHaveTextContent('20.00')
    fireEvent.click(screen.getByRole('button', { name: 'Save commissions' }))
    expect(onSave).toHaveBeenCalledWith([
      expect.objectContaining({
        recipient_role: 'COMMERCIAL',
        recipient_type: 'referent',
        recipient_id: 9,
        recipient: { id: 9, name: 'Mario Rossi' },
        value: 10,
      }),
    ])
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('offers no way to commission a role with nobody picked upstream', async () => {
    vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue({
      ...ALL_ROLES_PICKED,
      REPORTER: null,
      SUPPLIER: null,
    })
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={1}
        productName="Router"
        productId={4}
        commissionContext={{ ...COMMISSION_CONTEXT, reporterId: null }}
        quantity={1}
        unitPrice={100}
        commissions={[]}
        disabled={false}
        onSave={vi.fn()}
      />,
    )

    await waitFor(() =>
      expect(
        screen.getAllByText('No one is selected on the quote for this role, so it cannot be commissioned.'),
      ).toHaveLength(2),
    )
    expect(screen.getAllByRole('button', { name: 'Add manual commission' })).toHaveLength(2)
  })

  it('blocks the save when a drafted role lost its recipient upstream', async () => {
    const onSave = vi.fn()
    vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue({ ...ALL_ROLES_PICKED, COMMERCIAL: null })
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={1}
        productName="Router"
        productId={4}
        commissionContext={{ ...COMMISSION_CONTEXT, commercialId: null }}
        quantity={1}
        unitPrice={100}
        commissions={[{
          id: 5,
          recipient_role: 'COMMERCIAL',
          recipient_type: 'referent',
          recipient_id: 2,
          recipient: { id: 2, name: 'Luca' },
          commission_type: 'FIXED_AMOUNT',
          value: 25,
          internal_note: null,
          origin: 'PRODUCT',
          commission_configuration_id: 4,
        }]}
        disabled={false}
        onSave={onSave}
      />,
    )

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Save commissions' })).toBeDisabled(),
    )
    expect(screen.getByRole('alert')).toHaveTextContent(
      'This role is no longer selected on the quote: remove the commission before saving.',
    )
    expect(screen.getByLabelText('Recipient')).toHaveTextContent('Luca')
  })

  it('confirms removal of a persisted commission', async () => {
    const confirm = vi.fn().mockResolvedValue(true)
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={1}
        productName="Router"
        productId={4}
        commissionContext={COMMISSION_CONTEXT}
        quantity={1}
        unitPrice={100}
        commissions={[{
          id: 5,
          recipient_role: 'COMMERCIAL',
          recipient_type: 'referent',
          recipient_id: 9,
          recipient: { id: 9, name: 'Mario Rossi' },
          commission_type: 'FIXED_AMOUNT',
          value: 25,
          internal_note: null,
          origin: 'PRODUCT',
          commission_configuration_id: 4,
        }]}
        disabled={false}
        onSave={vi.fn()}
      />,
      permissions,
      confirm,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Remove commission' }))
    expect(confirm).toHaveBeenCalled()
    await waitFor(() => {
      expect(screen.getAllByText('No commission for this role.')).toHaveLength(4)
    })
  })

  it('hides protected fields and collection actions', () => {
    const hidden = { ...editable, visible: false, hidden: true, editable: false, disabled: true }
    const restricted = {
      ...permissions,
      fields: {
        commissions: { ...editable, editable: false, readonly: true },
        commission_recipient: hidden,
        commission_type: hidden,
        commission_value: hidden,
        commission_internal_note: hidden,
      },
    }
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={1}
        productName="Router"
        productId={4}
        quantity={1}
        unitPrice={100}
        commissions={[]}
        disabled
        onSave={vi.fn()}
      />,
      restricted,
    )
    expect(screen.queryByRole('button', { name: 'Add manual commission' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save commissions' })).not.toBeInTheDocument()
    expect(fetchQuoteCommissionRecipients).not.toHaveBeenCalled()
  })

  it('keeps a persisted commission when removal is declined and closes from cancel', async () => {
    const confirm = vi.fn().mockResolvedValue(false)
    const onOpenChange = vi.fn()
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={onOpenChange}
        lineNumber={2}
        productName="Switch"
        productId={4}
        commissionContext={COMMISSION_CONTEXT}
        quantity={null}
        unitPrice={null}
        commissions={[{
          id: 7,
          recipient_role: 'SUPPLIER',
          recipient_type: 'registry',
          recipient_id: 13,
          commission_type: 'FIXED_AMOUNT',
          value: 5,
          internal_note: 'Keep',
          origin: 'PRODUCT_CATEGORY',
          commission_configuration_id: 2,
        }]}
        disabled={false}
        onSave={vi.fn()}
      />,
      permissions,
      confirm,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Remove commission' }))
    await waitFor(() => expect(confirm).toHaveBeenCalled())
    expect(screen.getByText('Category')).toBeInTheDocument()
    // No `recipient` on the payload: the resolved lock supplies the name.
    await waitFor(() => expect(screen.getByLabelText('Recipient')).toHaveTextContent('ACME Spa'))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('removes a newly added commission without confirmation', async () => {
    const confirm = vi.fn().mockResolvedValue(true)
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={3}
        productName="Access point"
        productId={4}
        commissionContext={COMMISSION_CONTEXT}
        quantity={1}
        unitPrice={50}
        commissions={[]}
        disabled={false}
        onSave={vi.fn()}
      />,
      permissions,
      confirm,
    )

    await waitFor(() =>
      expect(screen.getAllByRole('button', { name: 'Add manual commission' })[2]).toBeEnabled(),
    )
    fireEvent.click(screen.getAllByRole('button', { name: 'Add manual commission' })[2])
    fireEvent.click(screen.getByRole('button', { name: 'Remove commission' }))
    expect(confirm).not.toHaveBeenCalled()
    expect(screen.getAllByText('No commission for this role.')).toHaveLength(4)
  })
})
