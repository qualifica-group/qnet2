import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useUserBulkActivation } from '@/features/users/use-user-bulk-activation'
import { bulkSetUsersActive } from '@/features/users/api'
import type { TableSelection } from '@/features/table/use-bulk-actions-slot'

/**
 * Spec 0310 AC-009: the users grid's "Activate selected" / "Deactivate
 * selected". Deactivating asks first, activating does not; one POST with the
 * selected ids; a mixed outcome is one warning toast; success refreshes and
 * clears the selection, a cancelled confirmation changes nothing.
 */

const confirmMock = vi.fn()
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => confirmMock }))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), warning: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/users/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/users/api')>('@/features/users/api')
  return { ...actual, bulkSetUsersActive: vi.fn() }
})

const SELECTION: TableSelection = { ids: [4, 7], rows: [] }
const onChanged = vi.fn()
const clearSelection = vi.fn()

function Harness() {
  const { getBulkActions } = useUserBulkActivation({ onChanged, clearSelection })
  return (
    <ul>
      {getBulkActions(SELECTION).map((item) => (
        <li key={item.key}>
          <button type="button" onClick={item.onSelect}>
            {item.label}
          </button>
        </li>
      ))}
    </ul>
  )
}

function renderHarness() {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <Harness />
    </QueryClientProvider>,
  )
}

const bulkMock = vi.mocked(bulkSetUsersActive)

describe('useUserBulkActivation', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
  })

  it('activates the selection without asking, then refreshes and clears it', async () => {
    bulkMock.mockResolvedValue({ updated: [4, 7], failed: [] })
    renderHarness()

    screen.getByRole('button', { name: 'Attiva selezionati' }).click()

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('2 utenti aggiornati.'))
    expect(confirmMock).not.toHaveBeenCalled()
    expect(bulkMock.mock.calls[0][0]).toEqual({ is_active: true, ids: [4, 7] })
    expect(onChanged).toHaveBeenCalledOnce()
    expect(clearSelection).toHaveBeenCalledOnce()
  })

  it('deactivates after the confirmation and reports the refused users in one warning', async () => {
    confirmMock.mockResolvedValue(true)
    bulkMock.mockResolvedValue({ updated: [7], failed: [{ id: 4, name: 'Mario Rossi', message: 'Non puoi disattivare il tuo account.' }] })
    renderHarness()

    screen.getByRole('button', { name: 'Disattiva selezionati' }).click()

    await waitFor(() =>
      expect(toast.warning).toHaveBeenCalledWith('1 aggiornati, 1 non riusciti.', {
        description: 'Mario Rossi: Non puoi disattivare il tuo account.',
      }),
    )
    expect(confirmMock.mock.calls[0][0]).toMatchObject({ title: 'Disattivare 2 utenti?' })
    expect(bulkMock.mock.calls[0][0]).toEqual({ is_active: false, ids: [4, 7] })
    expect(clearSelection).toHaveBeenCalledOnce()
  })

  it('changes nothing when the deactivation is cancelled', async () => {
    confirmMock.mockResolvedValue(false)
    renderHarness()

    screen.getByRole('button', { name: 'Disattiva selezionati' }).click()

    await waitFor(() => expect(confirmMock).toHaveBeenCalledOnce())
    expect(bulkMock).not.toHaveBeenCalled()
    expect(onChanged).not.toHaveBeenCalled()
  })
})
