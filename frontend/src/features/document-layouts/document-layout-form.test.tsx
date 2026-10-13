import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DocumentLayoutForm } from '@/features/document-layouts/document-layout-form'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The `documentLayouts` i18n namespace is owned by another teammate (spec
 * 0069 wave 1 report): not a locale file under `i18n/locales/` (out of
 * scope), registered directly on the shared i18n instance so this test
 * exercises the real rendered copy instead of raw translation keys. The
 * exact strings below are the ones requested for `en-document-layouts.ts`.
 */
const documentLayoutsEn = {
  form: {
    name: 'Name',
    code: 'Code',
    description: 'Description',
    module: 'Module',
    isActive: 'Active',
    isDefault: 'Default',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Document layout created successfully.',
    updated: 'Document layout updated successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    codeRequired: 'Code is required.',
    codeMax: 'Code must be at most 64 characters.',
    codeInvalid:
      'Code must start with a lowercase letter and contain only lowercase letters, digits and underscores.',
    descriptionMax: 'Description must be at most 500 characters.',
    moduleRequired: 'Module is required.',
    genericError: 'Something went wrong. Please try again.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, code, description, module and status.',
      },
    },
    hints: {
      codeLocked: 'The code cannot be changed after creation.',
      moduleLocked: 'The module cannot be changed after creation.',
      isDefault: 'The default layout of its module is used automatically when none is specified on the record.',
    },
  },
  modules: {
    quotes: 'Quotes',
  },
}

const createDocumentLayoutMock = vi.fn()

vi.mock('@/features/document-layouts/api', () => ({
  createDocumentLayout: (...args: unknown[]) => createDocumentLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field editable, matching the create-context metadata (`code`/`module` editable while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

let metaPermissions: ResourcePermissions = CREATE_PERMISSIONS

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: metaPermissions }, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/document-layouts/variables-api', () => ({
  useDocumentLayoutVariables: () => ({ data: { module: 'quotes', categories: [] }, isLoading: false }),
}))
vi.mock('@/features/document-layouts/editor/images/document-layout-images-api', () => ({
  useDocumentLayoutImages: () => ({ data: [] }),
  useUploadDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
  useDeleteDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
}))

/** Opens a closed row through its pencil. */
function openRow(label: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function documentLayout(
  overrides: Partial<DocumentLayoutDetailWithPermissions> = {},
): DocumentLayoutDetailWithPermissions {
  return {
    id: 9,
    name: 'Standard quote layout',
    code: 'standard_quote',
    description: 'The default quote layout',
    module: 'quotes',
    module_label: 'Quotes',
    is_active: true,
    is_default: true,
    config: createEmptyDocumentLayoutConfig(),
    images: [],
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: CREATE_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEn }, true, true)
})

beforeEach(() => {
  createDocumentLayoutMock.mockReset()
  metaPermissions = CREATE_PERMISSIONS
})

describe('DocumentLayoutForm — create (spec 0069, AC-113)', () => {
  it('starts with every row closed and the content editor open', () => {
    render(<DocumentLayoutForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox', { name: /^Name/ })).not.toBeInTheDocument()
    expect(screen.getByText('Header')).toBeInTheDocument()

    openRow('Name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('opens code, module and the switches from their rows', () => {
    render(<DocumentLayoutForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('Code')
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    openRow('Module')
    expect(screen.getByRole('combobox', { name: 'Module' })).toBeInTheDocument()
    openRow('Default')
    expect(screen.getByRole('switch', { name: 'Default' })).not.toBeChecked()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<DocumentLayoutForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('Code')
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'standard_quote' } })
    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('Name')
    const nameInput = screen.getByLabelText(/^Name/)
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createDocumentLayoutMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, including the default empty config', async () => {
    createDocumentLayoutMock.mockResolvedValue(documentLayout())
    const onSuccess = vi.fn()

    render(<DocumentLayoutForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('Name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard quote layout' } })
    openRow('Code')
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'standard_quote' } })
    save()

    await waitFor(() => expect(createDocumentLayoutMock).toHaveBeenCalledTimes(1))
    expect(createDocumentLayoutMock).toHaveBeenCalledWith({
      name: 'Standard quote layout',
      code: 'standard_quote',
      module: 'quotes',
      config: createEmptyDocumentLayoutConfig(),
      description: null,
      is_active: true,
      is_default: false,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(documentLayout()))
  })
})
