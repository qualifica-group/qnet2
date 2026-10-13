import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import {
  EDITABLE,
  HIDDEN,
  clickCreateSave,
  companySiteFixture,
  companySiteWrapper,
  confirmOpenRow,
  permissionsFor,
} from '@/features/company-sites/company-site-test-fixtures'

/**
 * The company-site create form is a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked, nothing saved per
 * row; Salva validates and creates the whole draft. The card is locked to a
 * company; custom fields included.
 */

const createCompanySiteMock = vi.fn()
const uploadCompanySiteLogoMock = vi.fn()
vi.mock('@/features/company-sites/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/company-sites/api')>()),
  createCompanySite: (...args: unknown[]) => createCompanySiteMock(...args),
  uploadCompanySiteLogo: (...args: unknown[]) => uploadCompanySiteLogoMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/personal-data/addresses-manager', () => ({ AddressesManager: () => null }))
vi.mock('@/features/personal-data/contacts-manager', () => ({ ContactsManager: () => null }))

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

function renderForm(onSuccess = vi.fn()) {
  const { wrapper } = companySiteWrapper()
  render(<CompanySiteForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
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
  createCompanySiteMock.mockReset()
  uploadCompanySiteLogoMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('CompanySiteForm — create', () => {
  it('opens on closed rows: nothing is editable until a pencil is clicked', async () => {
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Name' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('creates the site with the name and the company-locked card built row by row', async () => {
    const created = companySiteFixture()
    createCompanySiteMock.mockResolvedValue(created)
    const onSuccess = renderForm()

    await fillRow('Name', /^Name/, 'Sede Nord')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Company details' }))
    expect(screen.queryByLabelText(/^First name/)).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Individual' })).not.toBeInTheDocument()
    fireEvent.change(await screen.findByLabelText(/^Company name/), { target: { value: 'ACME S.p.A.' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createCompanySiteMock).toHaveBeenCalledTimes(1))
    const payload = createCompanySiteMock.mock.calls[0][0]
    expect(payload.name).toBe('Sede Nord')
    expect(payload.personal_data).toMatchObject({ type: 'company', company_name: 'ACME S.p.A.' })
    expect('banks' in payload).toBe(false)
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created))
  })

  it('refuses a draft without name and card, marking the rows, without calling the server', async () => {
    renderForm()
    await screen.findByRole('button', { name: 'Edit Name' })

    clickCreateSave()

    expect(await screen.findByText(i18n.t('companySites.form.nameRequired'))).toBeInTheDocument()
    expect(createCompanySiteMock).not.toHaveBeenCalled()
  })

  it('the identity band follows the name being typed', async () => {
    renderForm()

    expect(await screen.findByRole('heading', { name: i18n.t('companySites.form.createTitle') })).toBeInTheDocument()
    await fillRow('Name', /^Name/, 'Sede Nord')
    expect(screen.getByRole('heading', { name: 'Sede Nord' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [PRIORITY_FIELD],
      permissions: permissionsFor({ 'custom.priority': EDITABLE }),
    })
    createCompanySiteMock.mockResolvedValue(companySiteFixture())
    renderForm()

    await fillRow('Name', /^Name/, 'Sede Nord')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Company details' }))
    fireEvent.change(await screen.findByLabelText(/^Company name/), { target: { value: 'ACME S.p.A.' } })
    confirmOpenRow('Done')
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Priority' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Priority' }), { target: { value: 'High priority' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createCompanySiteMock).toHaveBeenCalledTimes(1))
    expect(createCompanySiteMock.mock.calls[0][0].custom_fields).toEqual({ priority: 'High priority' })
  })
})

describe('CompanySiteForm — metadata-driven authorization (spec 0020)', () => {
  it('hides the company and banks rows when their fields are hidden, keeping the name and the card', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ company_id: HIDDEN, banks: HIDDEN }),
    })
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Name' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Company details' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Company' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Banks' })).not.toBeInTheDocument()
  })
})
