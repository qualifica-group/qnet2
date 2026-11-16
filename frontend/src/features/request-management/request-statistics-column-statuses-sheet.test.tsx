import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { ColumnStatusesConfig } from '@/features/request-management/column-statuses-api'
import { RequestStatisticsColumnStatusesButton } from '@/features/request-management/request-statistics-column-statuses-sheet'

/**
 * Spec 0391 AC-012: the "Colonne e stati" action appears only with
 * `request-statistics.update`; its sheet shows the statuses chosen for the
 * active column, filters them by search, and "Save" sends every column.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchColumnStatusesMock = vi.fn()
const saveColumnStatusesMock = vi.fn()
vi.mock('@/features/request-management/column-statuses-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/request-management/column-statuses-api')>()),
  fetchColumnStatuses: (...args: unknown[]) => fetchColumnStatusesMock(...args),
  saveColumnStatuses: (...args: unknown[]) => saveColumnStatusesMock(...args),
}))

const CONFIG: ColumnStatusesConfig = {
  columns: [
    { key: 'unhandled_new_contacts', twin_key: 'nuovi_contatti', status_ids: [10] },
    { key: 'current_potentials', twin_key: 'potenziali', status_ids: [11] },
    { key: 'associati', twin_key: null, status_ids: [] },
    { key: 'trattative_concluse', twin_key: null, status_ids: [] },
    { key: 'invio_presa_in_carico', twin_key: null, status_ids: [] },
  ],
  workflows: [
    {
      id: null,
      name: 'Stati predefiniti',
      is_active: true,
      categories: [],
      statuses: [{ id: 1, name: 'Aperta', group: 'open', color: null }],
    },
    {
      id: 26,
      name: 'ISO',
      is_active: true,
      categories: ['ISO 9001'],
      statuses: [
        { id: 10, name: 'Nuovo Contatto', group: 'open', color: null },
        { id: 11, name: 'In trattativa', group: 'pending', color: null },
        { id: 12, name: 'Rimandata', group: 'open', color: null },
      ],
    },
  ],
}

function renderButton() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )

  return render(<RequestStatisticsColumnStatusesButton />, { wrapper })
}

async function openSheet() {
  fireEvent.click(screen.getByRole('button', { name: 'Columns and statuses' }))
  return screen.findByRole('dialog')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockImplementation((permission) => permission === 'request-statistics.update')
  fetchColumnStatusesMock.mockReset().mockResolvedValue(CONFIG)
  saveColumnStatusesMock.mockReset().mockResolvedValue(CONFIG)
})

describe('RequestStatisticsColumnStatusesButton (spec 0391)', () => {
  it('is hidden without request-statistics.update and fetches nothing', () => {
    canMock.mockReturnValue(false)
    renderButton()

    expect(screen.queryByRole('button', { name: 'Columns and statuses' })).not.toBeInTheDocument()
    expect(fetchColumnStatusesMock).not.toHaveBeenCalled()
  })

  it('checks the statuses chosen for the active column and switches column from the tabs', async () => {
    renderButton()
    const dialog = await openSheet()

    expect(await within(dialog).findByRole('checkbox', { name: 'Nuovo Contatto' })).toBeChecked()
    expect(within(dialog).getByRole('checkbox', { name: 'In trattativa' })).not.toBeChecked()
    expect(within(dialog).getByText('Also applies to "Unhandled New Contacts (selected period)".')).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('tab', { name: 'Potential Leads (1)' }))

    expect(within(dialog).getByRole('checkbox', { name: 'In trattativa' })).toBeChecked()
    expect(within(dialog).getByRole('checkbox', { name: 'Nuovo Contatto' })).not.toBeChecked()
  })

  it('filters the workflows and statuses by search', async () => {
    renderButton()
    const dialog = await openSheet()
    await within(dialog).findByRole('checkbox', { name: 'Aperta' })

    fireEvent.change(within(dialog).getByRole('searchbox'), { target: { value: 'rimand' } })

    expect(within(dialog).getByRole('checkbox', { name: 'Rimandata' })).toBeInTheDocument()
    expect(within(dialog).queryByRole('checkbox', { name: 'Aperta' })).not.toBeInTheDocument()
    expect(within(dialog).queryByRole('checkbox', { name: 'Nuovo Contatto' })).not.toBeInTheDocument()
  })

  it('saves the choice of every column, the edited one included', async () => {
    renderButton()
    const dialog = await openSheet()
    const save = within(dialog).getByRole('button', { name: 'Save' })
    expect(save).toBeDisabled()

    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Rimandata' }))
    fireEvent.click(save)

    await waitFor(() => expect(saveColumnStatusesMock).toHaveBeenCalledTimes(1))
    expect(saveColumnStatusesMock.mock.calls[0][1]).toEqual({
      unhandled_new_contacts: [10, 12],
      current_potentials: [11],
      associati: [],
      trattative_concluse: [],
      invio_presa_in_carico: [],
    })
  })
})
