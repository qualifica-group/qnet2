import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { CompanyForm } from '@/features/companies/company-form'
import {
  EDITABLE,
  HIDDEN,
  clickCreateSave,
  companyFixture,
  companyWrapper,
  confirmOpenRow,
  permissionsFor,
} from '@/features/companies/company-test-fixtures'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { GeoValue } from '@/features/geo/geo-select'

/**
 * The company create form is a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked, nothing saved per row;
 * Salva validates and creates the whole draft. Custom fields included.
 */

const createCompanyMock = vi.fn()
vi.mock('@/features/companies/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/companies/api')>()),
  createCompany: (...args: unknown[]) => createCompanyMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

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

function renderForm(onSuccess = vi.fn()) {
  const { wrapper } = companyWrapper()
  render(<CompanyForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
  return onSuccess
}

/** Opens a row, types into its control and keeps the value with Done. */
async function fillRow(field: string, label: RegExp, value: string) {
  fireEvent.click(await screen.findByRole('button', { name: `Edit ${field}` }))
  fireEvent.change(await screen.findByLabelText(label), { target: { value } })
  confirmOpenRow('Done')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCompanyMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('CompanyForm — create', () => {
  it('opens on closed rows: nothing is editable until a pencil is clicked', async () => {
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Denomination' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('creates the company with denomination, VAT number and address built row by row', async () => {
    const created = companyFixture()
    createCompanyMock.mockResolvedValue(created)
    const onSuccess = renderForm()

    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    await fillRow('VAT number', /^VAT number/, 'IT12345678903')
    await fillRow('Address', /^Address$/, '221B Baker Street')
    clickCreateSave()

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    const payload = createCompanyMock.mock.calls[0][0]
    expect(payload.denomination).toBe('Acme Srl')
    expect(payload.vat_number).toBe('IT12345678903')
    expect(payload.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created))
  })

  it('carries the geo cascade picked on the place row', async () => {
    createCompanyMock.mockResolvedValue(companyFixture())
    renderForm()

    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    await fillRow('Address', /^Address$/, 'Via Roma 1')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit City' }))
    fireEvent.click(screen.getByTestId('geo-select'))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    expect(createCompanyMock.mock.calls[0][0].address).toMatchObject({ state_id: 6, province_id: 8, city_id: 7 })
  })

  it('omits the address entirely when it is left blank', async () => {
    createCompanyMock.mockResolvedValue(companyFixture({ address: null }))
    renderForm()

    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    clickCreateSave()

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    expect('address' in createCompanyMock.mock.calls[0][0]).toBe(false)
  })

  it('blocks the save when the address is started but the required line1 is left blank', async () => {
    renderForm()

    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    await fillRow('Postal code', /^Postal code/, '20100')
    clickCreateSave()

    expect(await screen.findByText('Address is required when an address is provided.')).toBeInTheDocument()
    expect(createCompanyMock).not.toHaveBeenCalled()
  })

  it('refuses a draft without denomination, marking the row', async () => {
    renderForm()
    await screen.findByRole('button', { name: 'Edit Denomination' })

    clickCreateSave()

    expect(await screen.findByText(i18n.t('companies.form.denominationRequired'))).toBeInTheDocument()
    expect(createCompanyMock).not.toHaveBeenCalled()
  })

  it('the identity band follows the denomination being typed', async () => {
    renderForm()

    expect(await screen.findByRole('heading', { name: i18n.t('companies.form.createTitle') })).toBeInTheDocument()
    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    expect(screen.getByRole('heading', { name: 'Acme Srl' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [NOTES_FIELD],
      permissions: permissionsFor({ 'custom.notes': EDITABLE }),
    })
    createCompanyMock.mockResolvedValue(companyFixture())
    renderForm()

    await fillRow('Denomination', /^Denomination/, 'Acme Srl')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), { target: { value: 'Key account' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createCompanyMock).toHaveBeenCalledTimes(1))
    expect(createCompanyMock.mock.calls[0][0].custom_fields).toEqual({ notes: 'Key account' })
  })
})

describe('CompanyForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and the whole address section when its permission is hidden', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ vat_number: HIDDEN, address: HIDDEN }),
    })
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Denomination' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit VAT number' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Address' })).not.toBeInTheDocument()
  })

  it('marks a required field in its editor from the create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ denomination: { ...EDITABLE, required: true } }),
    })
    renderForm()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Denomination' }))

    const label = screen.getByText(
      (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('Denomination') === true,
    )
    expect(label.textContent).toContain('*')
  })
})
