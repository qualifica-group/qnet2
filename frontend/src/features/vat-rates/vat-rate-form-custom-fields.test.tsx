import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import { VatRateDetailView } from '@/features/vat-rates/vat-rate-detail'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the vat-rates module wires the universal custom-fields renderer.
 * Wave 3 (spec 0236): they render as in-place record rows, on the detail
 * (saved alone) and on the create draft (kept until Salva).
 */

const createVatRateMock = vi.fn()
const updateVatRateMock = vi.fn()

vi.mock('@/features/vat-rates/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/vat-rates/api')>()),
  createVatRate: (...args: unknown[]) => createVatRateMock(...args),
  updateVatRate: (...args: unknown[]) => updateVatRateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.notes': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: { view_activity: false },
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function vatRate(overrides: Partial<VatRateDetailWithPermissions> = {}): VatRateDetailWithPermissions {
  return {
    id: 9,
    name: 'Standard',
    rate: 22,
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

const pencilOf = async (label: string) =>
  screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createVatRateMock.mockReset()
  updateVatRateMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('VatRateForm — custom fields (spec 0021)', () => {
  it('includes the valued custom field in the create payload', async () => {
    createVatRateMock.mockResolvedValue(vatRate())

    render(<VatRateForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(await pencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard' } })
    fireEvent.click(await pencilOf('Rate'))
    fireEvent.change(screen.getByLabelText(/^Rate/), { target: { value: '22' } })
    fireEvent.click(await pencilOf('Notes'))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key rate' },
    })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createVatRateMock).toHaveBeenCalledTimes(1))
    expect(createVatRateMock.mock.calls[0][0].custom_fields).toEqual({ notes: 'Key rate' })
  })
})

describe('VatRateDetailView — custom fields (spec 0021)', () => {
  it('shows the persisted value and saves a changed one alone', async () => {
    updateVatRateMock.mockResolvedValue(vatRate({ custom_fields: { notes: 'Changed' } }))

    render(<VatRateDetailView vatRate={vatRate({ custom_fields: { notes: 'Existing note' } })} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByText('Existing note')).toBeInTheDocument()
    fireEvent.click(await pencilOf('Notes'))
    const input = await screen.findByRole('textbox', { name: 'Notes' })
    expect(input).toHaveValue('Existing note')
    fireEvent.change(input, { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateVatRateMock).toHaveBeenCalledWith(9, { custom_fields: { notes: 'Changed' } }))
  })
})
