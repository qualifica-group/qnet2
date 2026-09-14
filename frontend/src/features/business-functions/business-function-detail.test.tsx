import { render as rtlRender, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { BusinessFunctionDetailView } from '@/features/business-functions/business-function-detail'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'

/** Every render goes through a Router: the card links related records with real `<Link>`s. */
function render(ui: ReactElement) {
  return rtlRender(ui, { wrapper: MemoryRouter })
}

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

// Related-record links render only for an actor who can view the target
// module; these tests are not about abilities, so every ability is granted.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const BASE: BusinessFunctionDetailWithPermissions = {
  id: 1,
  name: 'Engineering',
  is_business_unit: true,
  is_business_service: false,
  type: 'business_unit',
  manager_id: 10,
  manager: { id: 10, name: 'Ada Lovelace', avatar_url: null },
  user_ids: [20, 21],
  users: [
    { id: 20, name: 'Grace Hopper', avatar_url: null },
    { id: 21, name: 'Katherine Johnson', avatar_url: null },
  ],
  parent_id: 5,
  parent: { id: 5, name: 'Operations' },
  operational_site_ids: [30],
  operational_sites: [{ id: 30, label: 'Via Roma 1 - Milano' }],
  created_at: '2026-01-15T10:30:00Z',
  permissions: {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: {},
    actions: {},
  },
}

describe('BusinessFunctionDetailView', () => {
  it('renders the name', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText('Engineering')).toBeInTheDocument()
  })

  it('renders the localized type label', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText(i18n.t('businessFunctions.form.type.businessUnit'))).toBeInTheDocument()
  })

  it('renders "none" when the function has no type', () => {
    render(<BusinessFunctionDetailView businessFunction={{ ...BASE, type: null }} />)
    expect(screen.getByText(i18n.t('businessFunctions.form.type.none'))).toBeInTheDocument()
  })

  it('renders the manager avatar and name', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText('AL')).toBeInTheDocument()
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
  })

  it('renders an em dash when there is no manager', () => {
    render(<BusinessFunctionDetailView businessFunction={{ ...BASE, manager: null }} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('renders every associated user with their avatar and name', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText('GH')).toBeInTheDocument()
    expect(screen.getByText('Grace Hopper')).toBeInTheDocument()
    expect(screen.getByText('KJ')).toBeInTheDocument()
    expect(screen.getByText('Katherine Johnson')).toBeInTheDocument()
  })

  it('renders the formatted creation date', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText(/2026/)).toBeInTheDocument()
  })

  it('renders the parent function when present', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText('Operations')).toBeInTheDocument()
  })

  it('omits the parent section for a top-level function', () => {
    render(<BusinessFunctionDetailView businessFunction={{ ...BASE, parent_id: null, parent: null }} />)
    expect(screen.queryByText(i18n.t('businessFunctions.detail.parent'))).not.toBeInTheDocument()
  })

  it('renders every operational site as a badge', () => {
    render(<BusinessFunctionDetailView businessFunction={BASE} />)
    expect(screen.getByText('Via Roma 1 - Milano')).toBeInTheDocument()
  })

  it('renders the empty state when no operational site is assigned', () => {
    render(
      <BusinessFunctionDetailView
        businessFunction={{ ...BASE, operational_site_ids: [], operational_sites: [] }}
      />,
    )
    expect(screen.getByText(i18n.t('businessFunctions.detail.operationalSites'))).toBeInTheDocument()
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
