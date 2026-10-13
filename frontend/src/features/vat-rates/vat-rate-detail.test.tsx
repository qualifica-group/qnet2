import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { VatRateDetailView } from '@/features/vat-rates/vat-rate-detail'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'

/**
 * The VAT rate detail (spec 0236, wave 3 of the "no edit pages" plan): every
 * row edits in place, each save a PATCH of that field alone.
 */

const activityLogSectionMock = vi.fn()
const updateVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/vat-rates/api')>()),
  updateVatRate: (...args: unknown[]) => updateVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// No custom fields: the record sections resolve to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () =>
    Promise.resolve({ fields: [], permissions: { resource: {}, fields: {}, actions: {} } }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function vatRate(overrides: Partial<VatRateDetailWithPermissions> = {}): VatRateDetailWithPermissions {
  return {
    id: 4,
    name: 'Standard',
    rate: 22,
    created_at: '2026-01-01T09:00:00Z',
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
  updateVatRateMock.mockReset()
})

describe('VatRateDetailView — detail fields', () => {
  it('shows the name, the rate and the creation date', () => {
    render(<VatRateDetailView vatRate={vatRate()} />)

    expect(screen.getByRole('heading', { name: 'Standard' })).toBeInTheDocument()
    expect(screen.getByText('22')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })

  it('mounts the activity section only when view_activity is granted', () => {
    render(
      <VatRateDetailView
        vatRate={vatRate({ permissions: { ...vatRate().permissions, actions: { view_activity: true } } })}
      />,
    )

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'vat-rates', id: 4 })
  })
})

describe('VatRateDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<VatRateDetailView vatRate={vatRate()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('vatRates.form.name')).toBeInTheDocument()
    expect(pencilOf('vatRates.form.rate')).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateVatRateMock.mockResolvedValue(vatRate({ rate: 10 }))
    render(<VatRateDetailView vatRate={vatRate()} />)

    fireEvent.click(pencilOf('vatRates.form.rate') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '10' } })
    saveRow()

    await waitFor(() => expect(updateVatRateMock).toHaveBeenCalledWith(4, { rate: 10 }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<VatRateDetailView vatRate={vatRate()} />)

    fireEvent.click(pencilOf('vatRates.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateVatRateMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateVatRateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<VatRateDetailView vatRate={vatRate()} />)

    fireEvent.click(pencilOf('vatRates.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed, nor on a read-only field', () => {
    const readonly = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }
    const base = vatRate().permissions
    const { unmount } = render(
      <VatRateDetailView
        vatRate={vatRate({ permissions: { ...base, resource: { ...base.resource, update: false } } })}
      />,
    )
    expect(pencilOf('vatRates.form.name')).not.toBeInTheDocument()
    expect(pencilOf('vatRates.form.rate')).not.toBeInTheDocument()
    unmount()

    render(<VatRateDetailView vatRate={vatRate({ permissions: { ...base, fields: { name: readonly } } })} />)
    expect(pencilOf('vatRates.form.name')).not.toBeInTheDocument()
    expect(pencilOf('vatRates.form.rate')).toBeInTheDocument()
  })
})
