import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * The metadata-driven behaviour of the VAT rate create form (spec 0004,
 * AC-024): hidden field absent, non-editable field without a pencil, a
 * server 422 mapped inline on its closed row. The in-place counterparts live
 * in `vat-rate-detail.test.tsx`.
 */

const createVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', () => ({
  createVatRate: (...args: unknown[]) => createVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const RESOURCE = { view: true, create: true, update: true, delete: true, export: true, import: true }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createVatRateMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('VatRateForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and offers no pencil on a read-only one', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: RESOURCE,
        fields: {
          rate: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
          name: { visible: true, hidden: false, editable: false, readonly: true, required: true, disabled: false },
        },
        actions: {},
      },
    })

    render(<VatRateForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await waitFor(() => expect(screen.getByText('Name')).toBeInTheDocument())
    expect(screen.queryByText('Rate')).not.toBeInTheDocument()
    expect(pencilOf('vatRates.form.name')).not.toBeInTheDocument()
    expect(pencilOf('vatRates.form.rate')).not.toBeInTheDocument()
  })

  it('surfaces a 422 field error on the closed row', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: { resource: RESOURCE, fields: {}, actions: {} } })
    createVatRateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { rate: ['field not editable'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(<VatRateForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(
      await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t('vatRates.form.name') }) }),
    )
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    fireEvent.click(
      screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t('vatRates.form.rate') }) }),
    )
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '22' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(createVatRateMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
