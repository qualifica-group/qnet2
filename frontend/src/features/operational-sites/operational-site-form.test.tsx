import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { GeoValue } from '@/features/geo/geo-select'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import {
  EDITABLE,
  HIDDEN,
  clickCreateSave,
  confirmOpenRow,
  operationalSiteFixture,
  operationalSiteWrapper,
  pencilOf,
  permissionsFor,
} from '@/features/operational-sites/operational-site-test-fixtures'

/**
 * The operational-site create form is a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked, nothing saved per
 * row; Salva validates and creates the whole draft. Custom fields included.
 */

const createOperationalSiteMock = vi.fn()
vi.mock('@/features/operational-sites/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/operational-sites/api')>()),
  createOperationalSite: (...args: unknown[]) => createOperationalSiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// GeoSelect has its own suite: a stub that drives a full cascade pick in one click.
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({ value, onChange }: { value: GeoValue; onChange: (next: GeoValue) => void }) => (
    <button
      type="button"
      data-testid="geo-select"
      data-state={value.state_id ?? ''}
      data-province={value.province_id ?? ''}
      data-city={value.city_id ?? ''}
      onClick={() => onChange({ country_id: 5, state_id: 6, province_id: 8, city_id: 7 })}
    >
      geo
    </button>
  ),
}))

const ZONE_FIELD: CustomFieldDescriptor = {
  key: 'custom.zone',
  type: 'text',
  label: 'Zone',
  group: null,
  mandatory: false,
  source: 'custom',
}

function renderForm(onSuccess = vi.fn()) {
  const { wrapper } = operationalSiteWrapper()
  render(<OperationalSiteForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
  return onSuccess
}

/** Opens a row, types into its control and keeps the value with Done. */
async function fillRow(field: string, label: RegExp, value: string) {
  fireEvent.click(await screen.findByRole('button', { name: `Edit ${field}` }))
  fireEvent.change(await screen.findByLabelText(label), { target: { value } })
  confirmOpenRow('Done')
}

async function pickPlace() {
  fireEvent.click(await screen.findByRole('button', { name: 'Edit City' }))
  fireEvent.click(screen.getByTestId('geo-select'))
  confirmOpenRow('Done')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOperationalSiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('OperationalSiteForm — create', () => {
  it('opens on closed rows: nothing is editable until a pencil is clicked', async () => {
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Street' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByTestId('geo-select')).not.toBeInTheDocument()
  })

  it('creates the site with the draft built row by row', async () => {
    const created = operationalSiteFixture()
    createOperationalSiteMock.mockResolvedValue(created)
    const onSuccess = renderForm()

    await fillRow('Street', /^Street/, 'Via Torino 3')
    await fillRow('Postal code', /^Postal code/, '10100')
    await pickPlace()
    clickCreateSave()
    await waitFor(() => expect(createOperationalSiteMock).toHaveBeenCalledTimes(1))
    expect(createOperationalSiteMock).toHaveBeenCalledWith({
      alias: null,
      line1: 'Via Torino 3',
      postal_code: '10100',
      country_id: 5,
      state_id: 6,
      province_id: 8,
      city_id: 7,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created))
  })

  it('includes the valued custom field in the create payload', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [ZONE_FIELD],
      permissions: permissionsFor({ 'custom.zone': EDITABLE }),
    })
    createOperationalSiteMock.mockResolvedValue(operationalSiteFixture())
    renderForm()

    await fillRow('Street', /^Street/, 'Via Torino 3')
    await pickPlace()
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Zone' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Zone' }), { target: { value: 'North' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createOperationalSiteMock).toHaveBeenCalledTimes(1))
    expect(createOperationalSiteMock.mock.calls[0][0].custom_fields).toEqual({ zone: 'North' })
  })

  it('the identity band follows the alias, then the street, while the draft is typed', async () => {
    renderForm()

    expect(await screen.findByRole('heading', { name: i18n.t('operationalSites.form.createTitle') })).toBeInTheDocument()
    await fillRow('Street', /^Street/, 'Via Torino 3')
    expect(screen.getByRole('heading', { name: 'Via Torino 3' })).toBeInTheDocument()
    await fillRow('Alias', /^Alias/, 'HQ')
    expect(screen.getByRole('heading', { name: 'HQ' })).toBeInTheDocument()
  })

  it('refuses a draft without street and place, marking the rows, without calling the server', async () => {
    renderForm()
    await screen.findByRole('button', { name: 'Edit Street' })

    clickCreateSave()

    expect(await screen.findByText(i18n.t('operationalSites.form.line1Required'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('operationalSites.form.cityRequired'))).toBeInTheDocument()
    expect(createOperationalSiteMock).not.toHaveBeenCalled()
  })

  it('Revert puts a row back as it was when it opened', async () => {
    renderForm()

    await fillRow('Street', /^Street/, 'Via Torino 3')
    fireEvent.click(screen.getByRole('button', { name: 'Edit Street' }))
    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Via Genova 4' } })
    fireEvent.click(screen.getByRole('button', { name: 'Revert' }))

    expect(screen.queryByLabelText(/^Street/)).not.toBeInTheDocument()
    expect(screen.queryByText('Via Genova 4')).not.toBeInTheDocument()
    expect(screen.getAllByText('Via Torino 3').length).toBeGreaterThan(0)
  })
})

describe('OperationalSiteForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and keeps the others, falling back to editable for unlisted ones', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ postal_code: HIDDEN }),
    })
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Street' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Postal code' })).not.toBeInTheDocument()
    expect(pencilOf('Alias')).toBeInTheDocument()
  })

  it('marks a required field in its editor from the create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ line1: { ...EDITABLE, required: true } }),
    })
    renderForm()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Street' }))

    const label = screen.getByText(
      (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('Street') === true,
    )
    expect(label.textContent).toContain('*')
  })
})
