import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ProductTypologyDetailView } from '@/features/product-typologies/product-typology-detail'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'

/**
 * Spec 0088: the detail shows name (hero title), code (hero subtitle),
 * color, description and both timestamps. Wave 3 of the "no edit pages" plan
 * (spec 0236): every row edits in place, each save a PATCH of that field
 * alone; `code` is immutable (D-2), so it is the hero subtitle and never a
 * row. The Activity Log gate is exercised on `permissions.actions.view_activity`.
 */

const activityLogSectionMock = vi.fn()
const updateProductTypologyMock = vi.fn()

vi.mock('@/features/product-typologies/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-typologies/api')>()),
  updateProductTypology: (...args: unknown[]) => updateProductTypologyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function productTypology(
  overrides: Partial<ProductTypologyDetailWithPermissions> = {},
): ProductTypologyDetailWithPermissions {
  return {
    id: 4,
    name: 'Kilogram',
    code: 'kilogram',
    description: 'Mass unit',
    color: 'blue',
    supplier_commission_enabled: false,
    supplier_commission_direction: null,
    proforma_kind: null,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateProductTypologyMock.mockReset()
})

describe('ProductTypologyDetailView — detail fields', () => {
  it('shows name, code, color, description and both timestamps', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.getByRole('heading', { name: 'Kilogram' })).toBeInTheDocument()
    expect(screen.getByText('kilogram')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('Mass unit')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<ProductTypologyDetailView productTypology={productTypology({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('ProductTypologyDetailView — activity log section', () => {
  it('mounts the section for the viewed product typology when view_activity is granted', () => {
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          permissions: { ...productTypology().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'product-typologies', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('ProductTypologyDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed with its pencil, and code is not a row', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('productTypologies.form.name')).toBeInTheDocument()
    expect(pencilOf('productTypologies.form.supplierCommissionEnabled')).toBeInTheDocument()
    expect(pencilOf('productTypologies.form.code')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never code', async () => {
    updateProductTypologyMock.mockResolvedValue(productTypology({ name: 'Kilograms' }))
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Kilograms' } })
    saveRow()

    await waitFor(() => expect(updateProductTypologyMock).toHaveBeenCalledWith(4, { name: 'Kilograms' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Kilograms' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Kilograms')).not.toBeInTheDocument()
    expect(updateProductTypologyMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateProductTypologyMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          permissions: {
            ...productTypology().permissions,
            resource: { ...productTypology().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('productTypologies.form.name')).not.toBeInTheDocument()
    expect(pencilOf('productTypologies.form.supplierCommissionEnabled')).not.toBeInTheDocument()
  })
})

describe('ProductTypologyDetailView — supplier commission row (spec 0202)', () => {
  it('shows "No" and no direction when disabled', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.getByText('Supplier commission calculation')).toBeInTheDocument()
    expect(screen.getByText('No')).toBeInTheDocument()
    expect(screen.queryByText(/Received|Paid/)).not.toBeInTheDocument()
  })

  it('shows the switch and the direction together in one closed value when enabled', () => {
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          supplier_commission_enabled: true,
          supplier_commission_direction: 'RECEIVED',
        })}
      />,
    )

    expect(screen.getByText('Yes · Received')).toBeInTheDocument()
  })

  it('saves the switch and its direction as one row', async () => {
    updateProductTypologyMock.mockResolvedValue(productTypology())
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.supplierCommissionEnabled') as HTMLElement)
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('switch', { name: /Supplier commission calculation/ }))
    fireEvent.click(await screen.findByRole('combobox', { name: /Direction/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Paid' }))
    saveRow()

    await waitFor(() =>
      expect(updateProductTypologyMock).toHaveBeenCalledWith(4, {
        supplier_commission_enabled: true,
        supplier_commission_direction: 'PAID',
      }),
    )
  })

  it('sends a null direction after switching the commission off', async () => {
    updateProductTypologyMock.mockResolvedValue(productTypology())
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          supplier_commission_enabled: true,
          supplier_commission_direction: 'PAID',
        })}
      />,
    )

    fireEvent.click(pencilOf('productTypologies.form.supplierCommissionEnabled') as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /Supplier commission calculation/ }))
    saveRow()

    await waitFor(() =>
      expect(updateProductTypologyMock).toHaveBeenCalledWith(4, {
        supplier_commission_enabled: false,
        supplier_commission_direction: null,
      }),
    )
  })

  it('does not save an enabled commission without a direction', async () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.supplierCommissionEnabled') as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /Supplier commission calculation/ }))
    saveRow()

    expect(
      await screen.findByText('The direction is required when the Supplier commission is enabled.'),
    ).toBeInTheDocument()
    expect(updateProductTypologyMock).not.toHaveBeenCalled()
  })
})

describe('ProductTypologyDetailView — proforma request type (spec 0247 AC-016)', () => {
  it('shows None when the typology feeds no proforma request', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.getByText('Proforma request type')).toBeInTheDocument()
    expect(screen.getByText('None')).toBeInTheDocument()
  })

  it('shows the chosen type', () => {
    render(<ProductTypologyDetailView productTypology={productTypology({ proforma_kind: 'institution' })} />)

    expect(screen.getByText('Institution')).toBeInTheDocument()
  })

  it('saves the type picked in the select as a PATCH of that field alone', async () => {
    updateProductTypologyMock.mockResolvedValue(productTypology({ proforma_kind: 'consultancy' }))
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    fireEvent.click(pencilOf('productTypologies.form.proformaKind') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: /Proforma request type/ }))
    expect(await screen.findByRole('option', { name: 'None' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Institution' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Consultancy' }))
    saveRow()

    await waitFor(() => expect(updateProductTypologyMock).toHaveBeenCalledWith(4, { proforma_kind: 'consultancy' }))
  })

  it('clears the type back to null with None', async () => {
    updateProductTypologyMock.mockResolvedValue(productTypology())
    render(<ProductTypologyDetailView productTypology={productTypology({ proforma_kind: 'institution' })} />)

    fireEvent.click(pencilOf('productTypologies.form.proformaKind') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: /Proforma request type/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'None' }))
    saveRow()

    await waitFor(() => expect(updateProductTypologyMock).toHaveBeenCalledWith(4, { proforma_kind: null }))
  })

  it('offers no pencil on the type without update permission', () => {
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          permissions: {
            ...productTypology().permissions,
            resource: { ...productTypology().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('productTypologies.form.proformaKind')).not.toBeInTheDocument()
  })
})
