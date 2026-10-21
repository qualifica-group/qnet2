import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ContractTypeDetailView } from '@/features/contract-types/contract-type-detail'
import { moduleScreen } from '@/features/contract-types/contract-type-screens'
import type { ContractTypeDetail } from '@/features/contract-types/types'

/** Spec 0290: the detail edits every row in place, each save a PATCH of that field alone. */

const updateMock = vi.fn()

vi.mock('@/features/contract-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contract-types/api')>()),
  updateContractType: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function contractType(update = true): ContractTypeDetail {
  return {
    id: 4,
    name: 'Tempo determinato',
    is_active: true,
    sort_order: 3,
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

describe('ContractTypeDetailView (spec 0290)', () => {
  it('shows the name, the editable rows and the order', () => {
    renderDetail(<ContractTypeDetailView contractType={contractType()} />)

    expect(screen.getByRole('heading', { name: 'Tempo determinato' })).toBeInTheDocument()
    expect(editButton('Active')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('saves only the changed field (PATCH) when a switch is toggled', async () => {
    updateMock.mockResolvedValue({ ...contractType(), is_active: false })
    const onChanged = vi.fn()
    renderDetail(<ContractTypeDetailView contractType={contractType()} onChanged={onChanged} />)

    fireEvent.click(editButton('Active'))
    fireEvent.click(screen.getByRole('switch'))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, { is_active: false }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('hides the pencils without the update permission', () => {
    renderDetail(<ContractTypeDetailView contractType={contractType(false)} />)

    expect(screen.queryByRole('button', { name: /^Edit/ })).not.toBeInTheDocument()
  })

  it('registers as a no-edit-route module in the registry', () => {
    expect(moduleScreen.domain).toBe('contract-types')
    expect(moduleScreen.generateEditRoute).toBe(false)
  })
})
