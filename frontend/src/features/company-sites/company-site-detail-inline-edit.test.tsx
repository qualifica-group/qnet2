import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'
import {
  EDITABLE,
  READ_ONLY,
  companySiteFixture,
  companySiteWrapper,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
} from '@/features/company-sites/company-site-test-fixtures'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/**
 * The company-site detail edits in place, one row at a time, the company-locked
 * anagraphic card, the banks and the custom fields included (wave 3 of the "no
 * edit pages" plan): there is no Edit button, and each save is a PATCH of that
 * field alone.
 */

const updateCompanySiteMock = vi.fn()
vi.mock('@/features/company-sites/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/company-sites/api')>()),
  updateCompanySite: (...args: unknown[]) => updateCompanySiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// The closed company row links to the company, gated by the abilities of the logged-in actor.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/personal-data/addresses-manager', () => ({ AddressesManager: () => null }))
vi.mock('@/features/personal-data/contacts-manager', () => ({ ContactsManager: () => null }))

// The banks manager has its own suite: a stub that empties the list in one click.
vi.mock('@/features/company-sites/banks-manager', () => ({
  BanksCountBadge: ({ count }: { count: number }) => <span>{`banks-${count}`}</span>,
  BanksManager: ({ onChange }: { onChange: (next: []) => void }) => (
    <button type="button" onClick={() => onChange([])}>
      drop-banks
    </button>
  ),
}))

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelect: () => ({
    data: undefined,
    isPending: false,
    isError: false,
    fetchNextPage: () => {},
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: () => {},
  }),
  flattenForSelectPages: () => [],
  useForSelectLabels: () => new Map(),
}))

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums: {} } }),
  useEnumOptions: () => [],
}))

const PRIORITY_FIELD: CustomFieldDescriptor = {
  key: 'custom.priority',
  type: 'text',
  label: 'Priority',
  group: null,
  mandatory: false,
  source: 'custom',
}

function renderDetail(site: CompanySiteDetailWithPermissions) {
  const { client, wrapper } = companySiteWrapper()
  render(<CompanySiteDetailView companySite={site} />, { wrapper })
  return client
}

function rejectWith422(errors: Record<string, string[]>) {
  updateCompanySiteMock.mockRejectedValue(
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
  updateCompanySiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [PRIORITY_FIELD],
    permissions: permissionsFor({ 'custom.priority': EDITABLE }),
  })
})

describe('CompanySiteDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    renderDetail(companySiteFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Name')).toBeInTheDocument()
    expect(pencilOf('Company details')).toBeInTheDocument()
    expect(pencilOf('Banks')).toBeInTheDocument()
  })

  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = companySiteFixture({ name: 'Sede Sud' })
    updateCompanySiteMock.mockResolvedValue(saved)
    const client = renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sede Sud' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanySiteMock).toHaveBeenCalledWith(7, { name: 'Sede Sud' }))
    expect(
      client.getQueryData<CompanySiteDetailWithPermissions>(['company-sites', 'detail', 7])?.permissions,
    ).toEqual(saved.permissions)
    await waitFor(() => expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument())
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument()
    expect(updateCompanySiteMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(companySiteFixture({ permissions: permissionsFor({ name: READ_ONLY, banks: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Name' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Banks' })).not.toBeInTheDocument()
    expect(pencilOf('Notes')).toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    rejectWith422({ name: ['Name already taken.'] })
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('refuses an empty name on the row without calling the server', async () => {
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: '' } })
    confirmOpenRow('Save')

    expect(await screen.findByText(i18n.t('companySites.form.nameRequired'))).toBeInTheDocument()
    expect(updateCompanySiteMock).not.toHaveBeenCalled()
  })
})

describe('CompanySiteDetailView — card and banks in place', () => {
  it('edits the card locked to a company and sends it as personal_data alone', async () => {
    updateCompanySiteMock.mockResolvedValue(companySiteFixture())
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Company details'))
    expect(screen.queryByLabelText(/^First name/)).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Individual' })).not.toBeInTheDocument()
    fireEvent.change(screen.getByLabelText(/^Company name/), { target: { value: 'ACME Group' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanySiteMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateCompanySiteMock.mock.calls[0]
    expect(id).toBe(7)
    expect(Object.keys(payload)).toEqual(['personal_data'])
    expect(payload.personal_data.company_name).toBe('ACME Group')
  })

  it('refuses an incomplete card without calling the server', async () => {
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Company details'))
    fireEvent.change(screen.getByLabelText(/^Company name/), { target: { value: '' } })
    confirmOpenRow('Save')

    expect(await screen.findByText(/^Complete the required personal data fields: /)).toBeInTheDocument()
    expect(updateCompanySiteMock).not.toHaveBeenCalled()
  })

  it('surfaces a personal_data.* 422 inside the open editor', async () => {
    rejectWith422({ 'personal_data.vat_number': ['The VAT number is invalid.'] })
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Company details'))
    fireEvent.change(screen.getByLabelText(/^Company name/), { target: { value: 'ACME Group' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('The VAT number is invalid.')).toBeInTheDocument()
  })

  it('sends the whole banks list as banks alone', async () => {
    updateCompanySiteMock.mockResolvedValue(companySiteFixture({ banks: [] }))
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Banks'))
    fireEvent.click(screen.getByRole('button', { name: 'drop-banks' }))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateCompanySiteMock).toHaveBeenCalledWith(7, { banks: [] }))
  })

  it('Cancel on the banks row discards the unconfirmed list', () => {
    renderDetail(companySiteFixture())

    fireEvent.click(pencilOf('Banks'))
    fireEvent.click(screen.getByRole('button', { name: 'drop-banks' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByText('Intesa')).toBeInTheDocument()
    expect(updateCompanySiteMock).not.toHaveBeenCalled()
  })
})

describe('CompanySiteDetailView — custom fields in place (spec 0021)', () => {
  it('shows the custom field value and PATCHes only that field', async () => {
    updateCompanySiteMock.mockResolvedValue(companySiteFixture({ custom_fields: { priority: 'Low' } }))
    renderDetail(companySiteFixture({ custom_fields: { priority: 'High' } }))

    expect(await screen.findByText('High')).toBeInTheDocument()
    fireEvent.click(pencilOf('Priority'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Priority' }), { target: { value: 'Low' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateCompanySiteMock).toHaveBeenCalledWith(7, { custom_fields: { priority: 'Low' } }),
    )
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    rejectWith422({ 'custom_fields.priority': ['Priority must be shorter.'] })
    renderDetail(companySiteFixture({ custom_fields: { priority: 'High' } }))

    await screen.findByText('High')
    fireEvent.click(pencilOf('Priority'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Priority' }), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Priority must be shorter.')).toBeInTheDocument()
  })
})
