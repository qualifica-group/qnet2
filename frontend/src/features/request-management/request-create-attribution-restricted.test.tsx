import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'

/**
 * User directive 2026-08-03: the create form's two supervisory attribution
 * controls each render only for their own ability — "Operatore" for
 * `request-management.assignOperator`, "Sede operativa" for
 * `operational-sites.viewAny` (the same one the field's server-side ceiling
 * hangs off). A role restricted on both, like the seeded Commercial, sees
 * neither. Mirrors `request-create-attribution-link.test.tsx`'s stubbing, with
 * `can` driven per test instead of always true.
 */

const grantedAbilities = new Set<string>()

vi.mock('@/features/request-management/api', () => ({
  createRequest: vi.fn(),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => grantedAbilities.has(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

/**
 * No connected actor: the Operatore/Sede defaults (user directive 2026-08-04)
 * would otherwise seed both controls, which is not what this suite is about —
 * they have their own coverage in
 * `use-request-create-form-actor-defaults.test.ts`.
 */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Stubbed to its accessible trigger name alone: what is under test is which controls exist. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button">{labels.triggerLabel}</button>
  ),
}))

const trigger = (name: string) => screen.queryByRole('button', { name })

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  grantedAbilities.clear()
  vi.clearAllMocks()
})

describe('Create form — supervisory attribution controls are gated one ability each', () => {
  it('renders neither Sede nor Operatore for an actor holding neither ability', () => {
    renderForm()

    expect(trigger('Operational site')).not.toBeInTheDocument()
    expect(trigger('Operator (GA2)')).not.toBeInTheDocument()
    // The rest of the attribution block is unaffected by the restriction.
    expect(trigger('Source')).toBeInTheDocument()
  })

  it('renders the Sede alone for an actor holding operational-sites.viewAny only', () => {
    grantedAbilities.add('operational-sites.viewAny')
    renderForm()

    expect(trigger('Operational site')).toBeInTheDocument()
    expect(trigger('Operator (GA2)')).not.toBeInTheDocument()
  })

  it('renders both for an actor holding both abilities', () => {
    grantedAbilities.add('operational-sites.viewAny')
    grantedAbilities.add('request-management.assignOperator')
    renderForm()

    expect(trigger('Operational site')).toBeInTheDocument()
    expect(trigger('Operator (GA2)')).toBeInTheDocument()
  })
})
