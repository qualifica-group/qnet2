import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import { CommissionConfigurationFormBody } from './commission-configuration-form-body'
import type { CommissionConfigurationDetailWithPermissions } from './types'

vi.mock('@/components/form/relation-select-field', async () => {
  const { useController } = await import('react-hook-form')
  return {
    // A minimal test double that still binds to the real RHF field (via
    // `useController`), so tests can both read the current value and simulate
    // a pick — the real `AsyncPaginatedSelect` needs network data this suite
    // does not provide.
    RelationSelectField: ({ control, name, label, resource }: {
      control: Parameters<typeof useController>[0]['control']
      name: Parameters<typeof useController>[0]['name']
      label: string
      resource: string
    }) => {
      const { field } = useController({ control, name })
      return (
        <div>
          <span>{`${label}: ${resource}: ${String(field.value)}`}</span>
          <button type="button" onClick={() => field.onChange(99)}>{`pick ${name}`}</button>
        </div>
      )
    },
  }
})

const editable: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const fieldNames = [
  'name', 'recipient_role', 'application_scope', 'product_category_id', 'product_id', 'recipient_id',
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
    expect(screen.getAllByText(/Product category/).length).toBeGreaterThan(0)
    expect(screen.getByText(/^Recipient: referents:/)).toBeInTheDocument()
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
    expect(screen.getAllByText(/^Product:/).length).toBeGreaterThan(0)
    expect(screen.getByDisplayValue('20')).toBeInTheDocument()
    expect(screen.getByText('€')).toBeInTheDocument()
  })

  it('keeps a still-admitted recipient type/pick across a role change, resetting only when it is no longer admitted (AC-019, supersedes 0089 AC-016)', () => {
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    ))
    // Default role COMMERCIAL -> default type 'referent' (first of its allow-list).
    expect(screen.getByText('Recipient: referents: null')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'pick recipient_id' }))
    expect(screen.getByText('Recipient: referents: 99')).toBeInTheDocument()

    // REPORTER also admits 'referent' (D-4): the pick survives untouched.
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(screen.getByRole('option', { name: 'Reporter' }))
    expect(screen.getByText('Recipient: referents: 99')).toBeInTheDocument()

    // SUPERVISOR admits {user, referent} too (D-9): still no reset.
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(screen.getByRole('option', { name: 'Supervisor' }))
    expect(screen.getByText('Recipient: referents: 99')).toBeInTheDocument()

    // SUPPLIER admits only 'registry': 'referent' is no longer admitted -> reset.
    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(screen.getByRole('option', { name: 'Supplier' }))
    expect(screen.getByText('Recipient: registries: null')).toBeInTheDocument()
  })

  it('changing the recipient type swaps the picker resource and clears the previous pick (spec 0090 D-4)', () => {
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    ))
    fireEvent.click(screen.getByRole('button', { name: 'pick recipient_id' }))
    expect(screen.getByText('Recipient: referents: 99')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient type/ }))
    fireEvent.click(screen.getByRole('option', { name: 'User' }))

    expect(screen.getByText('Recipient: users: null')).toBeInTheDocument()
  })

  it('hides the recipient type selector for SUPPLIER, which admits a single type (spec 0090)', () => {
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    ))
    expect(screen.getByRole('combobox', { name: /^Recipient type/ })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('combobox', { name: /^Recipient role/ }))
    fireEvent.click(screen.getByRole('option', { name: 'Supplier' }))

    expect(screen.queryByRole('combobox', { name: /^Recipient type/ })).not.toBeInTheDocument()
  })

  it('shows only the recipient picker for the RECIPIENT scope, hiding product and category', () => {
    render(wrapper(
      <CommissionConfigurationFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    ))
    expect(screen.getByText(/^Product category:/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('combobox', { name: /^Application scope/ }))
    fireEvent.click(screen.getByRole('option', { name: 'Specific recipient' }))

    expect(screen.queryByText(/^Product category:/)).not.toBeInTheDocument()
    expect(screen.queryByText(/^Product:/)).not.toBeInTheDocument()
    expect(screen.getByText(/^Recipient: referents:/)).toBeInTheDocument()
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
