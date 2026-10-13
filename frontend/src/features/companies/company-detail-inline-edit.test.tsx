import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { CompanyDetailView } from '@/features/companies/company-detail'
import {
  EDITABLE,
  HIDDEN,
  READ_ONLY,
  companyFixture,
  companyWrapper,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
} from '@/features/companies/company-test-fixtures'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { GeoValue } from '@/features/geo/geo-select'

/**
 * The company detail edits in place, one row at a time, custom fields and the
 * address included (wave 3 of the "no edit pages" plan): there is no Edit
 * button, and each save is a PATCH of that field alone.
 */

const updateCompanyMock = vi.fn()
vi.mock('@/features/companies/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/companies/api')>()),
  updateCompany: (...args: unknown[]) => updateCompanyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// GeoSelect has its own suite: a stub that drives a full cascade pick in one click.
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({ onChange }: { value: GeoValue; onChange: (next: GeoValue) => void }) => (
    <button
      type="button"
      data-testid="geo-select"
      onClick={() => onChange({ country_id: 5, state_id: 6, province_id: 8, city_id: 7 })}
    >
      geo
    </button>
  ),
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function renderDetail(company: CompanyDetailWithPermissions) {
  const { client, wrapper } = companyWrapper()
  render(<CompanyDetailView company={company} />, { wrapper })
  return client
}

function rejectWith422(errors: Record<string, string[]>) {
  updateCompanyMock.mockRejectedValue(
    new AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: { success: false, message: 'Validation failed', errors },
    } as never),
  )
  vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  updateCompanyMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [NOTES_FIELD],
    permissions: permissionsFor({ 'custom.notes': EDITABLE }),
  })
})

describe('CompanyDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    renderDetail(companyFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Denomination')).toBeInTheDocument()
    expect(pencilOf('VAT number')).toBeInTheDocument()
  })

  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = companyFixture({ denomination: 'Acme Srl EU' })
    updateCompanyMock.mockResolvedValue(saved)
    const client = renderDetail(companyFixture())

    fireEvent.click(pencilOf('Denomination'))
    fireEvent.change(screen.getByLabelText(/^Denomination/), { target: { value: 'Acme Srl EU' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledWith(7, { denomination: 'Acme Srl EU' }))
    expect(client.getQueryData<CompanyDetailWithPermissions>(['companies', 'detail', 7])?.permissions).toEqual(
      saved.permissions,
    )
    await waitFor(() => expect(screen.queryByLabelText(/^Denomination/)).not.toBeInTheDocument())
  })

  it('sends the whole address block when one of its rows is saved', async () => {
    updateCompanyMock.mockResolvedValue(companyFixture())
    renderDetail(companyFixture())

    fireEvent.click(pencilOf('Postal code'))
    fireEvent.change(screen.getByLabelText(/^Postal code/), { target: { value: '10100' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateCompanyMock.mock.calls[0]
    expect(id).toBe(7)
    expect(Object.keys(payload)).toEqual(['address'])
    expect(payload.address).toMatchObject({ line1: '221B Baker Street', postal_code: '10100', city_id: 100 })
  })

  it('sends the geo cascade when the place row is saved', async () => {
    updateCompanyMock.mockResolvedValue(companyFixture())
    renderDetail(companyFixture())

    fireEvent.click(pencilOf('City'))
    fireEvent.click(screen.getByTestId('geo-select'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledTimes(1))
    expect(updateCompanyMock.mock.calls[0][1].address).toMatchObject({
      country_id: 5,
      state_id: 6,
      province_id: 8,
      city_id: 7,
    })
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(companyFixture())

    fireEvent.click(pencilOf('Denomination'))
    fireEvent.change(screen.getByLabelText(/^Denomination/), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByLabelText(/^Denomination/)).not.toBeInTheDocument()
    expect(updateCompanyMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit, and no address section when it is hidden', () => {
    renderDetail(
      companyFixture({ permissions: permissionsFor({ denomination: READ_ONLY, address: HIDDEN }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Denomination' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Postal code' })).not.toBeInTheDocument()
    expect(screen.queryByText('221B Baker Street')).not.toBeInTheDocument()
    expect(pencilOf('VAT number')).toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    rejectWith422({ denomination: ['field not editable'] })
    renderDetail(companyFixture())

    fireEvent.click(pencilOf('Denomination'))
    fireEvent.change(screen.getByLabelText(/^Denomination/), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('field not editable')).toBeInTheDocument()
  })

  it('refuses a started address without a street, on the row, without calling the server', async () => {
    renderDetail(companyFixture())

    fireEvent.click(pencilOf('Address'))
    fireEvent.change(screen.getByLabelText(/^Address$/), { target: { value: '' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Address is required when an address is provided.')).toBeInTheDocument()
    expect(updateCompanyMock).not.toHaveBeenCalled()
  })
})

describe('CompanyDetailView — custom fields in place (spec 0021)', () => {
  it('shows the custom field value and PATCHes only that field', async () => {
    updateCompanyMock.mockResolvedValue(companyFixture({ custom_fields: { notes: 'VIP' } }))
    renderDetail(companyFixture({ custom_fields: { notes: 'Key account' } }))

    expect(await screen.findByText('Key account')).toBeInTheDocument()
    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'VIP' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanyMock).toHaveBeenCalledWith(7, { custom_fields: { notes: 'VIP' } }))
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    rejectWith422({ 'custom_fields.notes': ['Notes must be shorter.'] })
    renderDetail(companyFixture({ custom_fields: { notes: 'Key account' } }))

    await screen.findByText('Key account')
    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Notes must be shorter.')).toBeInTheDocument()
  })
})
