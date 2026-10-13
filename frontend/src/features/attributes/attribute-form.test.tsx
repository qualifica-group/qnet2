import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AttributeForm } from '@/features/attributes/attribute-form'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0017/0021/0240: the attribute create form is a replica of the detail
 * (rows closed until clicked, type editors open) over the same shared
 * `type`-conditional sub-forms (type picker, per-type config, ENUM options /
 * RELATION target editors, presentation). This suite exercises that wiring
 * end to end; the sub-editors' own internal behavior is covered by
 * `custom-field-definition-form.test.tsx` and each component's own test.
 */

const createAttributeMock = vi.fn()

vi.mock('@/features/attributes/api', () => ({
  createAttribute: (...args: unknown[]) => createAttributeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const ENTITIES = [
  { entity_type: 'companies', label: 'customFields.entities.companies' },
  { entity_type: 'products', label: 'customFields.entities.products' },
]
const RELATION_TARGETS = [...ENTITIES, { entity_type: 'provinces', label: 'customFields.entities.provinces' }]

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: ENTITIES, isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: RELATION_TARGETS, isLoading: false, isError: false }),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

// The form reads `/meta/attributes` for its permissions and (spec 0021) the
// dynamic custom-fields schema; this suite has no custom fields to exercise,
// so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** The form renders once `GET /meta/attributes` resolves: wait for the first row. */
async function renderForm(ui: ReactElement) {
  render(ui, { wrapper: wrapper() })
  await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Name' }) })
}

function attribute(
  overrides: Partial<AttributeDetailWithPermissions> = {},
): AttributeDetailWithPermissions {
  return {
    id: 3,
    code: 'color',
    name: 'Color',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    options: [
      { id: 1, value: 'red', label: 'Red', color: null, icon: null, sort_order: 0, is_default: false },
      { id: 2, value: 'blue', label: 'Blue', color: 'blue', icon: null, sort_order: 1, is_default: true },
    ],
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createAttributeMock.mockReset()
})

const openRow = (field: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) }))

function fillIdentity(code: string, name: string) {
  openRow('Code')
  fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: code } })
  openRow('Name')
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: name } })
}

function selectType(name: string) {
  const pencil = screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Type' }) })
  if (pencil) {
    fireEvent.click(pencil)
  }
  fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
  fireEvent.click(screen.getByRole('option', { name }))
}

describe('AttributeForm — type-conditional sub-forms (spec 0017/0021)', () => {
  it('hides the options editor and the relation target editor for the default text type', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('restores a reverted draft row and creates nothing', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    expect(screen.queryByRole('textbox', { name: 'Name' })).not.toBeInTheDocument()
    openRow('Name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Draft' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.revert') }))

    expect(screen.queryByRole('textbox', { name: 'Name' })).not.toBeInTheDocument()
    expect(createAttributeMock).not.toHaveBeenCalled()
  })

  it('reveals the options editor and hides the relation target editor once List of options is selected', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    selectType('List of options')

    expect(screen.getByRole('button', { name: 'Add option' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('reveals the relation target editor and hides the options editor once Relation is selected', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    selectType('Relation')

    expect(screen.getByRole('combobox', { name: 'Target module' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Cardinality' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Picker resource' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
  })

  it('offers provinces as a relation target (spec 0225)', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    selectType('Relation')
    fireEvent.click(screen.getByRole('combobox', { name: 'Target module' }))

    expect(screen.getByRole('option', { name: 'Provinces' })).toBeInTheDocument()
  })

  it('shows only the per-type config fields for the selected type', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    selectType('Decimal number')
    expect(screen.getByText('Minimum')).toBeInTheDocument()
    expect(screen.queryByText('Display as')).not.toBeInTheDocument()

    selectType('Yes/No')
    expect(screen.getByText('Display as')).toBeInTheDocument()
    expect(screen.queryByText('Minimum')).not.toBeInTheDocument()
  })

  it('submits the create payload with options only for an enum attribute', async () => {
    createAttributeMock.mockResolvedValue(attribute())

    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    fillIdentity('color', 'Color')
    selectType('List of options')
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getByLabelText('Value'), { target: { value: 'red' } })
    fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Red' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    const payload = createAttributeMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      code: 'color',
      name: 'Color',
      type: 'enum',
      options: [{ value: 'red', label: 'Red', sort_order: 0, is_default: false }],
    })
    expect(payload.relation_target).toBeUndefined()
    expect(payload.config).toBeUndefined()
  })

  it('projects the per-type config into the create payload', async () => {
    createAttributeMock.mockResolvedValue(attribute({ type: 'decimal' }))

    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    fillIdentity('weight_kg', 'Weight')
    selectType('Decimal number')
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Minimum' }), { target: { value: '0' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Decimal places' }), { target: { value: '2' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    const payload = createAttributeMock.mock.calls[0][0]
    expect(payload.type).toBe('decimal')
    expect(payload.config).toEqual({ min: 0, decimals: 2 })
    expect(payload.options).toBeUndefined()
  })
})

describe('AttributeForm — duplicate mode (row action "duplicate")', () => {
  it('pre-fills every field from the source with suffixed code and name', async () => {
    await renderForm(<AttributeForm
        mode={{ type: 'duplicate', source: attribute() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />)

    expect(screen.getByText('color_copy')).toBeInTheDocument()
    expect(screen.getByText('Color (copy)', { selector: 'span.break-words' })).toBeInTheDocument()
    expect(screen.getAllByLabelText('Value').map((input) => (input as HTMLInputElement).value)).toEqual([
      'red',
      'blue',
    ])
  })

  it('keeps the suffixed code within the backend 64-char limit', async () => {
    await renderForm(<AttributeForm
        mode={{ type: 'duplicate', source: attribute({ code: 'a'.repeat(64) }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />)

    expect(screen.getByText(`${'a'.repeat(59)}_copy`)).toBeInTheDocument()
  })

  it('submits through the create path with the source options', async () => {
    createAttributeMock.mockResolvedValue(attribute({ id: 4, code: 'color_copy' }))
    const onSuccess = vi.fn()

    await renderForm(<AttributeForm
        mode={{ type: 'duplicate', source: attribute() }}
        onSuccess={onSuccess}
        onCancel={vi.fn()}
      />)

    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    expect(createAttributeMock.mock.calls[0][0]).toMatchObject({
      code: 'color_copy',
      name: 'Color (copy)',
      type: 'enum',
      options: [
        { value: 'red', label: 'Red', is_default: false },
        { value: 'blue', label: 'Blue', color: 'blue', is_default: true },
      ],
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1))
  })
})
