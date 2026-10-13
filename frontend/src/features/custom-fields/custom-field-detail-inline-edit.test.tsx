import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { updateCustomFieldDefinition } from '@/features/custom-fields/api'
import { CustomFieldDetailView } from '@/features/custom-fields/custom-field-detail'
import type { CustomFieldDefinitionDetailWithPermissions } from '@/features/custom-fields/types'

/**
 * Spec 0231: the custom field definition detail edits in place, one row at a
 * time. Each row PATCHes its own fields alone (the backend update is a
 * partial PATCH); module, type and key are fixed at creation and have no row.
 */

vi.mock('@/features/custom-fields/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/custom-fields/api')>()),
  updateCustomFieldDefinition: vi.fn(),
}))
vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: [], isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: [], isLoading: false, isError: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

function renderDetail(value: CustomFieldDefinitionDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<CustomFieldDetailView definition={value} />, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (field: string) => screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })
const openRow = (field: string) => fireEvent.click(pencilOf(field) as HTMLElement)
const save = () => fireEvent.click(screen.getByRole('button', { name: 'Save' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateCustomFieldDefinition).mockReset()
  vi.mocked(updateCustomFieldDefinition).mockImplementation(async () => definition())
})

describe('CustomFieldDetailView — rows edited in place (spec 0231)', () => {
  it('has no Edit button: the rows are closed, each with its pencil', () => {
    renderDetail(definition())

    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Label')).toBeInTheDocument()
    expect(pencilOf('Options')).toBeInTheDocument()
    expect(pencilOf('Validation')).toBeInTheDocument()
  })

  it('offers no pencil on the fields fixed at creation: module, type and key', () => {
    renderDetail(definition())

    expect(pencilOf('Module')).not.toBeInTheDocument()
    expect(pencilOf('Type')).not.toBeInTheDocument()
    expect(pencilOf('Key')).not.toBeInTheDocument()
    expect(screen.getByText('loyalty_tier', { selector: 'span.font-mono' })).toBeInTheDocument()
  })

  it('Save sends the edited field alone', async () => {
    renderDetail(definition())

    openRow('Label')
    fireEvent.change(screen.getByRole('textbox', { name: 'Label' }), { target: { value: 'Renamed' } })
    save()

    await waitFor(() => expect(updateCustomFieldDefinition).toHaveBeenCalledWith(4, { label: 'Renamed' }))
  })

  it('a flag row sends its own flag alone', async () => {
    renderDetail(definition())

    openRow('Active')
    fireEvent.click(screen.getByRole('switch', { name: 'Active' }))
    save()

    await waitFor(() => expect(updateCustomFieldDefinition).toHaveBeenCalledWith(4, { is_active: false }))
  })

  it('Cancel restores the value and makes no call', () => {
    renderDetail(definition())

    openRow('Label')
    fireEvent.change(screen.getByRole('textbox', { name: 'Label' }), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(updateCustomFieldDefinition).not.toHaveBeenCalled()
    expect(screen.getByText('Loyalty tier', { selector: 'span.break-words' })).toBeInTheDocument()
  })

  it('the Presentation row sends only the presentation field that changed', async () => {
    renderDetail(definition())

    openRow('Presentation')
    fireEvent.change(screen.getByRole('textbox', { name: 'Help text' }), { target: { value: 'Pick a tier' } })
    save()

    await waitFor(() => expect(updateCustomFieldDefinition).toHaveBeenCalledWith(4, { help_text: 'Pick a tier' }))
  })

  it('the Options row opens the editor with the saved options and sends options alone', async () => {
    renderDetail(definition())

    openRow('Options')
    expect(screen.getAllByLabelText('Value').map((input) => (input as HTMLInputElement).value)).toEqual(['gold', 'silver'])
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getAllByLabelText('Value')[2], { target: { value: 'bronze' } })
    fireEvent.change(screen.getAllByLabelText('Label')[2], { target: { value: 'Bronze' } })
    save()

    await waitFor(() => expect(updateCustomFieldDefinition).toHaveBeenCalledTimes(1))
    const payload = vi.mocked(updateCustomFieldDefinition).mock.calls[0][1]
    expect(Object.keys(payload)).toEqual(['options'])
    expect(payload.options?.map((option) => option.value)).toEqual(['gold', 'silver', 'bronze'])
  })

  it('an invalid row blocks the save without a call', async () => {
    renderDetail(definition())

    openRow('Label')
    fireEvent.change(screen.getByRole('textbox', { name: 'Label' }), { target: { value: '' } })
    save()

    expect(await screen.findByText(i18n.t('customFields.form.labelRequired'))).toBeInTheDocument()
    expect(updateCustomFieldDefinition).not.toHaveBeenCalled()
  })

  it('a refused save (422) keeps the row open with the server message', async () => {
    vi.mocked(updateCustomFieldDefinition).mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { errors: { label: ['The label is taken.'] } },
        statusText: 'Unprocessable',
        headers: {},
        config: { headers: new AxiosHeaders() },
      }),
    )
    renderDetail(definition())

    openRow('Label')
    fireEvent.change(screen.getByRole('textbox', { name: 'Label' }), { target: { value: 'Other' } })
    save()

    expect(await screen.findByText('The label is taken.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Label' })).toBeInTheDocument()
  })

  it('offers no pencil at all without the update permission', () => {
    renderDetail(
      definition({
        permissions: {
          resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
          fields: {},
          actions: {},
        },
      }),
    )

    expect(screen.queryByRole('button', { name: /^Edit / })).not.toBeInTheDocument()
  })
})
