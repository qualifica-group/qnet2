import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { ArchivioSmartConnectionButton } from '@/features/document-checklists/archivio-smart-connection-button'

const abilities = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission), isLoading: false }),
}))

function renderButton() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ArchivioSmartConnectionButton />
    </QueryClientProvider>,
  )
}

// Spec 0403 AC-026: the connection button is shown only with document-checklists.manageConnection.
describe('ArchivioSmartConnectionButton', () => {
  it('is hidden without the manageConnection permission', () => {
    abilities.granted = new Set(['document-checklists.update'])
    renderButton()

    expect(screen.queryByRole('button')).toBeNull()
  })

  it('is shown with the manageConnection permission', () => {
    abilities.granted = new Set(['document-checklists.manageConnection'])
    renderButton()

    expect(screen.getByRole('button')).toBeInTheDocument()
  })
})
