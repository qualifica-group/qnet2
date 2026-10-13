import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseRequestPriorityForm } from '@/features/purchase-request-priorities/purchase-request-priority-form'
import type { PurchaseRequestPriorityDetailWithPermissions } from '@/features/purchase-request-priorities/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0247 AC-087: the form composes the SHARED `ColorTokenPicker` and
 * `IconPicker`, so a color is picked as a palette token (never typed as a hex)
 * and an icon as a curated lucide name. Wave 3 of the "no edit pages" plan:
 * the form is create-only, a replica of the detail with its rows closed
 * (the in-place edits live in `purchase-request-priority-detail.test.tsx`).
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
 */

const createPurchaseRequestPriorityMock = vi.fn()

vi.mock('@/features/purchase-request-priorities/api', () => ({
  createPurchaseRequestPriority: (...args: unknown[]) => createPurchaseRequestPriorityMock(...args),
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
    icon: EDITABLE,
    is_active: EDITABLE,
    is_default: EDITABLE,
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
const ICON_PLACEHOLDER = 'customFields.form.iconPickerPlaceholder'

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('purchaseRequestPriorities.form.save') })[0])

function purchaseRequestPriority(
  overrides: Partial<PurchaseRequestPriorityDetailWithPermissions> = {},
): PurchaseRequestPriorityDetailWithPermissions {
  return {
    id: 9,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    is_default: false,
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
  createPurchaseRequestPriorityMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('PurchaseRequestPriorityForm — create (spec 0247)', () => {
  it('starts with every row closed; the name row opens on its own control', () => {
    render(<PurchaseRequestPriorityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('purchaseRequestPriorities.form.name')
    expect(screen.getByLabelText(labelFor('purchaseRequestPriorities.form.name'))).toBeInTheDocument()
  })

  it('opens the shared color and icon pickers from their rows', () => {
    render(<PurchaseRequestPriorityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('purchaseRequestPriorities.form.color')
    expect(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) })).toBeInTheDocument()

    openRow('purchaseRequestPriorities.form.icon')
    // The picker trigger takes the field id, so its accessible name is the LABEL;
    // the placeholder is its visible content.
    expect(screen.getByLabelText(labelFor('purchaseRequestPriorities.form.icon'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t(ICON_PLACEHOLDER))).toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<PurchaseRequestPriorityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(i18n.t('purchaseRequestPriorities.form.nameRequired'))).toHaveAttribute('role', 'alert')

    openRow('purchaseRequestPriorities.form.name')
    const nameInput = screen.getByLabelText(labelFor('purchaseRequestPriorities.form.name'))
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createPurchaseRequestPriorityMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (D-4: the column is NOT NULL)', async () => {
    render(<PurchaseRequestPriorityForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('purchaseRequestPriorities.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('purchaseRequestPriorities.form.name')), { target: { value: 'High' } })
    save()

    await waitFor(() => expect(screen.getByText(i18n.t('purchaseRequestPriorities.form.colorRequired'))).toBeInTheDocument())
    expect(createPurchaseRequestPriorityMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createPurchaseRequestPriorityMock.mockResolvedValue(purchaseRequestPriority())
    const onSuccess = vi.fn()

    render(<PurchaseRequestPriorityForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('purchaseRequestPriorities.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('purchaseRequestPriorities.form.name')), { target: { value: 'High' } })
    openRow('purchaseRequestPriorities.form.color')
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    save()

    await waitFor(() => expect(createPurchaseRequestPriorityMock).toHaveBeenCalledTimes(1))
    expect(createPurchaseRequestPriorityMock).toHaveBeenCalledWith({
      name: 'High',
      color: 'emerald',
      icon: null,
      description: null,
      is_active: true,
      is_default: false,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(purchaseRequestPriority()))
  })
})
