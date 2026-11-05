import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import RequestImportPage from '@/pages/request-import-page'

/**
 * Spec 0348: the page gates the wizard behind `request-management.import`
 * (UI-only; the backend re-checks it on every endpoint of the domain).
 */

vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))

vi.mock('@/features/imports/wizard/import-wizard', () => ({
  ImportWizard: ({ domain }: { domain: string }) => <div>{`wizard:${domain}`}</div>,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RequestImportPage', () => {
  it('shows the forbidden fallback without request-management.import', () => {
    canMock.mockReturnValue(false)
    render(<RequestImportPage />)

    expect(screen.getByText('You do not have permission to import requests.')).toBeInTheDocument()
    expect(screen.queryByText('wizard:request-management')).not.toBeInTheDocument()
  })

  it('mounts the wizard for the request-management domain with the permission', () => {
    canMock.mockImplementation((permission: string) => permission === 'request-management.import')
    render(<RequestImportPage />)

    expect(screen.getByText('wizard:request-management')).toBeInTheDocument()
  })
})
