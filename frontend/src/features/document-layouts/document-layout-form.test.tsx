import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { DocumentLayoutForm } from '@/features/document-layouts/document-layout-form'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

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
const updateDocumentLayoutMock = vi.fn()

vi.mock('@/features/document-layouts/api', () => ({
  createDocumentLayout: (...args: unknown[]) => createDocumentLayoutMock(...args),
  updateDocumentLayout: (...args: unknown[]) => updateDocumentLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const READONLY: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** Every field editable, matching the create-context metadata (`code`/`module` editable while `$model === null`). */
const CREATE_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** `code`/`module` readonly, everything else editable, matching an existing record's metadata. */
const EDIT_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    code: READONLY,
    description: EDITABLE,
    module: READONLY,
    is_active: EDITABLE,
    is_default: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = CREATE_PERMISSIONS

vi.mock('@/features/document-layouts/use-document-layout-form-meta', () => ({
  useDocumentLayoutFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

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
    permissions: EDIT_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEn }, true, true)
})

beforeEach(() => {
  createDocumentLayoutMock.mockReset()
  updateDocumentLayoutMock.mockReset()
  metaPermissions = CREATE_PERMISSIONS
})

describe('DocumentLayoutForm — create (spec 0069, AC-113)', () => {
  it('renders name, code, description, module, is_active and is_default', () => {
    render(
      <DocumentLayoutForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Code/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Module' })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.getByRole('switch', { name: 'Default' })).not.toBeChecked()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(
      <DocumentLayoutForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'standard_quote' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const nameInput = await screen.findByLabelText(/^Name/)
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText('Name is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createDocumentLayoutMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, including the default empty config', async () => {
    createDocumentLayoutMock.mockResolvedValue(documentLayout())
    const onSuccess = vi.fn()

    render(
      <DocumentLayoutForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Standard quote layout' } })
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'standard_quote' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

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

describe('DocumentLayoutForm — edit (spec 0069, AC-114)', () => {
  it('hydrates every field and renders code/module disabled', () => {
    metaPermissions = EDIT_PERMISSIONS

    render(
      <DocumentLayoutForm
        mode={{ type: 'edit', documentLayout: documentLayout() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Standard quote layout')
    expect(screen.getByLabelText(/^Code/)).toHaveValue('standard_quote')
    expect(screen.getByLabelText(/^Code/)).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Module' })).toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toHaveValue('The default quote layout')
    expect(screen.getByRole('switch', { name: 'Default' })).toBeChecked()
  })

  it('submits only the changed field on a partial update, never code or module', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateDocumentLayoutMock.mockResolvedValue(documentLayout({ name: 'Renamed layout' }))

    render(
      <DocumentLayoutForm
        mode={{ type: 'edit', documentLayout: documentLayout() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Renamed layout' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateDocumentLayoutMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateDocumentLayoutMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Renamed layout' })
    expect(payload).not.toHaveProperty('code')
    expect(payload).not.toHaveProperty('module')
    expect(payload).not.toHaveProperty('config')
  })

  it('maps a server 422 onto the matching form field', async () => {
    metaPermissions = EDIT_PERMISSIONS
    updateDocumentLayoutMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { name: ['Name already taken.'] },
        },
      } as never),
    )

    render(
      <DocumentLayoutForm
        mode={{ type: 'edit', documentLayout: documentLayout() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Duplicate name' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
