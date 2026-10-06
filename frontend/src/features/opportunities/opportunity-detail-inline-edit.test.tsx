import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { toast } from 'sonner'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import axios from 'axios'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { updateOpportunity } from '@/features/opportunities/api'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import type { FieldPermission } from '@/features/authorization/types'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Spec 0198: no edit page, a pencil per editable row; Save PATCHes that field
 * alone (plus its cascade), Cancel restores it without any request; BR-2
 * locks and cascades onto read-only fields leave no pencil.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>('@/features/opportunities/api')
  return { ...actual, updateOpportunity: vi.fn() }
})

/** The anagrafica a pick hands down nothing: the role inheritance is covered by the create form's own suite. */
vi.mock('@/features/opportunities/opportunity-relation-meta', () => ({
  fetchOpportunityRegistryMeta: () => Promise.resolve(null),
}))

/** Every single relation picker, as a button per id (mirrors `opportunity-form-body.test.tsx`). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    onChange,
    labels,
  }: {
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" onClick={() => onChange(20)}>
      {`select ${labels.triggerLabel} 20`}
    </button>
  ),
}))

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const pencilName = (field: string) => label('common.inlineEdit.edit', { field })
const queryPencil = (field: string) => screen.queryByRole('button', { name: pencilName(field) })

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

function opportunity(overrides: Partial<OpportunityDetailWithPermissions> = {}): OpportunityDetailWithPermissions {
  return {
    id: 7,
    name: 'Enterprise deal',
    registry_id: 10,
    registry: { id: 10, name: 'Acme S.p.A.' },
    status: { source: 'default', distinct_count: 0, entries: [] },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: 100,
    source: { id: 100, name: 'Web' },
    operational_site_id: null,
    operational_site: null,
    product_lines: [
      {
        id: 500,
        business_function: { id: 50, name: 'Sales' },
        product_category: { id: 110, name: 'Consulting' },
      },
    ],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: 40,
    general_notes: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

function renderDetail(record: OpportunityDetailWithPermissions, onChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <OpportunityDetailView opportunity={record} onChanged={onChanged} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return { onChanged }
}

function withFieldPermissions(fields: Record<string, FieldPermission>): Partial<OpportunityDetailWithPermissions> {
  return {
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields,
      actions: {},
    },
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateOpportunity).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('OpportunityDetailView — in-place editing (spec 0198)', () => {
  it('saves the edited field alone and reports the change', async () => {
    const record = opportunity()
    vi.mocked(updateOpportunity).mockResolvedValueOnce({ ...record, name: 'New title' })
    const { onChanged } = renderDetail(record)

    fireEvent.click(queryPencil(label('opportunities.form.name'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('opportunities.form.name') }), {
      target: { value: 'New title' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() => expect(updateOpportunity).toHaveBeenCalledWith(7, { name: 'New title' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalledOnce())
    expect(screen.queryByRole('button', { name: label('common.inlineEdit.save') })).not.toBeInTheDocument()
  })

  it('restores the persisted value on cancel, without any request', () => {
    renderDetail(opportunity())

    fireEvent.click(queryPencil(label('opportunities.form.name'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('opportunities.form.name') }), {
      target: { value: 'Discarded' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))

    expect(updateOpportunity).not.toHaveBeenCalled()
    expect(screen.queryByRole('textbox', { name: label('opportunities.form.name') })).not.toBeInTheDocument()
    expect(screen.getAllByText('Enterprise deal').length).toBeGreaterThan(0)
  })

  it('keeps the editor open with the server message on a refused save', async () => {
    vi.mocked(updateOpportunity).mockRejectedValueOnce(
      new axios.AxiosError('422', '422', undefined, undefined, {
        status: 422,
        data: { errors: { name: ['Invalid title.'] } },
      } as never),
    )
    renderDetail(opportunity())

    fireEvent.click(queryPencil(label('opportunities.form.name'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('opportunities.form.name') }), {
      target: { value: 'X' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    expect(await screen.findByText('Invalid title.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: label('opportunities.form.name') })).toBeInTheDocument()
  })

  it('says why a save is refused by a rule on a field the open editor does not show', async () => {
    // A historical record with no classification row: the rule is the rows', the editor the title's.
    renderDetail(opportunity({ product_lines: [] }))

    fireEvent.click(queryPencil(label('opportunities.form.name'))!)
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('productLines.required')))
    expect(updateOpportunity).not.toHaveBeenCalled()
  })

  it('offers no edit on the fields a linked Lead derived (BR-2)', () => {
    renderDetail(
      opportunity({
        lead_id: 5,
        lead: { id: 5, label: 'Lead Rossi' },
        locked_fields: ['registry_id', 'source_id'],
      }),
    )

    expect(queryPencil(label('opportunities.form.registry'))).not.toBeInTheDocument()
    expect(queryPencil(label('opportunities.form.source'))).not.toBeInTheDocument()
    expect(queryPencil(label('opportunities.form.referent'))).toBeInTheDocument()
    // The origin itself is immutable: a read-only row.
    expect(screen.getByText('Lead Rossi')).toBeInTheDocument()
  })

  it('opens no editor whose cascade would write a read-only field', () => {
    renderDetail(opportunity(withFieldPermissions({ referent_id: READ_ONLY_FIELD, products_of_interest: READ_ONLY_FIELD })))

    // The anagrafica clears the referent; the classification rows prune the products of interest.
    expect(queryPencil(label('opportunities.form.registry'))).not.toBeInTheDocument()
    expect(queryPencil(label('opportunities.form.sections.productLines.title'))).not.toBeInTheDocument()
    expect(queryPencil(label('opportunities.form.commercial'))).toBeInTheDocument()
  })

  it('shows the open-opportunity refusal inside the anagrafica editor, with its link', async () => {
    vi.mocked(updateOpportunity).mockRejectedValueOnce(
      new axios.AxiosError('422', '422', undefined, undefined, {
        status: 422,
        data: {
          errors: {
            registry_id: ['This registry already has an open opportunity.'],
            existing_opportunity_id: ['3'],
          },
        },
      } as never),
    )
    renderDetail(opportunity())

    fireEvent.click(queryPencil(label('opportunities.form.registry'))!)
    fireEvent.click(screen.getByRole('button', { name: `select ${label('opportunities.form.registry')} 20` }))
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() => expect(updateOpportunity).toHaveBeenCalledWith(7, expect.objectContaining({ registry_id: 20 })))
    expect(await screen.findByText('This registry already has an open opportunity.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: label('opportunities.form.goToExistingOpportunity') })).toHaveAttribute(
      'href',
      '/opportunities/3',
    )
  })
})
