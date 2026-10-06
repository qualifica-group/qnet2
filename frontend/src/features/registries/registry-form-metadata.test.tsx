import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { RegistryForm } from '@/features/registries/registry-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import {
  EDITABLE,
  pencilOf,
  permissionsFor,
  registryWrapper,
} from '@/features/registries/registry-test-fixtures'

/**
 * The anagrafica create form (spec 0200 D-5): a replica of the detail whose
 * rows start CLOSED and open one at a time ("Done"/"Revert"), driven by the
 * create-context metadata (spec 0004: hidden field absent, required field
 * marked). The edit-mode cases of this suite moved to
 * `registry-detail-inline-edit.test.tsx` — REQUIREMENT CHANGED: there is no
 * edit form any more.
 */

const createRegistryMock = vi.fn()
vi.mock('@/features/registries/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/registries/api')>()),
  createRegistry: (...args: unknown[]) => createRegistryMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [],
  agreement_status: [],
  size_class: [],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

vi.mock('@/components/ui/async-paginated-select', () => ({ AsyncPaginatedSelect: () => null }))
vi.mock('@/components/ui/async-paginated-multi-select', () => ({ AsyncPaginatedMultiSelect: () => null }))

/** The `<label>` element whose text starts with `text` (exact-match helper). */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

function sectionNamed(title: string): HTMLElement {
  return screen.getByText(title).closest('section') as HTMLElement
}

function renderCreate() {
  render(<RegistryForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: registryWrapper().wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('RegistryForm — metadata-driven create rows (spec 0004, spec 0200)', () => {
  it('hides a hidden field and marks a required field once its row opens', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({
        source_id: { ...EDITABLE, visible: false, hidden: true, editable: false },
        vat_group: { ...EDITABLE, required: true },
      }),
    })
    renderCreate()

    await waitFor(() => expect(pencilOf('VAT group')).toBeInTheDocument())
    expect(screen.queryByText('Source')).not.toBeInTheDocument()

    fireEvent.click(pencilOf('VAT group'))
    expect(labelFor('VAT group').textContent).toContain('*')
  })

  it('starts every row closed and keeps a value on Done', async () => {
    renderCreate()

    await waitFor(() => expect(pencilOf('Employee count')).toBeInTheDocument())
    expect(screen.queryByLabelText(/^Employee count/)).not.toBeInTheDocument()

    fireEvent.click(pencilOf('Employee count'))
    fireEvent.change(screen.getByLabelText(/^Employee count/), { target: { value: '42' } })
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    expect(screen.queryByLabelText(/^Employee count/)).not.toBeInTheDocument()
    const section = sectionNamed('Business details')
    expect(within(section).getByText('42')).toBeInTheDocument()
  })

  it('puts the value back on Revert', async () => {
    renderCreate()

    await waitFor(() => expect(pencilOf('VAT group')).toBeInTheDocument())
    fireEvent.click(pencilOf('VAT group'))
    fireEvent.change(screen.getByLabelText(/^VAT group/), { target: { value: 'G-1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Revert' }))

    expect(screen.queryByText('G-1')).not.toBeInTheDocument()
  })

  it('keeps the anagraphic card open: it names the anagrafica being created', async () => {
    renderCreate()

    fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })

    expect(screen.getByRole('heading', { name: 'Ada Lovelace' })).toBeInTheDocument()
  })

  it('reveals "Qualified supplier" only once the draft is a supplier', async () => {
    renderCreate()

    await waitFor(() => expect(pencilOf('Supplier')).toBeInTheDocument())
    expect(screen.queryByText('Qualified supplier')).not.toBeInTheDocument()

    fireEvent.click(pencilOf('Supplier'))
    fireEvent.click(screen.getByLabelText('Supplier'))
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    expect(pencilOf('Qualified supplier')).toBeInTheDocument()
  })

  it('refuses the save without a POST while the card is incomplete', async () => {
    renderCreate()

    await waitFor(() => expect(screen.getAllByRole('button', { name: 'Save' }).length).toBeGreaterThan(0))
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    expect((await screen.findAllByRole('alert')).length).toBeGreaterThan(0)
    expect(createRegistryMock).not.toHaveBeenCalled()
  })
})

/**
 * User directive 2026-09-11: the Supervisore and the G.A. slots live in their
 * OWN "Team" block, like the Opportunità and Offerta records. Commerciale and
 * Segnalatore stay in "Relations": they are referenti, not users.
 */
describe('RegistryForm — Team and Relations blocks (user directive 2026-09-11)', () => {
  it('gathers the supervisor and the G.A. slots in the Team block', async () => {
    renderCreate()

    await waitFor(() => expect(screen.getByText('Team')).toBeInTheDocument())
    const team = sectionNamed('Team')
    expect(within(team).getByText('Supervisor')).toBeInTheDocument()
    expect(within(team).getByText('Account managers')).toBeInTheDocument()
  })

  it('leaves the referent-backed roles in Relations, where they belong', async () => {
    renderCreate()

    await waitFor(() => expect(screen.getByText('Relations')).toBeInTheDocument())
    const relations = sectionNamed('Relations')
    expect(within(relations).getByText('Commercial')).toBeInTheDocument()
    expect(within(relations).getByText('Reporter')).toBeInTheDocument()
    expect(within(relations).queryByText('Supervisor')).not.toBeInTheDocument()
  })
})
