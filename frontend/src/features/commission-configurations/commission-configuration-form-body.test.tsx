import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import { CommissionConfigurationFormBody } from './commission-configuration-form-body'
import type { CommissionConfigurationDetailWithPermissions } from './types'

vi.mock('@/components/form/relation-select-field', () => ({
  RelationSelectField: ({ label }: { label: string }) => <div>{label}</div>,
}))

const editable: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const fieldNames = [
  'name', 'recipient_role', 'application_scope', 'product_category_id', 'product_id',
  'commission_type', 'value', 'priority', 'valid_from', 'valid_until', 'status', 'internal_note',
]
const permissions: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: Object.fromEntries(fieldNames.map((field) => [field, editable])),
  actions: {},
}

function wrapper(children: ReactNode, resolvedPermissions = permissions) {
  return (
    <QueryClientProvider client={new QueryClient()}>
      <ResourcePermissionsProvider permissions={resolvedPermissions}>
        {children}
      </ResourcePermissionsProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => i18n.changeLanguage('en'))

describe('CommissionConfigurationFormBody', () => {
  it('renders the complete create form and cancels', () => {
    const onCancel = vi.fn()
    const { container } = render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={onCancel} />,
    ))
    expect(screen.getByText('Identity and scope')).toBeInTheDocument()
    expect(screen.getAllByText('Product category').length).toBeGreaterThan(0)
    expect(screen.getByText('Calculation')).toBeInTheDocument()
    expect(screen.getByText('Validity')).toBeInTheDocument()
    expect(screen.getByText('Internal note')).toBeInTheDocument()
    fireEvent.change(container.querySelector('input[name="value"]')!, { target: { value: '8.5' } })
    fireEvent.change(container.querySelector('input[name="priority"]')!, { target: { value: '4' } })
    fireEvent.change(container.querySelector('input[name="valid_until"]')!, { target: { value: '2027-01-01' } })
    fireEvent.change(container.querySelector('input[name="valid_until"]')!, { target: { value: '' } })
    fireEvent.change(container.querySelector('textarea[name="internal_note"]')!, { target: { value: 'Review' } })
    fireEvent.change(container.querySelector('textarea[name="internal_note"]')!, { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalled()
  })

  it('renders the product relation for an edit product rule', () => {
    const configuration = {
      id: 1,
      name: 'Product rule',
      recipient_role: 'SUPPLIER',
      application_scope: 'PRODUCT',
      product_category_id: null,
      product_category: null,
      product_id: 3,
      product: { id: 3, name: 'Router' },
      commission_type: 'FIXED_AMOUNT',
      value: '20.0000',
      priority: 1,
      valid_from: '2026-07-29',
      valid_until: '2026-12-31',
      status: 'SUSPENDED',
      internal_note: 'Review',
      created_at: '2026-07-29T00:00:00Z',
      updated_at: '2026-07-29T00:00:00Z',
      permissions,
    } satisfies CommissionConfigurationDetailWithPermissions
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'edit', configuration }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    ))
    expect(screen.getAllByText('Product').length).toBeGreaterThan(0)
    expect(screen.getByDisplayValue('20')).toBeInTheDocument()
    expect(screen.getByText('€')).toBeInTheDocument()
  })

  it('does not render empty sections when every child is hidden', () => {
    const hidden = { ...editable, visible: false, hidden: true, editable: false, disabled: true }
    const hiddenPermissions = {
      ...permissions,
      fields: Object.fromEntries(fieldNames.map((field) => [field, hidden])),
    }
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      hiddenPermissions,
    ))
    expect(screen.queryByText('Identity and scope')).not.toBeInTheDocument()
    expect(screen.queryByText('Calculation')).not.toBeInTheDocument()
    expect(screen.queryByText('Validity')).not.toBeInTheDocument()
    expect(screen.queryByText('Internal note')).not.toBeInTheDocument()
  })
})
