import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  EMPTY_PAGE,
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  applyRow,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * The anagrafica pick (split from `opportunity-form-body.test.tsx` for size,
 * engineering.md §6): AC-072/AC-093 referent scoping, the roles and G.A. slots
 * inherited from the anagrafica (directive 2026-07-29, AC-095), and the
 * confirmation that guards the values the user entered BEFORE picking it —
 * the team section sits above the client section (user report 2026-09-28:
 * the team typed first was wiped and never saved).
 *
 * Spec 0198: every field is a closed row opened through its pencil; what the
 * pick inherits shows by name on the closed rows, the referent has no pencil
 * until an anagrafica is chosen.
 */

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

// Closed rows link their related records (`RecordLink`): the open-mode
// preference and the abilities are stubbed rather than dragging an AuthProvider
// in; every ability is granted, so the links render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const TEST_REGISTRY_WITH_DEFAULTS = 10
const TEST_REGISTRY_WITHOUT_DEFAULTS = 20

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

/**
 * Controls the one-shot `meta` fetch behind the registry prefill (BR-4/A-5)
 * and the closed rows' label resolution: both go through the generic
 * `fetchForSelect`, mocked here per resource+id.
 */
const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  SELECT_IDS.Registry = [TEST_REGISTRY_WITH_DEFAULTS, TEST_REGISTRY_WITHOUT_DEFAULTS]
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITH_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITH_DEFAULTS,
            label: 'Acme S.p.A.',
            meta: {
              commercial: { id: 71, name: 'Sara Conti' },
              reporter: { id: 81, name: 'Elio Fabbri' },
              supervisor: { id: 61, name: 'Ivo Bianchi' },
              // A-5: account managers inherited into manager_slots (gap-aware by position).
              managers: [
                { id: 91, name: 'Gina Manager', position: 1 },
                { id: 93, name: 'Turi Manager', position: 3 },
              ],
            },
          },
        ],
      }
    }
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITHOUT_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITHOUT_DEFAULTS,
            label: 'Beta Srl',
            meta: { commercial: null, reporter: null, supervisor: null, managers: [] },
          },
        ],
      }
    }
    return resolveForSelectLabels(resource, params)
  })
})

async function renderCreateForm() {
  render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
  await waitFor(() => expect(queryPencil(ROW.registry)).toBeInTheDocument())
}

/** Opens the anagrafica row and picks one; the row stays open (the confirmation, when asked, belongs to it). */
function pickRegistry(registryId: number) {
  openRow(ROW.registry)
  fireEvent.click(screen.getByRole('button', { name: `select Registry ${registryId}` }))
}

/** Picks a fixed user/contact (mock id 1) into a closed row and keeps it with "Done". */
function pickIntoRow(row: string, triggerLabel: string) {
  openRow(row)
  fireEvent.click(screen.getByRole('button', { name: `select ${triggerLabel} 1` }))
  applyRow()
}

