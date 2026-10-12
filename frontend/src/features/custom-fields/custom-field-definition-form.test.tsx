import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CustomFieldDefinitionFormBody } from '@/features/custom-fields/custom-field-definition-form-body'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'

/** Spec 0021 AC-025 / 0231: the `type`-conditional options/relation_target editors on the create form (rows closed, editors open). */

const createDefinitionMock = vi.fn()

vi.mock('@/features/custom-fields/api', () => ({
  createCustomFieldDefinition: (...args: unknown[]) => createDefinitionMock(...args),
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

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm() {
  render(
    <ResourcePermissionsProvider permissions={null}>
      <CustomFieldDefinitionFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const openRow = (field: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) }))

function pickType(option: string) {
  openRow('Type')
  fireEvent.click(screen.getByRole('combobox', { name: 'Type' }))
  fireEvent.click(screen.getByRole('option', { name: option }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createDefinitionMock.mockReset()
})

describe('CustomFieldDefinitionForm — type-conditional sub-forms (AC-025)', () => {
  it('keeps every row closed, with the options and relation editors hidden for the default text type', () => {
    renderForm()

    expect(screen.queryByRole('combobox', { name: 'Type' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: 'Label' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('reveals the options editor and hides the relation target editor once List of options is selected', () => {
    renderForm()

    pickType('List of options')

    expect(screen.getByRole('button', { name: 'Add option' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Target module' })).not.toBeInTheDocument()
  })

  it('reveals the relation target editor (entity_type + cardinality + picker resource) and hides options once Relation is selected', () => {
    renderForm()

    pickType('Relation')

    expect(screen.getByRole('combobox', { name: 'Target module' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Cardinality' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Picker resource' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
  })

  it('submits the create payload with options for an enum field', async () => {
    createDefinitionMock.mockResolvedValue({ id: 4 })

    renderForm()

    openRow('Module')
    fireEvent.click(screen.getByRole('combobox', { name: 'Module' }))
    fireEvent.click(screen.getByRole('option', { name: 'Companies' }))
    openRow('Key')
    fireEvent.change(screen.getByLabelText('Key'), { target: { value: 'loyalty_tier' } })
    pickType('List of options')
    openRow('Label')
    fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Loyalty tier' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getByLabelText('Value'), { target: { value: 'gold' } })
    fireEvent.change(screen.getAllByLabelText('Label')[1], { target: { value: 'Gold' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

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
