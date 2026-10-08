import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import {
  EDITABLE,
  READ_ONLY,
  pencilOf,
  permissionsFor,
  registryFixture,
  registryWrapper,
} from '@/features/registries/registry-test-fixtures'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/**
 * Spec 0207: the anagrafica's "Note generali" head the record's side column,
 * in the request work panel's callout, edited in place inside it.
 */

const updateRegistryMock = vi.fn()
vi.mock('@/features/registries/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/registries/api')>()),
  updateRegistry: (...args: unknown[]) => updateRegistryMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: permissionsFor() }),
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

const TITLE = 'General notes'

function renderDetail(registry: RegistryDetailWithPermissions) {
  const { wrapper } = registryWrapper()
  render(<RegistryDetailView registry={registry} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateRegistryMock.mockReset()
})

describe('RegistryDetailView — general notes (spec 0207)', () => {
  it('shows the note in its callout', () => {
    renderDetail(registryFixture({ general_notes: 'Call only in the morning' }))

    expect(within(screen.getByRole('region', { name: TITLE })).getByText('Call only in the morning')).toBeInTheDocument()
  })

  it('invites an editable empty note with the placeholder', () => {
    renderDetail(registryFixture({ permissions: permissionsFor({ general_notes: EDITABLE }) }))

    expect(
      within(screen.getByRole('region', { name: TITLE })).getByText('Write a note about this registry…'),
    ).toBeInTheDocument()
  })

  it('shows nothing for an empty note the actor cannot edit', () => {
    renderDetail(registryFixture({ permissions: permissionsFor({ general_notes: READ_ONLY }) }))

    expect(screen.queryByRole('region', { name: TITLE })).not.toBeInTheDocument()
  })

  it('PATCHes general_notes alone from the in-place editor', async () => {
    updateRegistryMock.mockResolvedValue(registryFixture({ general_notes: 'New note' }))
    renderDetail(registryFixture({ permissions: permissionsFor({ general_notes: EDITABLE }) }))

    fireEvent.click(pencilOf(TITLE))
    fireEvent.change(screen.getByRole('textbox', { name: /^General notes/ }), { target: { value: 'New note' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRegistryMock).toHaveBeenCalledWith(7, { general_notes: 'New note' }))
  })
})
