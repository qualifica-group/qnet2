import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CustomFieldDefinitionForm } from '@/features/custom-fields/custom-field-definition-form'
import type { CustomFieldDefinitionDetailWithPermissions } from '@/features/custom-fields/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0021 AC-025: the `type`-conditional options/relation_target sub-forms. */

const createDefinitionMock = vi.fn()
const updateDefinitionMock = vi.fn()

vi.mock('@/features/custom-fields/api', () => ({
  createCustomFieldDefinition: (...args: unknown[]) => createDefinitionMock(...args),
  updateCustomFieldDefinition: (...args: unknown[]) => updateDefinitionMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const ENTITIES = [
  { entity_type: 'companies', label: 'customFields.entities.companies' },
  { entity_type: 'products', label: 'customFields.entities.products' },
]

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: ENTITIES, isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: ENTITIES, isLoading: false, isError: false }),
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

function definition(
  overrides: Partial<CustomFieldDefinitionDetailWithPermissions> = {},
): CustomFieldDefinitionDetailWithPermissions {
  return {
    id: 4,
    entity_type: 'companies',
    key: 'loyalty_tier',
    type: 'enum',
    label: 'Loyalty tier',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    group: null,
    tab: null,
    sort_order: 0,
    default_value: null,
    config: { display: 'select' },
    validation: null,
    relation_target: null,
    is_indexed: false,
    is_active: true,
    options: [
      { id: 1, value: 'gold', label: 'Gold', color: null, icon: null, sort_order: 0, is_default: true },
      { id: 2, value: 'silver', label: 'Silver', color: null, icon: null, sort_order: 1, is_default: false },
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
  createDefinitionMock.mockReset()
  updateDefinitionMock.mockReset()
})

describe('CustomFieldDefinitionForm — type-conditional sub-forms (AC-025)', () => {
  it('shows the type select and hides both the options editor and the relation target editor for the default text type', () => {
    render(
      <CustomFieldDefinitionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: 'Type' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('reveals the options editor and hides the relation target editor once List of options is selected', () => {
    render(
      <CustomFieldDefinitionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'List of options' }))

    expect(screen.getByRole('button', { name: 'Add option' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('reveals the relation target editor (entity_type + cardinality + picker resource) and hides options once Relation is selected', () => {
    render(
      <CustomFieldDefinitionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'Relation' }))

    expect(screen.getByRole('combobox', { name: 'Target module' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Cardinality' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Picker resource' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
  })

  it('hydrates the enum options in edit mode', () => {
    render(
      <CustomFieldDefinitionForm
        mode={{ type: 'edit', definition: definition() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getAllByLabelText('Value').map((input) => (input as HTMLInputElement).value)).toEqual([
      'gold',
      'silver',
    ])
  })

  it('submits the create payload with options for an enum field', async () => {
    createDefinitionMock.mockResolvedValue(definition())

    render(
      <CustomFieldDefinitionForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Module' }))
    fireEvent.click(screen.getByRole('option', { name: 'Companies' }))
    fireEvent.change(screen.getByLabelText('Key'), { target: { value: 'loyalty_tier' } })
    fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
    fireEvent.click(screen.getByRole('option', { name: 'List of options' }))
    fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Loyalty tier' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getByLabelText('Value'), { target: { value: 'gold' } })
    fireEvent.change(screen.getAllByLabelText('Label')[1], { target: { value: 'Gold' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createDefinitionMock).toHaveBeenCalledTimes(1))
    expect(createDefinitionMock).toHaveBeenCalledWith(
      expect.objectContaining({
        entity_type: 'companies',
        key: 'loyalty_tier',
        type: 'enum',
        label: 'Loyalty tier',
        options: [{ value: 'gold', label: 'Gold', color: undefined, icon: undefined, sort_order: 0, is_default: false }],
      }),
    )
  })
})
