import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'

/**
 * Sede <-> Operatore reciprocal filtering/linking (spec 0048 AC-060..063).
 * Split out of `lead-form-body.test.tsx` for size (engineering.md §6),
 * mirrors `lead-form-body-site.test.tsx`'s mocking approach.
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/leads/api')>('@/features/leads/api')
  return {
    ...actual,
    createLead: (...args: unknown[]) => createLeadMock(...args),
    updateLead: (...args: unknown[]) => updateLeadMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/** An Operatore with an employment Sede (meta), driving the AC-061 auto-fill. */
const OPERATOR_WITH_SITE: UserForSelectItem = {
  id: 5,
  label: 'Ada Lovelace',
  meta: { operational_site_id: 66, operational_site_label: 'Depot Z' },
}

/** An Operatore with no employment Sede — must leave the Sede field untouched. */
const OPERATOR_NO_SITE: UserForSelectItem = {
  id: 6,
  label: 'Bob Noyce',
  meta: undefined,
}

const SITE_A: ForSelectItem = { id: 77, label: 'Warehouse A' }
const SITE_B: ForSelectItem = { id: 88, label: 'Warehouse B' }

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `lead-form-body.test.tsx`). The Site button cycles between
 * `SITE_A`/`SITE_B` on each click (starts at `SITE_A`) so a test can drive a
 * second, DIFFERENT pick; the Operator button always picks `OPERATOR_NO_SITE`
 * unless `data-pick-with-site` is set, in which case it picks
 * `OPERATOR_WITH_SITE`. Also exposes the `params` this render received via
 * `data-params`, so a test can assert the Sede filter reaches the Operator
 * picker (AC-060) without a real dropdown.
 */
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
        if (labels.triggerLabel === 'Site') {
          const next = value === SITE_A.id ? SITE_B : SITE_A
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        if (labels.triggerLabel === 'Operator') {
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

/** Stubs the "Prodotti di interesse" picker: not exercised by this suite. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    labels,
    disabled,
  }: {
    value: number[]
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <div data-testid={`multi-${labels.triggerLabel}`} data-disabled={disabled ? 'true' : 'false'}>
      {value.join(',')}
    </div>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('LeadFormBody — Sede filters the Operatore picker (AC-060)', () => {
  it('leaves the Operator unfiltered while no Sede is chosen', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Operator')).toBeInTheDocument())
    expect(screen.getByTestId('select-Operator')).toHaveAttribute('data-params', '')
  })

  it('scopes the Operator picker to the chosen Sede once one is picked', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Site'))

    await waitFor(() =>
      expect(screen.getByTestId('select-Operator')).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: SITE_A.id }),
      ),
    )
  })

  it('shows the "filtered by Site" hint only once a Sede is chosen', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    expect(
      screen.queryByText('The operators shown are limited to those of the chosen Site.'),
    ).not.toBeInTheDocument()

    fireEvent.click(screen.getByTestId('select-Site'))

    await waitFor(() =>
      expect(
        screen.getByText('The operators shown are limited to those of the chosen Site.'),
      ).toBeInTheDocument(),
    )
  })
})

describe('LeadFormBody — Operatore auto-fills the Sede (AC-061)', () => {
  it('instantly hydrates the Sede trigger from the picked Operator meta', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Operator')).toBeInTheDocument())
    expect(screen.getByTestId('select-Site')).toHaveTextContent('')

    fireEvent.click(screen.getByTestId('select-Operator'))

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent('66'))
    expect(screen.getByTestId('select-Site')).not.toBeDisabled()
  })

  it('leaves the Sede untouched when the picked Operator has no employment Sede', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Operator')).toBeInTheDocument())
    // The mock's Operator button toggles between the two fixtures; the first
    // click always lands on `OPERATOR_WITH_SITE` (see the mock above), so
    // click a second time to land on `OPERATOR_NO_SITE`.
    fireEvent.click(screen.getByTestId('select-Operator'))
    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent('66'))

    fireEvent.click(screen.getByTestId('select-Operator'))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('66')
  })
})

describe('LeadFormBody — a Sede already chosen is never overwritten (AC-026/AC-027, spec 0103 D-13)', () => {
  it('AC-026: leaves an already-chosen Sede untouched when the picked Operator has a different physical Sede', async () => {
    render(
      <LeadForm
        mode={{
          type: 'edit',
          lead: lead({
            operational_site_id: SITE_A.id,
            operational_site: { id: SITE_A.id, label: SITE_A.label },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id)))

    // OPERATOR_WITH_SITE's own employment Sede (66) differs from the Sede
    // already on the form (77): with remote memberships (spec 0103) that
    // operator can legitimately show up in this Sede's filtered list, so the
    // Sede must NOT flip to the operator's physical one.
    fireEvent.click(screen.getByTestId('select-Operator'))

    await waitFor(() =>
      expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_WITH_SITE.id)),
    )
    expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id))
  })

  it('AC-027: still auto-fills an empty Sede from the picked Operator', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Operator')).toBeInTheDocument())
    expect(screen.getByTestId('select-Site')).toHaveTextContent('')

    fireEvent.click(screen.getByTestId('select-Operator'))

    await waitFor(() =>
      expect(screen.getByTestId('select-Site')).toHaveTextContent(
        String(OPERATOR_WITH_SITE.meta?.operational_site_id),
      ),
    )
  })
})

describe('LeadFormBody — changing the Sede clears the Operatore (AC-062)', () => {
  it('clears a previously picked Operator once the Sede changes to a different value', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Operator')).toBeInTheDocument())
    // The mock toggles: first click hits `OPERATOR_WITH_SITE`, auto-filling
    // the Sede to 66 (AC-061); second click lands on `OPERATOR_NO_SITE`,
    // which leaves that auto-filled Sede in place (an operator with no Sede
    // of its own never resets a Sede already picked).
    fireEvent.click(screen.getByTestId('select-Operator'))
    fireEvent.click(screen.getByTestId('select-Operator'))
    await waitFor(() =>
      expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id)),
    )
    expect(screen.getByTestId('select-Site')).toHaveTextContent('66')

    // The Site mock toggles off the current value (66) onto `SITE_A` (77) — a
    // real change from the Sede the Operator was linked against.
    fireEvent.click(screen.getByTestId('select-Site'))

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id)))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent('')
  })

  it("a different field's change never clears the Operator (only the Sede field's own pick does)", async () => {
    render(
      <LeadForm
        mode={{
          type: 'edit',
          lead: lead({
            operational_site_id: SITE_A.id,
            operational_site: { id: SITE_A.id, label: SITE_A.label },
            operator_id: OPERATOR_NO_SITE.id,
            operator: { id: OPERATOR_NO_SITE.id, name: OPERATOR_NO_SITE.label },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id)))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id))

    // A different single-select field never triggers the Sede's own onItemChange.
    fireEvent.click(screen.getByTestId('select-Source'))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id))
  })
})

describe('LeadFormBody — edit prefill stays coherent (AC-063)', () => {
  it('precompiles both Sede and Operatore from the loaded lead and keeps them linked', async () => {
    render(
      <LeadForm
        mode={{
          type: 'edit',
          lead: lead({
            operational_site_id: SITE_A.id,
            operational_site: { id: SITE_A.id, label: SITE_A.label },
            operator_id: OPERATOR_NO_SITE.id,
            operator: { id: OPERATOR_NO_SITE.id, name: OPERATOR_NO_SITE.label },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id)))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id))
    expect(screen.getByTestId('select-Operator')).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_A.id }),
    )

    // Changing the Sede away from the prefilled one still clears the stale Operator.
    fireEvent.click(screen.getByTestId('select-Site'))
    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_B.id)))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent('')
  })
})
