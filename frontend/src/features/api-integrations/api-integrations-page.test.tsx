import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import ApiDocsPage from '@/features/api-integrations/api-docs-page'
import ApiIntegrationsPage from '@/features/api-integrations/api-integrations-page'
import { createWrapper } from '@/features/api-integrations/test-support'

const permissions = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => vi.fn() }))
vi.mock('@/features/table/table-view', () => ({ TableView: () => <div>table</div> }))
vi.mock('@/features/api-integrations/components/api-docs-content', () => ({
  ApiDocsContent: () => <div>docs-content</div>,
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => permissions.granted.has(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  permissions.granted = new Set()
})

function renderPage(page: 'clients' | 'docs') {
  const { Wrapper } = createWrapper()
  render(
    <MemoryRouter>{page === 'clients' ? <ApiIntegrationsPage /> : <ApiDocsPage />}</MemoryRouter>,
    { wrapper: Wrapper },
  )
}

describe('ApiIntegrationsPage (AC-020)', () => {
  it('shows the access-denied message without api-clients.view', () => {
    renderPage('clients')
    expect(screen.getByText('You do not have permission to view API integrations.')).toBeInTheDocument()
    expect(screen.queryByText('table')).not.toBeInTheDocument()
  })

  it('shows only the clients (no tabs) with a link to the API documentation page', () => {
    permissions.granted = new Set(['api-clients.view'])
    renderPage('clients')

    expect(screen.getByText('table')).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'API documentation' })).toHaveAttribute('href', '/dev/api-docs')
  })
})

describe('ApiDocsPage', () => {
  it('shows the access-denied message without api-clients.view', () => {
    renderPage('docs')
    expect(screen.getByText('You do not have permission to view API integrations.')).toBeInTheDocument()
    expect(screen.queryByText('docs-content')).not.toBeInTheDocument()
  })

  it('renders the API reference with api-clients.view', () => {
    permissions.granted = new Set(['api-clients.view'])
    renderPage('docs')
    expect(screen.getByText('docs-content')).toBeInTheDocument()
  })
})
