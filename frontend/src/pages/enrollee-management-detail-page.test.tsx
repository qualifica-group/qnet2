import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import EnrolleeManagementDetailPage from '@/pages/enrollee-management-detail-page'
import { workPanel } from '@/features/request-management/request-work-panel-fixtures'

// `PageHeader` renders `AppBreadcrumbs`, which needs an `AuthProvider` this
// suite does not mount: only the back link under test is real.
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

/**
 * Spec 0130 AC-015: `/enrollee-management/:id` mounts the SAME
 * `RequestWorkPanelScreen` Gestione Richieste uses, resolved onto
 * `/enrollee-management/{id}` (never `/request-management/{id}`) by
 * `RequestModuleProvider`, with a back link to the enrollee-management list.
 */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchRequestWorkPanelMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
}))

function renderDetailPage(id = '4001') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter initialEntries={[`/enrollee-management/${id}`]}>
          <Routes>
            <Route path="/enrollee-management/:id" element={<EnrolleeManagementDetailPage />} />
          </Routes>
        </MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  fetchRequestWorkPanelMock.mockResolvedValue(workPanel())
})

describe('EnrolleeManagementDetailPage (spec 0130 AC-015)', () => {
  it('fetches the panel under /enrollee-management, never /request-management', async () => {
    renderDetailPage()

    await waitFor(() => expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith('/enrollee-management', 4001))
  })

  it('links "back" to the enrollee-management list', async () => {
    renderDetailPage()

    await screen.findByRole('heading', { name: 'Preliminary information' })
    expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute('href', '/enrollee-management')
  })
})
