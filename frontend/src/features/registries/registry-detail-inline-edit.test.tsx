import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import {
  EDITABLE,
  READ_ONLY,
  pencilOf,
  permissionsFor,
  registryCard,
  registryFixture,
  registryWrapper,
} from '@/features/registries/registry-test-fixtures'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/**
 * Spec 0200: the anagrafica detail edits in place, one row at a time, custom
 * fields ("campi flessibili") and the anagraphic card included. Each save is
 * a PATCH of that field alone.
 */

const updateRegistryMock = vi.fn()
vi.mock('@/features/registries/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/registries/api')>()),
  updateRegistry: (...args: unknown[]) => updateRegistryMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/personal-data/addresses-manager', () => ({ AddressesManager: () => null }))
vi.mock('@/features/personal-data/contacts-manager', () => ({ ContactsManager: () => null }))
vi.mock('@/features/registries/registry-related-records', () => ({ RegistryRelatedRecords: () => null }))
vi.mock('@/components/ui/async-paginated-select', () => ({ AsyncPaginatedSelect: () => null }))
vi.mock('@/components/ui/async-paginated-multi-select', () => ({ AsyncPaginatedMultiSelect: () => null }))

const PRIORITY_FIELD: CustomFieldDescriptor = {
  key: 'custom.priority_level',
  type: 'text',
  label: 'Priority level',
  group: null,
  mandatory: false,
  source: 'custom',
}

function renderDetail(registry: RegistryDetailWithPermissions) {
  const { client, wrapper } = registryWrapper()
  render(<RegistryDetailView registry={registry} />, { wrapper })
  return client
}

/** The open row's own Save (the inline editor's confirm). */
function saveOpenRow() {
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [PRIORITY_FIELD],
    permissions: permissionsFor({ 'custom.priority_level': EDITABLE }),
  })
})

describe('RegistryDetailView — in-place editing (spec 0200)', () => {
  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = registryFixture({ vat_group: 'G-1' })
    updateRegistryMock.mockResolvedValue(saved)
    const client = renderDetail(registryFixture())

    fireEvent.click(pencilOf('VAT group'))
    fireEvent.change(screen.getByLabelText(/^VAT group/), { target: { value: 'G-1' } })
    saveOpenRow()

    await waitFor(() => expect(updateRegistryMock).toHaveBeenCalledWith(7, { vat_group: 'G-1' }))
    expect(client.getQueryData<RegistryDetailWithPermissions>(['registries', 'detail', 7])?.permissions).toEqual(
      saved.permissions,
    )
    await waitFor(() => expect(screen.queryByLabelText(/^VAT group/)).not.toBeInTheDocument())
  })

  it('closes on Cancel without calling the server', async () => {
    renderDetail(registryFixture())

    fireEvent.click(pencilOf('VAT group'))
    fireEvent.change(screen.getByLabelText(/^VAT group/), { target: { value: 'G-1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByLabelText(/^VAT group/)).not.toBeInTheDocument()
    expect(updateRegistryMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(registryFixture({ permissions: permissionsFor({ vat_group: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit VAT group' })).not.toBeInTheDocument()
    expect(pencilOf('Employee count')).toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    updateRegistryMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { vat_group: ['VAT group taken.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderDetail(registryFixture())

    fireEvent.click(pencilOf('VAT group'))
    fireEvent.change(screen.getByLabelText(/^VAT group/), { target: { value: 'G-1' } })
    saveOpenRow()

    expect(await screen.findByText('VAT group taken.')).toBeInTheDocument()
    vi.restoreAllMocks()
  })

  it('shows "Qualified supplier" only on a supplier', () => {
    renderDetail(registryFixture({ is_supplier: false }))
    expect(screen.queryByText('Qualified supplier')).not.toBeInTheDocument()
  })
})

describe('RegistryDetailView — custom fields in place (spec 0200 D-3)', () => {
  it('shows the custom field value and PATCHes only that field', async () => {
    updateRegistryMock.mockResolvedValue(registryFixture({ custom_fields: { priority_level: 'High' } }))
    renderDetail(registryFixture({ custom_fields: { priority_level: 'Low' } }))

    expect(await screen.findByText('Low')).toBeInTheDocument()
    fireEvent.click(pencilOf('Priority level'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Priority level' }), { target: { value: 'High' } })
    saveOpenRow()

    await waitFor(() =>
      expect(updateRegistryMock).toHaveBeenCalledWith(7, { custom_fields: { priority_level: 'High' } }),
    )
  })

  it('renders no custom field the role cannot see', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [PRIORITY_FIELD], permissions: permissionsFor() })
    renderDetail(
      registryFixture({
        custom_fields: { priority_level: 'Low' },
        permissions: permissionsFor({ 'custom.priority_level': { ...EDITABLE, visible: false, hidden: true } }),
      }),
    )

    await waitFor(() => expect(fetchResourceMetaMock).toHaveBeenCalled())
    expect(screen.queryByText('Priority level')).not.toBeInTheDocument()
  })
})

describe('RegistryDetailView — anagraphic card in place (spec 0200 D-4)', () => {
  it('sends the edited card as personal_data', async () => {
    updateRegistryMock.mockResolvedValue(registryFixture())
    renderDetail(registryFixture())

    fireEvent.click(pencilOf('Personal details'))
    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Augusta' } })
    saveOpenRow()

    await waitFor(() => expect(updateRegistryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateRegistryMock.mock.calls[0]
    expect(Object.keys(payload)).toEqual(['personal_data'])
    expect(payload.personal_data.first_name).toBe('Augusta')
  })

  it('refuses an incomplete card without calling the server', async () => {
    renderDetail(registryFixture())

    fireEvent.click(pencilOf('Personal details'))
    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: '' } })
    saveOpenRow()

    // The editor names the incomplete card and the card marks its own field.
    expect((await screen.findAllByRole('alert')).length).toBeGreaterThan(0)
    expect(updateRegistryMock).not.toHaveBeenCalled()
  })

  it('does not validate an untouched historical card when another field is saved', async () => {
    updateRegistryMock.mockResolvedValue(registryFixture())
    renderDetail(registryFixture({ personal_data: registryCard({ first_name: null }) }))

    fireEvent.click(pencilOf('Employee count'))
    fireEvent.change(screen.getByLabelText(/^Employee count/), { target: { value: '12' } })
    saveOpenRow()

    await waitFor(() => expect(updateRegistryMock).toHaveBeenCalledWith(7, { employee_count: 12 }))
  })

  it('keeps the card rows and the custom fields within the record sections', async () => {
    renderDetail(registryFixture({ custom_fields: { priority_level: 'Low' } }))
    // The section title, then the row's own (screen-reader) label.
    const identity = screen.getAllByText('Personal details')[0].closest('section') as HTMLElement
    expect(within(identity).getByText('Ada')).toBeInTheDocument()
    expect(await screen.findByText('Low')).toBeInTheDocument()
  })
})
