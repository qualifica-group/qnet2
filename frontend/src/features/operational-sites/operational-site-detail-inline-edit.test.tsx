import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { GeoValue } from '@/features/geo/geo-select'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import {
  EDITABLE,
  READ_ONLY,
  confirmOpenRow,
  operationalSiteFixture,
  operationalSiteWrapper,
  pencilOf,
  permissionsFor,
} from '@/features/operational-sites/operational-site-test-fixtures'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'

/**
 * The operational-site detail edits in place, one row at a time, custom fields
 * and the geo cascade included (wave 3 of the "no edit pages" plan): there is
 * no Edit button, and each save is a PATCH of that field alone.
 */

const updateOperationalSiteMock = vi.fn()
vi.mock('@/features/operational-sites/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/operational-sites/api')>()),
  updateOperationalSite: (...args: unknown[]) => updateOperationalSiteMock(...args),
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

function renderDetail(site: OperationalSiteDetailWithPermissions) {
  const { client, wrapper } = operationalSiteWrapper()
  render(<OperationalSiteDetailView operationalSite={site} />, { wrapper })
  return client
}

function rejectWith422(errors: Record<string, string[]>) {
  updateOperationalSiteMock.mockRejectedValue(
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
  updateOperationalSiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [ZONE_FIELD],
    permissions: permissionsFor({ 'custom.zone': EDITABLE }),
  })
})

describe('OperationalSiteDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    renderDetail(operationalSiteFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Street')).toBeInTheDocument()
    expect(pencilOf('Alias')).toBeInTheDocument()
  })

  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = operationalSiteFixture({ line1: 'Via Milano 9' })
    updateOperationalSiteMock.mockResolvedValue(saved)
    const client = renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('Street'))
    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Via Milano 9' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateOperationalSiteMock).toHaveBeenCalledWith(9, { line1: 'Via Milano 9' }))
    expect(
      client.getQueryData<OperationalSiteDetailWithPermissions>(['operational-sites', 'detail', 9])?.permissions,
    ).toEqual(saved.permissions)
    await waitFor(() => expect(screen.queryByLabelText(/^Street/)).not.toBeInTheDocument())
  })

  it('deactivates the site through the active switch (spec 0135)', async () => {
    updateOperationalSiteMock.mockResolvedValue(operationalSiteFixture({ is_active: false }))
    renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('Active site'))
    fireEvent.click(screen.getByRole('switch', { name: /^Active site/ }))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateOperationalSiteMock).toHaveBeenCalledWith(9, { is_active: false }))
  })

  it('sends the whole geo cascade when the place row is saved', async () => {
    updateOperationalSiteMock.mockResolvedValue(operationalSiteFixture())
    renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('City'))
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-city', '4')
    fireEvent.click(screen.getByTestId('geo-select'))
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateOperationalSiteMock).toHaveBeenCalledWith(9, {
        country_id: 5,
        state_id: 6,
        province_id: 8,
        city_id: 7,
      }),
    )
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('Street'))
    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByLabelText(/^Street/)).not.toBeInTheDocument()
    expect(screen.getByText('Via Roma 1')).toBeInTheDocument()
    expect(updateOperationalSiteMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit, nor on the place when one level is locked', () => {
    renderDetail(
      operationalSiteFixture({ permissions: permissionsFor({ line1: READ_ONLY, state_id: READ_ONLY }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Street' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit City' })).not.toBeInTheDocument()
    expect(pencilOf('Alias')).toBeInTheDocument()
  })

  it('offers no pencil at all without the update permission on the fields', () => {
    renderDetail(
      operationalSiteFixture({
        permissions: permissionsFor({
          alias: READ_ONLY,
          line1: READ_ONLY,
          postal_code: READ_ONLY,
          city_id: READ_ONLY,
          is_active: READ_ONLY,
        }),
      }),
    )

    expect(screen.queryByRole('button', { name: /^Edit / })).not.toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    rejectWith422({ line1: ['field not editable'] })
    renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('Street'))
    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('field not editable')).toBeInTheDocument()
  })

  it('refuses an empty street on the row without calling the server', async () => {
    renderDetail(operationalSiteFixture())

    fireEvent.click(pencilOf('Street'))
    fireEvent.change(screen.getByLabelText(/^Street/), { target: { value: '' } })
    confirmOpenRow('Save')

    expect(await screen.findByText(i18n.t('operationalSites.form.line1Required'))).toBeInTheDocument()
    expect(updateOperationalSiteMock).not.toHaveBeenCalled()
  })
})

describe('OperationalSiteDetailView — custom fields in place (spec 0021)', () => {
  it('shows the custom field value and PATCHes only that field', async () => {
    updateOperationalSiteMock.mockResolvedValue(operationalSiteFixture({ custom_fields: { zone: 'North-East' } }))
    renderDetail(operationalSiteFixture({ custom_fields: { zone: 'South' } }))

    expect(await screen.findByText('South')).toBeInTheDocument()
    fireEvent.click(pencilOf('Zone'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Zone' }), { target: { value: 'North-East' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateOperationalSiteMock).toHaveBeenCalledWith(9, { custom_fields: { zone: 'North-East' } }),
    )
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    rejectWith422({ 'custom_fields.zone': ['Zone must be shorter.'] })
    renderDetail(operationalSiteFixture({ custom_fields: { zone: 'South' } }))

    await screen.findByText('South')
    fireEvent.click(pencilOf('Zone'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Zone' }), { target: { value: 'North' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Zone must be shorter.')).toBeInTheDocument()
  })
})
