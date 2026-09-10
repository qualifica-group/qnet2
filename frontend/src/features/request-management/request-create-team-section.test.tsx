import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'

/**
 * Spec 0097 rev-2 D-7/D-9, create-form half (AC-010): the team is a section of
 * its own here too — the Offerta's Supervisore plus its G.A. slots — while
 * "Attribuzione" keeps the Fonte/Segnalatore/Sede trio. Twin of
 * `request-team-section.test.tsx`, as these two screens are twins everywhere
 * else.
 *
 * The two sections are gated differently, which is the point of the last case:
 * assigning the team is supervisory (`request-management.assignOperator`,
 * user directive 2026-08-03), the Supervisore is not — it is an attribution
 * field like Fonte, gated wholesale by `request-management.create`.
 */

const grantedAbilities = new Set<string>()

vi.mock('@/features/request-management/api', () => ({
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
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

/** No connected actor: the Operatore/Sede defaults have their own suite and would seed both controls here. */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Stubbed to its accessible trigger name alone: what is under test is which section each control lives in. */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button">{labels.triggerLabel}</button>
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

/** The card a section heading belongs to — `FormSection` renders no landmark of its own. */
function sectionOf(title: string): HTMLElement {
  return screen.getByRole('heading', { name: title }).closest('section') as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  grantedAbilities.clear()
  vi.clearAllMocks()
})

describe('Create form — the team is a section of its own (AC-010)', () => {
  it('groups the Supervisore and the G.A. slots under "Team"', () => {
    grantedAbilities.add('request-management.assignOperator')
    renderForm()
    const team = sectionOf('Team')

    expect(within(team).getByRole('button', { name: 'Supervisor' })).toBeInTheDocument()
    expect(within(team).getByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(within(team).getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  /**
   * Requirement changed (user directive 2026-09-10): the Sede left this
   * section too, into a card of its own directly above the Team it scopes.
   * "Attribution" is the Fonte/Segnalatore pair alone now.
   */
  it('leaves "Attribution" with the Fonte/Segnalatore pair alone', () => {
    grantedAbilities.add('request-management.assignOperator')
    grantedAbilities.add('operational-sites.viewAny')
    renderForm()
    const attribution = sectionOf('Attribution')

    expect(within(attribution).getByRole('button', { name: 'Source' })).toBeInTheDocument()
    expect(within(attribution).getByRole('button', { name: 'Reporter' })).toBeInTheDocument()
    expect(within(attribution).queryByRole('button', { name: 'Operational site' })).not.toBeInTheDocument()
    expect(within(attribution).queryByRole('button', { name: 'Supervisor' })).not.toBeInTheDocument()
    expect(within(attribution).queryByRole('button', { name: 'Account manager 2' })).not.toBeInTheDocument()
  })

  it('gives the Sede a card of its own, right above the Team', () => {
    grantedAbilities.add('operational-sites.viewAny')
    renderForm()

    expect(within(sectionOf('Operational site')).getByRole('button', { name: 'Operational site' }))
      .toBeInTheDocument()
    expect(sectionOf('Operational site').compareDocumentPosition(sectionOf('Team')))
      .toBe(Node.DOCUMENT_POSITION_FOLLOWING)
  })

  it('keeps the Supervisore for an actor who may not assign the team', () => {
    renderForm()
    const team = sectionOf('Team')

    expect(within(team).getByRole('button', { name: 'Supervisor' })).toBeInTheDocument()
    expect(within(team).queryByRole('button', { name: 'Account manager 2' })).not.toBeInTheDocument()
  })
})
