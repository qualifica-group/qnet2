import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteCommissionRecipient } from '@/features/quotes/quote-commission-recipient'

/** User directive 2026-10-23: hovering a commission recipient leads to its record. */

const abilities = vi.hoisted(() => ({ granted: new Set<string>(['registries.view']) }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission) }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

const REGISTERED_DOMAINS = ['registries', 'referents']

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    REGISTERED_DOMAINS.includes(domain)
      ? {
          domain,
          basePath: `/${domain}`,
          defaultMode: 'page',
          labelKey: `navigation.${domain}`,
          DetailScreen: ({ id }: { id: number }) => <div>{`${domain}-detail-${id}`}</div>,
          FormScreen: () => null,
        }
      : undefined,
}))

function renderRecipient(type: 'registry' | 'referent') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <QuoteCommissionRecipient recipient={{ id: 7, name: 'Perry Johnson' }} type={type} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteCommissionRecipient', () => {
  it('links a registry to its record and shows the card with the link on hover', async () => {
    renderRecipient('registry')

    const link = screen.getByRole('link', { name: 'Perry Johnson' })
    expect(link).toHaveAttribute('href', '/registries/7')

    fireEvent.pointerEnter(link, { pointerType: 'mouse' })

    expect(await screen.findByText('Registry')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Perry Johnson' })).toHaveLength(2)
  })

  it('shows the plain name when the actor cannot view the record', () => {
    renderRecipient('referent')

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('Perry Johnson')).toBeInTheDocument()
  })
})
