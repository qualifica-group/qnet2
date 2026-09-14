import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'
import { BreadcrumbTitleProvider, useBreadcrumbTitle } from '@/routes/breadcrumb-title'

/**
 * Spec 0022 AC-A5 — an entity detail/edit URL must read as
 * "Registries / Acme S.p.A.", never as the raw id. The navigation tree (which
 * only supplies the module icon) is stubbed.
 */
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: null }),
}))

/** Stands in for a detail page: registers the entity name for its own path. */
function DetailPageStub({ href, name }: { href: string; name: string | undefined }) {
  useBreadcrumbTitle(href, name)
  return null
}

function renderAt(path: string, page: React.ReactNode) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <BreadcrumbTitleProvider>
        <AppBreadcrumbs />
        {page}
      </BreadcrumbTitleProvider>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AppBreadcrumbs', () => {
  it('shows the raw segment while the entity name is unknown (still loading)', () => {
    renderAt('/registries/12', <DetailPageStub href="/registries/12" name={undefined} />)

    expect(screen.getByText('12')).toBeInTheDocument()
  })

  it('replaces the id crumb with the registered entity name', async () => {
    renderAt('/registries/12', <DetailPageStub href="/registries/12" name="Acme S.p.A." />)

    expect(await screen.findByText('Acme S.p.A.')).toBeInTheDocument()
    expect(screen.queryByText('12')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Registries' })).toHaveAttribute(
      'href',
      '/registries',
    )
  })

  it('keeps the entity name on the edit route and labels the trailing segment', async () => {
    renderAt('/registries/12/edit', <DetailPageStub href="/registries/12" name="Acme S.p.A." />)

    expect(await screen.findByRole('link', { name: 'Acme S.p.A.' })).toHaveAttribute(
      'href',
      '/registries/12',
    )
    expect(screen.getByText('Edit')).toBeInTheDocument()
  })

  it('labels the referents segment (it was missing from the segment map)', () => {
    renderAt('/referents', null)

    expect(screen.getByText('Referents')).toBeInTheDocument()
  })

  it('labels the time-entries segment (it was missing from the segment map)', () => {
    renderAt('/time-entries', null)

    expect(screen.getByText('Time tracking')).toBeInTheDocument()
  })
})
