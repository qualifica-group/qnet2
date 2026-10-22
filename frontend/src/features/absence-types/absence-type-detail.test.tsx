import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AbsenceTypeDetailView } from '@/features/absence-types/absence-type-detail'
import { moduleScreen } from '@/features/absence-types/absence-type-screens'
import type { AbsenceTypeDetail } from '@/features/absence-types/types'

/** Spec 0278: the detail edits every row in place, each save a PATCH of that field alone. */

const updateMock = vi.fn()

vi.mock('@/features/absence-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/absence-types/api')>()),
  updateAbsenceType: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function absenceType(update = true): AbsenceTypeDetail {
  return {
    id: 4,
    name: 'Ferie',
    deducts_balance: true,
    requires_approval: false,
    is_active: true,
    sort_order: 3,
    allows_full_day: true,
    allows_half_day: true,
    allows_hourly: true,
    min_notice_days: null,
    color: 'slate',
    default_annual_days: null,
    created_at: '2026-10-19T08:00:00Z',
    updated_at: '2026-10-19T08:00:00Z',
    permissions: {
      resource: { view: true, create: false, update, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
  }
}

function renderDetail(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

const editButton = (label: string) =>
  screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('AbsenceTypeDetailView (spec 0278)', () => {
  it('shows the booleans as Yes/No and the order', () => {
    renderDetail(<AbsenceTypeDetailView absenceType={absenceType()} />)

    expect(screen.getByRole('heading', { name: 'Ferie' })).toBeInTheDocument()
    expect(editButton('Deducts a balance')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('saves only the changed field (PATCH) when a switch is toggled', async () => {
    updateMock.mockResolvedValue({ ...absenceType(), requires_approval: true })
    const onChanged = vi.fn()
    renderDetail(<AbsenceTypeDetailView absenceType={absenceType()} onChanged={onChanged} />)

    fireEvent.click(editButton('Requires approval'))
    fireEvent.click(screen.getByRole('switch'))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, { requires_approval: true }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('hides the pencils without the update permission', () => {
    renderDetail(<AbsenceTypeDetailView absenceType={absenceType(false)} />)

    expect(screen.queryByRole('button', { name: /^Edit/ })).not.toBeInTheDocument()
  })

  it('registers as a no-edit-route module in the registry', () => {
    expect(moduleScreen.domain).toBe('absence-types')
    expect(moduleScreen.generateEditRoute).toBe(false)
  })
})
