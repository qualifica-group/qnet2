import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * User directive 2026-08-04, on the create form as on the work panel (whose
 * twin suite is `request-attribution-rewards.test.tsx`): the "abbinamento
 * buono" control has no subject without a Segnalatore, so it is not rendered
 * disabled — it is not there at all, and appears with the app's reveal once a
 * reporter is picked.
 */

vi.mock('@/features/request-management/api', () => ({
  createRequest: vi.fn(),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

/** No connected actor: the Operatore/Sede defaults are not what this suite is about. */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const REFERENT: ForSelectItem = { id: 20, label: 'Mario Rossi' }

/** Same stub as `request-create-attribution-link.test.tsx`: a pick without a real dropdown, keyed on the trigger label. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      onClick={() => onChange(value === REFERENT.id ? null : REFERENT.id)}
    >
      {value ?? ''}
    </button>
  ),
}))

function renderForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      {/* The anagrafica section mounts `ContactsManager`, whose delete flow needs the app-level confirm dialog. */}
      <ConfirmDialogProvider>
        <RequestCreateForm onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

const reporterField = () => screen.getByTestId('select-Reporter')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('Create form — the reward control follows the reporter', () => {
  it('does not mount the control while no reporter is chosen', () => {
    renderForm()

    expect(screen.queryByRole('button', { name: 'Add reward' })).not.toBeInTheDocument()
    expect(screen.queryByText('Assigned rewards')).not.toBeInTheDocument()
    expect(screen.queryByText('Select a reporter first to assign a reward.')).not.toBeInTheDocument()
  })

  it('reveals it, enabled, as soon as a reporter is picked', async () => {
    renderForm()

    fireEvent.click(reporterField())

    expect(await screen.findByRole('button', { name: 'Add reward' })).toBeEnabled()
  })

  it('reveals it with the motion-safe animation, not as a hard cut', async () => {
    renderForm()

    fireEvent.click(reporterField())

    // The block's own root: the field label sits directly inside it.
    const block = (await screen.findByText('Assigned rewards')).parentElement
    expect(block).toHaveClass('motion-safe:animate-in')
  })

  it('unmounts the control again when the reporter is cleared with no reward attached', async () => {
    renderForm()
    fireEvent.click(reporterField())
    await screen.findByRole('button', { name: 'Add reward' })

    fireEvent.click(reporterField())

    expect(screen.queryByRole('button', { name: 'Add reward' })).not.toBeInTheDocument()
  })
})
