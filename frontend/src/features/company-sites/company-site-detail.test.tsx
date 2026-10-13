import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'
import {
  companySiteFixture,
  companySiteWrapper,
  permissionsFor,
} from '@/features/company-sites/company-site-test-fixtures'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/**
 * Spec 0020 AC-020: the "Set as default site" action appears only when the
 * site is not already the default, calls `set-default` and notifies the
 * caller so the grid can refresh. Plus what the detail shows; its in-place
 * editing is covered by `company-site-detail-inline-edit.test.tsx`.
 */

const setDefaultCompanySiteMock = vi.fn()
vi.mock('@/features/company-sites/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/company-sites/api')>()),
  setDefaultCompanySite: (...args: unknown[]) => setDefaultCompanySiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The closed company row links to the company, gated by the abilities of the logged-in actor.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/personal-data/addresses-manager', () => ({ AddressesManager: () => null }))
vi.mock('@/features/personal-data/contacts-manager', () => ({ ContactsManager: () => null }))

vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: permissionsFor() }),
}))

function renderDetail(site: CompanySiteDetailWithPermissions, onChanged = vi.fn()) {
  const { wrapper } = companySiteWrapper()
  render(<CompanySiteDetailView companySite={site} onChanged={onChanged} />, { wrapper })
  return onChanged
}

function withActions(actions: Record<string, boolean>) {
  return { ...permissionsFor(), actions }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  setDefaultCompanySiteMock.mockReset()
})

describe('CompanySiteDetailView — set-default (AC-020)', () => {
  it('shows the action when the site is not the default', () => {
    renderDetail(companySiteFixture({ permissions: withActions({ set_default: true }) }))

    expect(screen.getByRole('button', { name: /Set as default site/ })).toBeInTheDocument()
  })

  it('hides the action when the site is already the default', () => {
    renderDetail(companySiteFixture({ is_default: true, permissions: withActions({ set_default: true }) }))

    expect(screen.queryByRole('button', { name: /Set as default site/ })).not.toBeInTheDocument()
    expect(screen.getByText('Default')).toBeInTheDocument()
  })

  it('hides the action when the actor lacks the set_default permission', () => {
    renderDetail(companySiteFixture({ permissions: withActions({ set_default: false }) }))

    expect(screen.queryByRole('button', { name: /Set as default site/ })).not.toBeInTheDocument()
  })

  it('calls set-default, seeds the cache and notifies the caller on click', async () => {
    setDefaultCompanySiteMock.mockResolvedValue(companySiteFixture({ is_default: true }))
    const onChanged = renderDetail(companySiteFixture({ permissions: withActions({ set_default: true }) }))

    screen.getByRole('button', { name: /Set as default site/ }).click()

    await waitFor(() => expect(setDefaultCompanySiteMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(onChanged).toHaveBeenCalledTimes(1))
  })
})

describe('CompanySiteDetailView — what it shows', () => {
  it('shows the name, notes, company, card and banks', () => {
    renderDetail(companySiteFixture())

    expect(screen.getAllByText('Sede Nord').length).toBeGreaterThan(0)
    expect(screen.getByText('Main office')).toBeInTheDocument()
    expect(screen.getAllByText('Holding Srl').length).toBeGreaterThan(0)
    expect(screen.getAllByText('ACME S.p.A.').length).toBeGreaterThan(0)
    expect(screen.getByText('Intesa')).toBeInTheDocument()
    expect(screen.getByText('IT60X0542811101000000123456')).toBeInTheDocument()
  })

  it('shows the em dash for a site without notes, company and banks', () => {
    renderDetail(companySiteFixture({ notes: null, company: null, banks: [] }))

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})
