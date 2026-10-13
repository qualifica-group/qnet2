import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Wave 3 of the "no edit pages" plan (spec 0236): the form is create-only, a
 * replica of the detail with its rows closed; the in-place edits live in
 * `vat-rate-detail.test.tsx`.
 */

const createVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', () => ({
  createVatRate: (...args: unknown[]) => createVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata (covered by
 * `vat-rate-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

// The form wrapper reads `/meta/vat-rates` (create-context permissions) and the
// custom-field definitions from the same call; this suite has no custom
// fields to exercise (covered by `vat-rate-form-custom-fields.test.tsx`).
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function vatRate(): VatRateDetailWithPermissions {
  return {
    id: 9,
    name: 'Standard',
    rate: 22,
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

/** Opens a closed row through its pencil. */
async function openRow(key: string) {
  fireEvent.click(
    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }),
  )
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createVatRateMock.mockReset()
})

describe('VatRateForm — create', () => {
  it('starts with every row closed; the name and rate rows open on their own control', async () => {
    render(<VatRateForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('vatRates.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    await openRow('vatRates.form.rate')
    expect(screen.getByLabelText(/^Rate/)).toBeInTheDocument()
  })

  it('submits the create payload on save', async () => {
    createVatRateMock.mockResolvedValue(vatRate())
    const onSuccess = vi.fn()

    render(<VatRateForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('vatRates.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    await openRow('vatRates.form.rate')
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '22' } })
    save()

    await waitFor(() => expect(createVatRateMock).toHaveBeenCalledTimes(1))
    expect(createVatRateMock).toHaveBeenCalledWith({ name: 'Standard', rate: 22 })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(vatRate()))
  })

  it('rejects an empty rate at submit, on the closed row', async () => {
    render(<VatRateForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('vatRates.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    save()

    expect(await screen.findByText('Rate is required.')).toHaveAttribute('role', 'alert')
    expect(createVatRateMock).not.toHaveBeenCalled()
  })
})
