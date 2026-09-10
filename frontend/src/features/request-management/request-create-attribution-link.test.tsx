import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'

/**
 * The create form's Sede <-> Operatore link and its section order (user
 * directive 2026-07-31): both must match the work panel's, so a request is
 * created with the same fields, in the same sequence, the operator later
 * works it in. Mirrors `request-attribution-operator-link.test.tsx` — the
 * same `AsyncPaginatedSelect` stub keyed on the accessible trigger label, so
 * a pick can be driven without a real dropdown and the `params` each render
 * received are exposed for assertion.
 *
 * AC-003 (spec 0097): the link hangs off the TEAM editor's operator slot
 * (`ManagerSlotsField`, position 2) instead of the retired single "Operatore"
 * picker. With no active category tab the slot keeps the editor's own default
 * denomination.
 *
 * AC-011 (spec 0097 rev-2): those two ends are now in two DIFFERENT sections
 * — the Sede in "Attribuzione", the slot in "Team" — and the link is cabled by
 * the form that owns them both (`useRequestSiteOperatorLink`). Every rule
 * below is unchanged, which is the whole point of asserting them across the
 * real form rather than one section.
 */

vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
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

/** An operator carrying its own employment Sede in `meta` — drives the auto-fill. */
const OPERATOR_WITH_SITE: UserForSelectItem = {
  id: 5,
  label: 'Ada Lovelace',
  meta: { operational_site_id: 66, operational_site_label: 'Depot Z' },
}

/** An operator with no employment Sede — must leave the Sede untouched. */
const OPERATOR_NO_SITE: UserForSelectItem = { id: 6, label: 'Bob Noyce', meta: undefined }

const SITE_A: ForSelectItem = { id: 77, label: 'Warehouse A' }
const SITE_B: ForSelectItem = { id: 88, label: 'Warehouse B' }

/** Default denomination of the operator slot (position 2), with no category label resolved. */
const OPERATOR_SLOT_LABEL = 'Account manager 2'

/** A neighbouring slot, to assert the Sede rules never spill onto the rest of the team. */
const OTHER_SLOT_LABEL = 'Account manager 1'

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    params,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
    params?: Record<string, string | number>
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-params={params ? JSON.stringify(params) : ''}
      onClick={() => {
        if (labels.triggerLabel === 'Operational site') {
          const next = value === SITE_A.id ? SITE_B : SITE_A
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        if (labels.triggerLabel === OPERATOR_SLOT_LABEL) {
          const next = value === OPERATOR_WITH_SITE.id ? OPERATOR_NO_SITE : OPERATOR_WITH_SITE
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        onChange(3)
        onItemChange?.(null)
      }}
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

const siteField = () => screen.getByTestId('select-Operational site')
const operatorField = () => screen.getByTestId(`select-${OPERATOR_SLOT_LABEL}`)
const otherSlotField = () => screen.getByTestId(`select-${OTHER_SLOT_LABEL}`)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('Create form — section order', () => {
  /**
   * Requirement changed (user directive 2026-09-10): the Team and the Sede
   * left the filling flow for the side column, which the panel grid renders
   * FIRST in the DOM (and reorders to the right on two columns), and the
   * client details now come before the attribution.
   */
  it('renders sede and team in the side column, then product lines, client details, attribution', () => {
    renderForm()

    const sectionTitles = ['Operational site', 'Team', 'Product lines', 'Client details', 'Attribution']
    const titles = screen
      .getAllByRole('heading')
      .map((heading) => heading.textContent)
      .filter((title) => title !== null && sectionTitles.includes(title))

    expect(titles).toEqual(sectionTitles)
  })

  /**
   * AC-011: the two ends of the link are two ADJACENT cards of the side column
   * since the user directive 2026-09-10, the Sede first — it is what scopes
   * the slot below it.
   */
  it('renders the Sede before the Operatore it scopes', () => {
    renderForm()

    expect(siteField().compareDocumentPosition(operatorField())).toBe(Node.DOCUMENT_POSITION_FOLLOWING)
  })
})

describe('Create form — the Sede scopes the Operatore picker', () => {
  it('sends no filter while no Sede is chosen', () => {
    renderForm()

    expect(operatorField()).toHaveAttribute('data-params', '')
    expect(screen.queryByText('Only the operators of the selected site.')).not.toBeInTheDocument()
  })

  /** AC-003: only the operator slot is bound to the Sede — the rest of the team is not. */
  it('leaves every other slot unfiltered once a Sede is picked', async () => {
    renderForm()
    fireEvent.click(siteField())

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: SITE_A.id })),
    )
    expect(otherSlotField()).toHaveAttribute('data-params', '')
  })

  it('re-scopes the picker as soon as a Sede is picked', async () => {
    renderForm()
    fireEvent.click(siteField())

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: SITE_A.id })),
    )
    expect(screen.getByText('Only the operators of the selected site.')).toBeInTheDocument()
  })
})

describe('Create form — Operatore auto-fills the Sede', () => {
  it('hydrates the Sede from the picked operator meta, with no extra fetch', async () => {
    renderForm()
    fireEvent.click(operatorField())

    await waitFor(() => expect(siteField()).toHaveTextContent(String(OPERATOR_WITH_SITE.meta?.operational_site_id)))
  })

  it('does NOT clear the operator it just auto-filled the Sede from', async () => {
    renderForm()
    fireEvent.click(operatorField())

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_WITH_SITE.id)))
  })

  /** AC-023: a Sede already picked on the create form is never overwritten by the operator picker. */
  it("does not overwrite a freshly picked Sede with the operator's own Sede", async () => {
    renderForm()
    fireEvent.click(siteField())
    await waitFor(() => expect(siteField()).toHaveTextContent(String(SITE_A.id)))

    fireEvent.click(operatorField())

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_WITH_SITE.id)))
    expect(siteField()).toHaveTextContent(String(SITE_A.id))
  })

  it('leaves the Sede untouched for an operator with no Sede', async () => {
    renderForm()
    // The stub picks OPERATOR_WITH_SITE first, then OPERATOR_NO_SITE.
    fireEvent.click(operatorField())
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))
    fireEvent.click(operatorField())

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_NO_SITE.id)))
    expect(siteField()).toHaveTextContent('66')
  })
})

describe('Create form — changing the Sede clears the operator slot', () => {
  it('clears an operator belonging to the previous Sede, keeping the other slots', async () => {
    renderForm()
    fireEvent.click(otherSlotField()) // a G.A. on another slot, untouched by the Sede rule
    fireEvent.click(operatorField()) // operator 5, Sede 66
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))

    fireEvent.click(siteField()) // a REAL Sede pick, different from 66

    // The stub renders `value ?? ''`, so an emptied slot has no text node at
    // all — asserted positively, since `toHaveTextContent('')` passes on any
    // content.
    await waitFor(() => expect(operatorField().textContent).toBe(''))
    expect(otherSlotField().textContent).not.toBe('')
  })
})
