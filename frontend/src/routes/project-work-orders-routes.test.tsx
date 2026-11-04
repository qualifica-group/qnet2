import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { matchRoutes } from 'react-router-dom'
import i18n from '@/i18n'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'
import { BreadcrumbTitleProvider } from '@/routes/breadcrumb-title'
import { router } from '@/routes/router'
import { navigation as itNavigation } from '@/i18n/locales/it-navigation'
import { navigation as enNavigation } from '@/i18n/locales/en-navigation'

vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: null }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Spec 0352 D-1, AC-016 (frontend side): the route, its breadcrumb and the menu label. */
describe('project work orders route (spec 0352)', () => {
  it('resolves /project-work-orders', () => {
    const matches = matchRoutes(router.routes, { pathname: '/project-work-orders' })
    expect(matches?.at(-1)?.route.path).toBe('project-work-orders')
  })

  it('shows the "Project Work Orders" breadcrumb', async () => {
    render(
      <MemoryRouter initialEntries={['/project-work-orders']}>
        <BreadcrumbTitleProvider>
          <AppBreadcrumbs />
        </BreadcrumbTitleProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByText('Project Work Orders')).toBeInTheDocument()
  })

  it('has the menu label in Italian and English', () => {
    expect(itNavigation.projectWorkOrders).toBe('Commesse di Progetto')
    expect(enNavigation.projectWorkOrders).toBe('Project Work Orders')
  })
})
