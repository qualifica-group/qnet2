import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import i18n from '@/i18n'
import ModuleDetailPage from '@/features/modules/module-detail-page'
import type { ModuleDetailScreenProps } from '@/features/modules/types'

/**
 * Covers the `onEdit`/`detailOwnsEditAction` wiring added on top of the
 * existing generic detail page (spec 0042): a module that opts in gets its
 * `DetailScreen` handed a navigate-to-edit callback and loses the header's
 * own "Edit" button, so the action is not rendered twice; every other module
 * (the default, `detailOwnsEditAction` absent) keeps its original header
 * button untouched.
 */

function StubDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  return (
    <div>
      <div>{`detail-${id}`}</div>
      {onEdit ? <button onClick={onEdit}>screen-edit</button> : null}
    </div>
  )
}

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) => {
    if (domain === 'projects') {
      return {
        domain: 'projects',
        basePath: '/projects',
        defaultMode: 'page',
        labelKey: 'navigation.projects',
        DetailScreen: StubDetailScreen,
        FormScreen: () => null,
      }
    }
    if (domain === 'opportunities') {
      return {
        domain: 'opportunities',
        basePath: '/opportunities',
        defaultMode: 'page',
        labelKey: 'navigation.opportunities',
        DetailScreen: StubDetailScreen,
        FormScreen: () => null,
        detailOwnsEditAction: true,
      }
    }
    return undefined
  },
}))

vi.mock('@/features/auth/can', () => ({
  Can: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <div>{actions}</div>,
}))

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="location">{location.pathname}</div>
}

function renderAt(path: string, domain: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path={`/${domain}/:id`} element={<ModuleDetailPage domain={domain} />} />
      </Routes>
      <LocationProbe />
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ModuleDetailPage', () => {
  it('regression: with detailOwnsEditAction absent, the header renders its own Edit button and DetailScreen still receives onEdit', () => {
    renderAt('/projects/5', 'projects')

    expect(screen.getByText('detail-5')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /edit/i })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'screen-edit' }))
    expect(screen.getByTestId('location')).toHaveTextContent('/projects/5/edit')
  })

  it('detailOwnsEditAction: true omits the header Edit button and the DetailScreen callback navigates to the edit route', () => {
    renderAt('/opportunities/9', 'opportunities')

    expect(screen.getByText('detail-9')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /edit/i })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'screen-edit' }))
    expect(screen.getByTestId('location')).toHaveTextContent('/opportunities/9/edit')
  })
})
