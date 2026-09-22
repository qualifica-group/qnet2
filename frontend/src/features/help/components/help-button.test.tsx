import { describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { HelpButton } from '@/features/help/components/help-button'

/** Spec 0143 AC-001 (button) and AC-010 (accessible panel, Esc closes, focus returns). */

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: [], isLoading: false }),
}))
vi.mock('@/features/help/help-content-loader', async () => {
  const actual = await vi.importActual<typeof import('@/features/help/help-content-loader')>(
    '@/features/help/help-content-loader',
  )
  return { ...actual, loadHelpGuide: () => Promise.resolve(null) }
})

function renderButton() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <HelpButton />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('HelpButton', () => {
  it('exposes an accessible "Guida" name (AC-001)', () => {
    renderButton()
    expect(screen.getByRole('button', { name: 'Guida' })).toBeInTheDocument()
  })

  it('opens a panel with an accessible title, closes on Escape and returns focus to the button (AC-010)', async () => {
    renderButton()
    const button = screen.getByRole('button', { name: 'Guida' })
    button.focus()

    fireEvent.click(button)

    const dialog = await screen.findByRole('dialog', { name: 'Guida' })
    fireEvent.keyDown(dialog, { key: 'Escape' })

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(button).toHaveFocus()
  })
})
