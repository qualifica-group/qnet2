import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RecordLink } from '@/components/detail/record-link'

/**
 * User directive 2026-09-14: a link to another record on a detail opens that
 * record in a modal (with the toolbar jump to its dedicated page), never
 * navigates on a plain click; modified clicks stay with the browser.
 */

const abilities = vi.hoisted(() => ({ granted: new Set<string>(['projects.view']) }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission) }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'projects'
      ? {
          domain: 'projects',
          basePath: '/projects',
          defaultMode: 'page',
          labelKey: 'navigation.projects',
          DetailScreen: ({ id }: { id: number }) => <div>{`detail-${id}`}</div>,
          FormScreen: () => null,
        }
      : undefined,
}))

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="location">{location.pathname}</div>
}

function renderLink(domain: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/tasks/1']}>
        <RecordLink domain={domain} id={5}>
          Project Alpha
        </RecordLink>
        <LocationProbe />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

afterEach(() => {
  abilities.granted = new Set(['projects.view'])
})

describe('RecordLink', () => {
  it('keeps a real href to the record page', () => {
    renderLink('projects')

    expect(screen.getByRole('link', { name: /Project Alpha/ })).toHaveAttribute('href', '/projects/5')
  })

  it('opens the record in a modal on a plain click, even when the module opens as a page, and stays on the detail', () => {
    renderLink('projects')

    fireEvent.click(screen.getByRole('link', { name: /Project Alpha/ }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('detail-5')).toBeInTheDocument()
    expect(screen.getByTestId('location')).toHaveTextContent('/tasks/1')
  })

  it('offers the jump from the modal to the dedicated page', () => {
    renderLink('projects')

    fireEvent.click(screen.getByRole('link', { name: /Project Alpha/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Open detail page' }))

    expect(screen.getByTestId('location')).toHaveTextContent('/projects/5')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('leaves a modified click (new tab) to the browser, without opening the modal', () => {
    renderLink('projects')

    fireEvent.click(screen.getByRole('link', { name: /Project Alpha/ }), { metaKey: true })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('degrades to plain text when the actor cannot view the target record', () => {
    abilities.granted = new Set()
    renderLink('projects')

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('Project Alpha')).toBeInTheDocument()
  })

  it('degrades to plain text for an unregistered domain', () => {
    renderLink('unknown')

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('Project Alpha')).toBeInTheDocument()
  })
})
