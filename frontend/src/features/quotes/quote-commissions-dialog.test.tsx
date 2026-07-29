import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import { QuoteCommissionsDialog } from './quote-commissions-dialog'

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: (props: {
    labels: { triggerLabel: string }
    selectedItem?: { label: string } | null
    disabled?: boolean
    onChange: (id: number) => void
    onItemChange?: (item: { id: number; label: string }) => void
  }) => (
    <button
      type="button"
      disabled={props.disabled}
      aria-label={props.labels.triggerLabel}
      onClick={() => {
        props.onChange(9)
        props.onItemChange?.({ id: 9, label: 'Mario Rossi' })
      }}
    >
      {props.selectedItem?.label ?? 'Select recipient'}
    </button>
  ),
}))

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

function renderDialog(
  children: ReactNode,
  resolvedPermissions = permissions,
  confirm = vi.fn().mockResolvedValue(true),
) {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <ConfirmContext.Provider value={confirm}>
        <ResourcePermissionsProvider permissions={resolvedPermissions}>
          {children}
        </ResourcePermissionsProvider>
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => i18n.changeLanguage('en'))

describe('QuoteCommissionsDialog', () => {
  it('adds, edits and saves a manual commission while retaining recipient label', () => {
    const onSave = vi.fn()
    const onOpenChange = vi.fn()
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={onOpenChange}
        lineNumber={1}
        productName="Router"
        quantity={2}
        unitPrice={100}
        commissions={[]}
        disabled={false}
        onSave={onSave}
      />,
    )
    expect(screen.getAllByText('No commission for this role.')).toHaveLength(4)
    fireEvent.click(screen.getAllByRole('button', { name: 'Add manual commission' })[0])
    fireEvent.click(screen.getByRole('button', { name: 'Recipient' }))
    expect(screen.getByRole('button', { name: 'Recipient' })).toHaveTextContent('Mario Rossi')
    fireEvent.change(screen.getByLabelText('Value'), { target: { value: '10' } })
    fireEvent.change(screen.getByLabelText('Internal service note'), { target: { value: 'Manual note' } })
    fireEvent.change(screen.getByLabelText('Internal service note'), { target: { value: '' } })
    expect(screen.getByText('20.00')).toBeInTheDocument()
    expect(screen.getByText('Manual override')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Save commissions' }))
    expect(onSave).toHaveBeenCalledWith([
      expect.objectContaining({ recipient_id: 9, recipient: { id: 9, name: 'Mario Rossi' }, value: 10 }),
    ])
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('confirms removal of a persisted commission', async () => {
    const confirm = vi.fn().mockResolvedValue(true)
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={1}
        productName="Router"
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
        quantity={null}
        unitPrice={null}
        commissions={[{
          id: 7,
          recipient_role: 'SUPPLIER',
          recipient_type: 'registry',
          recipient_id: 3,
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
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('removes a newly added commission without confirmation', () => {
    const confirm = vi.fn().mockResolvedValue(true)
    renderDialog(
      <QuoteCommissionsDialog
        open
        onOpenChange={vi.fn()}
        lineNumber={3}
        productName="Access point"
        quantity={1}
        unitPrice={50}
        commissions={[]}
        disabled={false}
        onSave={vi.fn()}
      />,
      permissions,
      confirm,
    )

    fireEvent.click(screen.getAllByRole('button', { name: 'Add manual commission' })[2])
    fireEvent.click(screen.getByRole('button', { name: 'Remove commission' }))
    expect(confirm).not.toHaveBeenCalled()
    expect(screen.getAllByText('No commission for this role.')).toHaveLength(4)
  })
})
