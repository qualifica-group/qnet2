import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import '@/features/imports/wizard/i18n'
import type { ImportRunDetail, ValueMappingField } from '@/features/imports/wizard/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Spec 0348 AC-017 / AC-018 (FE) / AC-016 (FE): the request-management mapping
 * step offers the category's offer attributes as targets, shows the "Valori"
 * panel (existing status / create new) and blocks the submit until every file
 * value has a match; the lead domain keeps neither the panel nor any request.
 */

vi.mock('@/features/imports/wizard/mapping-template-controls', async () => {
  const actual = await vi.importActual<typeof import('@/features/imports/wizard/mapping-template-controls')>(
    '@/features/imports/wizard/mapping-template-controls',
  )
  return { ...actual, SavedTemplatesMenu: () => null }
})

const fetchValueMappingMock = vi.fn()
vi.mock('@/features/imports/wizard/api', () => ({
  fetchValueMapping: (...args: unknown[]) => fetchValueMappingMock(...args),
}))

const fetchEffectiveAttributesMock = vi.fn()
vi.mock('@/features/product-categories/api', () => ({
  fetchEffectiveAttributes: (...args: unknown[]) => fetchEffectiveAttributesMock(...args),
}))

const PRODUCT_LINE_ID = 26

vi.mock('@/features/imports/wizard/import-config-relation-select', () => ({
  ImportConfigRelationSelect: ({
    resource,
    onChange,
    triggerLabel,
  }: {
    resource: string
    onChange: (next: number | null) => void
    triggerLabel: string
  }) => (
    <button type="button" aria-label={triggerLabel} onClick={() => onChange(resource === 'sources' ? 3 : PRODUCT_LINE_ID)}>
      pick
    </button>
  ),
}))

const STATUS_FIELD: ValueMappingField = {
  field: 'workflow_status',
  column_key: 'Stato',
  allow_create: true,
  options: [
    { id: 5, name: 'Open', group: 'open' },
    { id: 7, name: 'Not reachable', group: 'pending' },
    { id: 9, name: 'In negotiation', group: 'open' },
  ],
  values: [
    { value: 'Nuovo Contatto', count: 3, suggested_id: null },
    { value: '', count: 2, suggested_id: 5 },
    { value: 'NR', count: 1, suggested_id: 7 },
  ],
}

const PRICE_ATTRIBUTE = { id: 1, code: 'price', name: 'Prezzo' } as EffectiveAttribute

function requestRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'request-management',
    status: 'configuring',
    original_filename: 'requests.xlsx',
    total_rows: 6,
    valid_rows: 0,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-11-03T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Nome', name: 'Nome', index: 0, duplicate: false },
      { key: 'Stato', name: 'Stato', index: 1, duplicate: false },
      { key: 'Prezzo', name: 'Prezzo', index: 2, duplicate: false },
    ],
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: { Nome: 'first_name', Stato: 'workflow_status' },
    fields: [
      { id: 'first_name', label: 'imports.request-management.fields.first_name', required: false, group: 'contact', type: 'string' },
      { id: 'workflow_status', label: 'imports.request-management.fields.workflow_status', required: false, group: 'request', type: 'string' },
    ],
    global_fields: [
      {
        id: 'product_category_id',
        label: 'imports.request-management.global.product_category_id',
        required: true,
        for_select_resource: 'product-categories',
        default: null,
        multiple: false,
        depends_on: null,
      },
    ],
    dedup_modes: ['create_new'],
    value_mapping_fields: ['workflow_status', 'source_name'],
    attribute_targets: { context: 'quote', depends_on: 'product_category_id' },
    ...overrides,
  }
}

function renderStep(run: ImportRunDetail, initialConfig: Record<string, number | null> = {}) {
  const onSubmit = vi.fn()
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ImportStepMapping
        run={run}
        initialMapping={run.suggested_mapping ?? {}}
        initialDedupStrategy={null}
        initialConfig={initialConfig}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />
    </QueryClientProvider>,
  )
  return { onSubmit }
}

const SUBMIT = { name: 'Save mapping and continue' }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchValueMappingMock.mockReset()
  fetchEffectiveAttributesMock.mockReset()
  fetchValueMappingMock.mockResolvedValue([STATUS_FIELD])
  fetchEffectiveAttributesMock.mockResolvedValue([PRICE_ATTRIBUTE])
})