describe('OpportunityFormBody — referent scoping + free commercial/reporter (AC-093)', () => {
  // REQUIREMENT CHANGED (spec 0198): "disabled" is now "no pencil" — the
  // referent row opens only once an anagrafica is chosen.
  it('gives the referent no pencil until a registry is chosen; commercial/reporter stay editable', async () => {
    await renderCreateForm()

    // A-3: only referent is anagrafica-scoped; commercial/reporter are free.
    expect(queryPencil(ROW.referent)).not.toBeInTheDocument()
    expect(queryPencil(ROW.commercial)).toBeInTheDocument()
    expect(queryPencil(ROW.reporter)).toBeInTheDocument()
  })

  // Requirement changed by directive 2026-07-29 (supersedes 2026-07-17): the
  // three roles are ALWAYS inherited from the anagrafica. The pickers stay
  // unscoped (A-3), only their initial value is now derived.
  it('scopes ONLY the referent by registry_id; commercial/reporter/supervisor receive no params but ARE inherited from the anagrafica', async () => {
    await renderCreateForm()

    pickRegistry(TEST_REGISTRY_WITH_DEFAULTS)
    applyRow()

    await waitFor(() => expect(queryPencil(ROW.referent)).toBeInTheDocument())
    openRow(ROW.referent)
    expect(screen.getByTestId('params-Contact')).toHaveTextContent(
      JSON.stringify({ registry_id: TEST_REGISTRY_WITH_DEFAULTS }),
    )
    // The referent is reset, not inherited: it stays anagrafica-scoped (BR-4).
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    applyRow()

    // A-3: commercial/reporter are the whole platform list — no registry_id param.
    await waitFor(() => expect(screen.getByRole('link', { name: /Sara Conti/ })).toBeInTheDocument())
    openRow(ROW.commercial)
    expect(screen.getByTestId('params-Sales rep')).toHaveTextContent('null')
    expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('71')
    applyRow()
    openRow(ROW.reporter)
    expect(screen.getByTestId('params-Reporter')).toHaveTextContent('null')
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('81')
    applyRow()
    openRow(ROW.supervisor)
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61')
  })

  // Requirement changed by user decision 2026-09-28: values the user entered
  // are replaced only after confirming.
  it('re-inherits the three roles on every registry change once the user confirms replacing their own', async () => {
    await renderCreateForm()

    pickRegistry(TEST_REGISTRY_WITH_DEFAULTS)
    applyRow()

    // The user manually picks a referent (scoped) and overrides the inherited roles.
    await waitFor(() => expect(queryPencil(ROW.referent)).toBeInTheDocument())
    pickIntoRow(ROW.referent, 'Contact')
    pickIntoRow(ROW.commercial, 'Sales rep')
    pickIntoRow(ROW.reporter, 'Reporter')
    await waitFor(() => expect(screen.getAllByRole('link', { name: /Contact One/ })).toHaveLength(3))

    // Changing the anagrafica resets the scoped referent AND, once confirmed,
    // re-derives the three roles — the new one has none, so they end up empty.
    pickRegistry(TEST_REGISTRY_WITHOUT_DEFAULTS)
    fireEvent.click(await screen.findByRole('button', { name: 'Replace' }))
    applyRow()

    await waitFor(() => expect(screen.queryByRole('link', { name: /Contact One/ })).not.toBeInTheDocument())
    expect(screen.queryByRole('link', { name: /Sara Conti|Elio Fabbri/ })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Beta Srl/ })).toBeInTheDocument()
    openRow(ROW.supervisor)
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('inherits the account managers of the chosen anagrafica into gap-aware slots, then clears them for one without (AC-095)', async () => {
    await renderCreateForm()

    pickRegistry(TEST_REGISTRY_WITH_DEFAULTS)
    applyRow()

    // Positions 1 and 3 -> slots 1 and 3 filled, slot 2 an empty gap.
    expect(await screen.findByText('Gina Manager')).toBeInTheDocument()
    expect(screen.getByText('Turi Manager')).toBeInTheDocument()
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('91')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('93')
    applyRow()

    pickRegistry(TEST_REGISTRY_WITHOUT_DEFAULTS)
    applyRow()

    // The values came from the previous anagrafica, not from the user: replaced
    // without asking. Requirement changed 2026-09-28: the four G.A. cards stay,
    // empty, like on a blank create form.
    await waitFor(() => expect(screen.queryByText('Gina Manager')).not.toBeInTheDocument())
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 4')).toHaveTextContent('')
  })
})

describe('OpportunityFormBody — team entered before the anagrafica (user report 2026-09-28)', () => {
  async function enterTeamThenPickRegistry() {
    await renderCreateForm()

    pickIntoRow(ROW.supervisor, 'Supervisor')
    openRow(ROW.managers)
    fireEvent.click(screen.getByRole('button', { name: 'select Account manager 2 1' }))
    await waitFor(() => expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('1'))
    applyRow()

    pickRegistry(TEST_REGISTRY_WITH_DEFAULTS)
  }

  it('keeps the team the user entered when they choose to, still inheriting the empty roles', async () => {
    await enterTeamThenPickRegistry()

    fireEvent.click(await screen.findByRole('button', { name: 'Keep mine' }))
    applyRow()

    expect(await screen.findByRole('link', { name: /Sara Conti/ })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Elio Fabbri/ })).toBeInTheDocument()
    openRow(ROW.supervisor)
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('1')
    applyRow()
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('1')
  })

  it("replaces the team with the anagrafica's when the user confirms", async () => {
    await enterTeamThenPickRegistry()

    fireEvent.click(await screen.findByRole('button', { name: 'Replace' }))
    applyRow()

    openRow(ROW.supervisor)
    await waitFor(() => expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61'))
    applyRow()
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('91')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('93')
  })
})
