import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import ApiIntegrationsPage from '@/features/api-integrations/api-integrations-page'
import { createWrapper } from '@/features/api-integrations/test-support'

const permissions = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => vi.fn() }))
vi.mock('@/features/table/table-view', () => ({ TableView: () => <div>table</div> }))
vi.mock('@/features/api-integrations/components/api-docs-tab', () => ({
  ApiDocsTab: () => <div>docs-content</div>,
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

describe('ApiIntegrationsPage (AC-020)', () => {
  it('shows the access-denied message without api-clients.view', () => {
    render(<ApiIntegrationsPage />, { wrapper: createWrapper().Wrapper })
    expect(screen.getByText('You do not have permission to view API integrations.')).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })

  it('shows the two tabs and switches to the documentation', () => {
    permissions.granted = new Set(['api-clients.view'])
    render(<ApiIntegrationsPage />, { wrapper: createWrapper().Wrapper })

    expect(screen.getByRole('tab', { name: 'API clients', selected: true })).toBeInTheDocument()
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Documentation' }))
    fireEvent.click(screen.getByRole('tab', { name: 'Documentation' }))
    expect(screen.getByText('docs-content')).toBeInTheDocument()
  })
})