describe('request-management mapping step', () => {
  it('AC-017: after choosing the product line the target menu offers the category attributes and suggests a matching column', async () => {
    renderStep(requestRun())

    fireEvent.click(screen.getByRole('button', { name: 'Product line' }))

    await waitFor(() => expect(fetchEffectiveAttributesMock).toHaveBeenCalledWith(PRODUCT_LINE_ID, 'quote'))
    const targets = screen.getAllByRole('combobox', { name: 'Target field' })
    await waitFor(() => expect(targets[2]).toHaveTextContent('Additional information: Prezzo'))

    fireEvent.click(targets[0])
    expect(screen.getByRole('option', { name: 'Additional information: Prezzo' })).toBeInTheDocument()
  })

  it('AC-017: the Values panel blocks the submit until every status is matched, then sends value_mappings', async () => {
    const { onSubmit } = renderStep(requestRun())

    fireEvent.click(screen.getByRole('button', { name: 'Product line' }))

    const panel = await screen.findByRole('region', { name: 'Values' })
    expect(await within(panel).findByRole('combobox', { name: 'Match for “(empty)”' })).toHaveTextContent('Open')
    expect(screen.getByRole('button', SUBMIT)).toBeDisabled()
    expect(screen.getByText(/1 value is still unmatched/i)).toBeInTheDocument()

    fireEvent.click(within(panel).getByRole('combobox', { name: 'Match for “Nuovo Contatto”' }))
    fireEvent.click(screen.getByRole('option', { name: 'Create new status' }))

    const nameInput = screen.getByRole('textbox', { name: 'Name of the new status for “Nuovo Contatto”' })
    expect(nameInput).toHaveValue('Nuovo Contatto')
    fireEvent.change(nameInput, { target: { value: '' } })
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('alert')).toHaveTextContent('Enter the name of the new status.')
    expect(nameInput).toHaveAccessibleDescription('Enter the name of the new status.')
    expect(screen.getByRole('button', SUBMIT)).toBeDisabled()

    fireEvent.change(nameInput, { target: { value: 'open' } })
    expect(screen.getByRole('alert')).toHaveTextContent('A status with this name already exists')
    expect(screen.getByRole('button', SUBMIT)).toBeDisabled()

    fireEvent.change(nameInput, { target: { value: 'New contact' } })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByRole('button', SUBMIT)).toBeEnabled()
    fireEvent.click(screen.getByRole('button', SUBMIT))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][4]).toEqual({
      workflow_status: {
        'Nuovo Contatto': { create: { name: 'New contact', group: 'open' } },
        '': { id: 5 },
        NR: { id: 7 },
      },
    })
  })

  it('a preset product line (backend default) starts the dependent queries without any pick', async () => {
    renderStep(requestRun(), { product_category_id: PRODUCT_LINE_ID })

    await waitFor(() => expect(fetchEffectiveAttributesMock).toHaveBeenCalledWith(PRODUCT_LINE_ID, 'quote'))
    await waitFor(() => expect(fetchValueMappingMock).toHaveBeenCalledTimes(1))
    expect(await screen.findByRole('combobox', { name: 'Match for “Nuovo Contatto”' })).toBeInTheDocument()
  })

  it('AC-016: saved value mappings are pre-applied without overriding a manual choice', async () => {
    const run = requestRun({
      matching_template: {
        id: 4,
        name: 'DIL',
        column_mapping: {},
        dedup_strategy: null,
        value_mappings: { workflow_status: { 'nuovo contatto': { id: 9 }, nr: { id: 999 } } },
      },
    })
    renderStep(run)

    fireEvent.click(screen.getByRole('button', { name: 'Product line' }))

    const panel = await screen.findByRole('region', { name: 'Values' })
    const newContact = await within(panel).findByRole('combobox', { name: 'Match for “Nuovo Contatto”' })
    expect(newContact).toHaveTextContent('In negotiation')
    // An id that is not an option of this workflow is ignored: the suggestion stays.
    expect(within(panel).getByRole('combobox', { name: 'Match for “NR”' })).toHaveTextContent('Not reachable')

    fireEvent.click(newContact)
    fireEvent.click(screen.getByRole('option', { name: 'Open' }))
    expect(within(panel).getByRole('combobox', { name: 'Match for “Nuovo Contatto”' })).toHaveTextContent('Open')
  })

  it('AC-018: the lead domain shows no Values panel and sends no value-mapping or attribute request', async () => {
    const { onSubmit } = renderStep(
      requestRun({
        resource: 'leads',
        value_mapping_fields: [],
        attribute_targets: null,
        suggested_mapping: { Nome: 'first_name' },
      }),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Product line' }))
    fireEvent.click(screen.getByRole('button', SUBMIT))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0]).toHaveLength(3)
    expect(screen.queryByRole('region', { name: 'Values' })).not.toBeInTheDocument()
    expect(fetchValueMappingMock).not.toHaveBeenCalled()
    expect(fetchEffectiveAttributesMock).not.toHaveBeenCalled()
  })
})
