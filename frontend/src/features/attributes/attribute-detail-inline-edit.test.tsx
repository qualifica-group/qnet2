import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { updateAttribute } from '@/features/attributes/api'
import { AttributeDetailView } from '@/features/attributes/attribute-detail'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0240: the attribute detail edits in place, one row at a time. Each row
 * PATCHes its own fields alone (the backend update is a partial PATCH); code
 * and type are fixed at creation and have no pencil.
 */

vi.mock('@/features/attributes/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attributes/api')>()),
  updateAttribute: vi.fn(),
}))
vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: [], isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: [], isLoading: false, isError: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

const NOTES_PERMISSION = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

function permissions(update = true): ResourcePermissions {
  return {
    resource: { view: true, create: true, update, delete: true, export: true, import: true },
    fields: { 'custom.notes': NOTES_PERMISSION },
    actions: {},
  }
}

function attribute(overrides: Partial<AttributeDetailWithPermissions> = {}): AttributeDetailWithPermissions {
  return {
    id: 3,
    code: 'color',
    name: 'Color',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: { display: 'select' },
    relation_target: null,
    options: [
      { id: 1, value: 'red', label: 'Red', color: null, icon: null, sort_order: 0, is_default: false },
      { id: 2, value: 'blue', label: 'Blue', color: null, icon: null, sort_order: 1, is_default: true },
    ],
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissions(),
    ...overrides,
  }
}

function renderDetail(value: AttributeDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<AttributeDetailView attribute={value} />, {
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
  // The columns editor's table control reads the viewport.
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })) as unknown as typeof window.matchMedia
  vi.mocked(updateAttribute).mockReset()
  vi.mocked(updateAttribute).mockImplementation(async () => attribute())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('AttributeDetailView — rows edited in place (spec 0240)', () => {
  it('has no Edit button: the rows are closed, each with its pencil', () => {
    renderDetail(attribute())

    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('Name')).toBeInTheDocument()
    expect(pencilOf('Presentation')).toBeInTheDocument()
    expect(pencilOf('Field settings')).toBeInTheDocument()
    expect(pencilOf('Options')).toBeInTheDocument()
  })

  it('offers no pencil on the fields fixed at creation: code and type', () => {
    renderDetail(attribute())

    expect(pencilOf('Code')).not.toBeInTheDocument()
    expect(pencilOf('Type')).not.toBeInTheDocument()
    expect(screen.getAllByText('color').length).toBeGreaterThan(0)
  })

  it('Save sends the edited field alone', async () => {
    renderDetail(attribute())

    openRow('Name')
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Colour' } })
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledWith(3, { name: 'Colour' }))
  })

  it('Cancel restores the value and makes no call', () => {
    renderDetail(attribute())

    openRow('Name')
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Colour' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(updateAttribute).not.toHaveBeenCalled()
    expect(screen.getByText('Color', { selector: 'span.break-words' })).toBeInTheDocument()
  })

  it('the Presentation row sends only the presentation field that changed', async () => {
    renderDetail(attribute())

    openRow('Presentation')
    fireEvent.change(screen.getByRole('textbox', { name: 'Help text' }), { target: { value: 'Pick a colour' } })
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledWith(3, { help_text: 'Pick a colour' }))
  })

  it('the Options row opens the editor with the saved options and sends options alone', async () => {
    renderDetail(attribute())

    openRow('Options')
    expect(screen.getAllByLabelText('Value').map((input) => (input as HTMLInputElement).value)).toEqual(['red', 'blue'])
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getAllByLabelText('Value')[2], { target: { value: 'green' } })
    fireEvent.change(screen.getAllByLabelText('Label')[2], { target: { value: 'Green' } })
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledTimes(1))
    const payload = vi.mocked(updateAttribute).mock.calls[0][1]
    expect(Object.keys(payload)).toEqual(['options'])
    expect(payload.options?.map((option) => option.value)).toEqual(['red', 'blue', 'green'])
  })

  it('the Columns row hydrates the saved columns of a table attribute', () => {
    renderDetail(
      attribute({
        type: 'table',
        options: [],
        config: { columns: [{ key: 'inspector', label: 'Inspector', type: 'text', required: false }] },
      }),
    )

    openRow('Columns')

    expect(screen.getByLabelText('Column label')).toHaveValue('Inspector')
    expect(screen.getByLabelText('Column key')).toHaveValue('inspector')
  })

  it('a custom field row sends that field alone', async () => {
    renderDetail(attribute({ custom_fields: { notes: 'Existing note' } }))

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Updated note' } })
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledWith(3, { custom_fields: { notes: 'Updated note' } }))
  })

  it('an invalid row blocks the save without a call', async () => {
    renderDetail(attribute())

    openRow('Name')
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: '' } })
    save()

    expect(await screen.findByText(i18n.t('attributes.form.nameRequired'))).toBeInTheDocument()
    expect(updateAttribute).not.toHaveBeenCalled()
  })

  it('a refused save (422) keeps the row open with the server message', async () => {
    vi.mocked(updateAttribute).mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { errors: { name: ['The name is taken.'] } },
        statusText: 'Unprocessable',
        headers: {},
        config: { headers: new AxiosHeaders() },
      }),
    )
    renderDetail(attribute())

    openRow('Name')
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Other' } })
    save()

    expect(await screen.findByText('The name is taken.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Name' })).toBeInTheDocument()
  })

  it('offers no pencil at all without the update permission', () => {
    renderDetail(attribute({ permissions: permissions(false) }))

    expect(screen.queryByRole('button', { name: /^Edit / })).not.toBeInTheDocument()
  })
})
