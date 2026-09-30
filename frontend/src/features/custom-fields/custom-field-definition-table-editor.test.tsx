import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { CustomFieldDefinitionForm } from '@/features/custom-fields/custom-field-definition-form'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0180 AC-021: the `table` columns editor inside the custom field definition form. */

const createDefinitionMock = vi.fn()

vi.mock('@/features/custom-fields/api', () => ({
  createCustomFieldDefinition: (...args: unknown[]) => createDefinitionMock(...args),
  updateCustomFieldDefinition: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({
    data: [{ entity_type: 'companies', label: 'customFields.entities.companies' }],
    isLoading: false,
    isError: false,
  }),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/custom-fields/use-custom-field-definition-form-meta', () => ({
  useCustomFieldDefinitionFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
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
  createDefinitionMock.mockReset()
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
  render(<CustomFieldDefinitionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
  pick(screen.getByRole('combobox', { name: 'Module' }), 'Companies')
  fireEvent.change(screen.getByLabelText('Key'), { target: { value: 'inspections' } })
  fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Inspections' } })
  pick(screen.getByRole('combobox', { name: 'Type' }), 'Table')
}

function addColumn(label: string) {
  fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
  const labels = screen.getAllByLabelText('Column label')
  fireEvent.change(labels[labels.length - 1], { target: { value: label } })
}

describe('DefinitionTableColumnsEditor in the custom field form (AC-021)', () => {
  it('hides the type-level indexing toggle and the top-level options editor for a table', () => {
    renderTableForm()

    expect(screen.getByRole('button', { name: 'Add column' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
    expect(screen.queryByRole('switch', { name: 'Indexed' })).not.toBeInTheDocument()
  })

  it('suggests the column key from the label until the key is edited by hand', () => {
    renderTableForm()
    addColumn('Data verifica')

    expect(screen.getByLabelText('Column key')).toHaveValue('data_verifica')

    fireEvent.change(screen.getByLabelText('Column key'), { target: { value: 'checked_on' } })
    fireEvent.change(screen.getByLabelText('Column label'), { target: { value: 'Other label' } })
    expect(screen.getByLabelText('Column key')).toHaveValue('checked_on')
  })

  it('reorders and removes columns', () => {
    renderTableForm()
    addColumn('First')
    addColumn('Second')

    fireEvent.click(screen.getByRole('button', { name: 'Move up 2' }))
    expect(screen.getAllByLabelText('Column label').map((input) => (input as HTMLInputElement).value)).toEqual([
      'Second',
      'First',
    ])

    fireEvent.click(screen.getByRole('button', { name: 'Remove column 1' }))
    expect(screen.getAllByLabelText('Column label')).toHaveLength(1)
  })

  // Full admin flow (five Radix selects, each keystroke re-renders the live preview): ~2s alone, but it
  // exceeds the default 5s when the whole suite runs in parallel, so this one test gets an explicit budget.
  it('submits a payload conforming to TableFieldConfig', { timeout: 20_000 }, async () => {
    createDefinitionMock.mockResolvedValue({})
    renderTableForm()

    addColumn('Inspection date')
    pick(screen.getAllByRole('combobox', { name: 'Column type' })[0], 'Date')
    addColumn('Phase')
    pick(screen.getAllByRole('combobox', { name: 'Column type' })[1], 'List of options')
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getByLabelText('Value 1'), { target: { value: 'stage_1' } })
    fireEvent.change(screen.getByLabelText('Label 1'), { target: { value: 'Stage 1' } })
    fireEvent.click(screen.getAllByRole('checkbox', { name: 'Required' })[1])

    fireEvent.click(screen.getByRole('checkbox', { name: 'Row selection' }))
    fireEvent.change(screen.getByLabelText('Selection label'), { target: { value: 'Active' } })
    fireEvent.change(screen.getByLabelText('Selection key'), { target: { value: 'active' } })
    pick(screen.getByRole('combobox', { name: 'Summary column' }), 'Inspection date')
    pick(screen.getByRole('combobox', { name: 'Strategy' }), 'Selected row')
    fireEvent.change(screen.getByLabelText('Maximum rows'), { target: { value: '10' } })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createDefinitionMock).toHaveBeenCalledTimes(1))
    const payload = createDefinitionMock.mock.calls[0][0]
    expect(payload.type).toBe('table')
    expect(payload.is_indexed).toBe(false)
    expect(payload.options).toBeUndefined()
    expect(payload.config).toEqual({
      columns: [
        { key: 'inspection_date', label: 'Inspection date', type: 'date', required: false },
        {
          key: 'phase',
          label: 'Phase',
          type: 'enum',
          required: true,
          options: [{ value: 'stage_1', label: 'Stage 1' }],
        },
      ],
      selectable: { key: 'active', label: 'Active' },
      summary: { column: 'inspection_date', strategy: 'selected' },
      max_rows: 10,
    })
  })

  it('blocks the submit with an inline message on duplicate keys and on an enum without options', async () => {
    renderTableForm()
    addColumn('Phase')
    addColumn('Phase')
    pick(screen.getAllByRole('combobox', { name: 'Column type' })[0], 'List of options')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Duplicate key.')).toBeInTheDocument()
    expect(screen.getByText('Add at least one option.')).toBeInTheDocument()
    expect(createDefinitionMock).not.toHaveBeenCalled()
  })

  it('requires at least one column', async () => {
    renderTableForm()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Add at least one column.')).toBeInTheDocument()
    expect(createDefinitionMock).not.toHaveBeenCalled()
  })

  it('shows a server 422 config.columns.N.key error on the matching column', async () => {
    const error = new AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: { errors: { 'config.columns.0.key': ['The column key is reserved.'] } },
      statusText: 'Unprocessable',
      headers: {},
      config: { headers: {} } as never,
    })
    createDefinitionMock.mockRejectedValue(error)
    renderTableForm()
    addColumn('Phase')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The column key is reserved.')).toBeInTheDocument()
  })
})
