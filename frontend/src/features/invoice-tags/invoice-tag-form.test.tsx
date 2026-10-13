import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { InvoiceTagForm } from '@/features/invoice-tags/invoice-tag-form'
import type { InvoiceTagDetailWithPermissions } from '@/features/invoice-tags/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0247 AC-016: the form composes the SHARED `ColorTokenPicker`, so a color
 * is picked as a palette token (never typed as a hex). Wave 3 of the "no edit pages" plan:
 * the form is create-only, a replica of the detail with its rows closed
 * (the in-place edits live in `invoice-tag-detail.test.tsx`).
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
 */

const createInvoiceTagMock = vi.fn()

vi.mock('@/features/invoice-tags/api', () => ({
  createInvoiceTag: (...args: unknown[]) => createInvoiceTagMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const ALL_EDITABLE: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    description: EDITABLE,
    color: EDITABLE,
    is_active: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: metaPermissions }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Anchors a translated label so a required field's trailing marker does not break the match. */
function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

const COLOR_PLACEHOLDER = 'customFields.form.colorPickerPlaceholder'

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('invoiceTags.form.save') })[0])

function invoiceTag(
  overrides: Partial<InvoiceTagDetailWithPermissions> = {},
): InvoiceTagDetailWithPermissions {
  return {
    id: 9,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    sort_order: 3,
    is_active: true,
    created_at: null,
    updated_at: null,
    permissions: ALL_EDITABLE,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createInvoiceTagMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('InvoiceTagForm — create (spec 0247)', () => {
  it('starts with every row closed; the name row opens on its own control', () => {
    render(<InvoiceTagForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('invoiceTags.form.name')
    expect(screen.getByLabelText(labelFor('invoiceTags.form.name'))).toBeInTheDocument()
  })

  it('opens the shared color picker from its row', () => {
    render(<InvoiceTagForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('invoiceTags.form.color')
    expect(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) })).toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<InvoiceTagForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(i18n.t('invoiceTags.form.nameRequired'))).toHaveAttribute('role', 'alert')

    openRow('invoiceTags.form.name')
    const nameInput = screen.getByLabelText(labelFor('invoiceTags.form.name'))
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createInvoiceTagMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (the column is NOT NULL)', async () => {
    render(<InvoiceTagForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('invoiceTags.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('invoiceTags.form.name')), { target: { value: 'High' } })
    save()

    await waitFor(() => expect(screen.getByText(i18n.t('invoiceTags.form.colorRequired'))).toBeInTheDocument())
    expect(createInvoiceTagMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createInvoiceTagMock.mockResolvedValue(invoiceTag())
    const onSuccess = vi.fn()

    render(<InvoiceTagForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('invoiceTags.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('invoiceTags.form.name')), { target: { value: 'High' } })
    openRow('invoiceTags.form.color')
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    save()

    await waitFor(() => expect(createInvoiceTagMock).toHaveBeenCalledTimes(1))
    expect(createInvoiceTagMock).toHaveBeenCalledWith({
      name: 'High',
      color: 'emerald',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(invoiceTag()))
  })
})
