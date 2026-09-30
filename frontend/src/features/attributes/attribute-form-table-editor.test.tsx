import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AttributeForm } from '@/features/attributes/attribute-form'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0180 AC-021: the `table` columns editor inside the attribute form. */

const createAttributeMock = vi.fn()
const updateAttributeMock = vi.fn()

vi.mock('@/features/attributes/api', () => ({
  createAttribute: (...args: unknown[]) => createAttributeMock(...args),
  updateAttribute: (...args: unknown[]) => updateAttributeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const ENTITIES = [
  { entity_type: 'companies', label: 'customFields.entities.companies' },
  { entity_type: 'products', label: 'customFields.entities.products' },
]

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: ENTITIES, isLoading: false, isError: false }),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/attributes/use-attribute-form-meta', () => ({
  useAttributeFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useAttributeForm` reads `/meta/attributes` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise, so it
// resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}


beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createAttributeMock.mockReset()
  updateAttributeMock.mockReset()
  // The live preview renders the table control, which reads the viewport.
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })) as unknown as typeof window.matchMedia
})

function pick(combobox: HTMLElement, option: string) {
  fireEvent.click(combobox)
  fireEvent.click(screen.getByRole('option', { name: option }))
}

function renderTableForm() {
  render(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
  fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'inspections' } })
  fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Inspections' } })
  pick(screen.getByRole('combobox', { name: 'Type' }), 'Table')
}

function addColumn(label: string) {
  fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
  const labels = screen.getAllByLabelText('Column label')
  fireEvent.change(labels[labels.length - 1], { target: { value: label } })
}

describe('AttributeForm — table columns editor (spec 0180 AC-021)', () => {
  it('submits the TableFieldConfig payload', async () => {
    createAttributeMock.mockResolvedValue({})
    renderTableForm()
    addColumn('Inspector')
    addColumn('On site')
    pick(screen.getAllByRole('combobox', { name: 'Column type' })[1], 'Yes/No')
    fireEvent.click(screen.getAllByRole('checkbox', { name: 'Required' })[0])
    pick(screen.getByRole('combobox', { name: 'Summary column' }), 'Inspector')
    pick(screen.getByRole('combobox', { name: 'Strategy' }), 'Minimum')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    const payload = createAttributeMock.mock.calls[0][0]
    expect(payload.type).toBe('table')
    expect(payload.options).toBeUndefined()
    expect(payload.config).toEqual({
      columns: [
        { key: 'inspector', label: 'Inspector', type: 'text', required: true },
        { key: 'on_site', label: 'On site', type: 'boolean', required: false },
      ],
      summary: { column: 'inspector', strategy: 'min' },
    })
  })

  it('downgrades the "Selected row" strategy when row selection is turned off', () => {
    renderTableForm()
    addColumn('Inspector')
    fireEvent.click(screen.getByRole('checkbox', { name: 'Row selection' }))
    pick(screen.getByRole('combobox', { name: 'Summary column' }), 'Inspector')
    pick(screen.getByRole('combobox', { name: 'Strategy' }), 'Selected row')
    expect(screen.getByRole('combobox', { name: 'Strategy' })).toHaveTextContent('Selected row')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Row selection' }))

    expect(screen.getByRole('combobox', { name: 'Strategy' })).toHaveTextContent('Maximum')
  })

  it('hydrates the columns of an existing table attribute', () => {
    render(
      <AttributeForm
        mode={{
          type: 'edit',
          attribute: {
            id: 5,
            code: 'inspections',
            name: 'Inspections',
            type: 'table',
            description: null,
            help_text: null,
            placeholder: null,
            icon: null,
            config: { columns: [{ key: 'inspector', label: 'Inspector', type: 'text', required: false }] },
            relation_target: null,
            options: [],
            created_at: '2026-01-01T00:00:00Z',
            permissions: FULL_ACCESS_PERMISSIONS,
          },
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText('Column label')).toHaveValue('Inspector')
    expect(screen.getByLabelText('Column key')).toHaveValue('inspector')
  })
})
