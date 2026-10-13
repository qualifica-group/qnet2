import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { documentLayoutsEditorEn } from '@/features/document-layouts/editor/editor-i18n-fixture'
import { DocumentLayoutFormBody } from '@/features/document-layouts/document-layout-form-body'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The `documentLayouts` copy this test exercises (metadata form fields +
 * the editor's own namespace) — same "own i18n fixture" pattern as
 * `document-layout-form.test.tsx`.
 */
const documentLayoutsEn = {
  ...documentLayoutsEditorEn,
  form: {
    ...documentLayoutsEditorEn.form,
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
    genericError: 'Something went wrong. Please try again.',
    sections: { identity: { title: 'Details', description: 'Name, code, description, module and status.' } },
    hints: { codeLocked: 'Locked', moduleLocked: 'Locked', isDefault: 'Default hint' },
  },
}

const createDocumentLayoutMock = vi.fn()

vi.mock('@/features/document-layouts/api', () => ({
  createDocumentLayout: (...args: unknown[]) => createDocumentLayoutMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))
vi.mock('@/features/document-layouts/variables-api', () => ({
  useDocumentLayoutVariables: () => ({ data: { module: 'quotes', categories: [] }, isLoading: false }),
}))
vi.mock('@/features/document-layouts/editor/images/document-layout-images-api', () => ({
  useDocumentLayoutImages: () => ({ data: [] }),
  useUploadDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
  useDeleteDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
}))

const FULL_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function documentLayout(overrides: Partial<DocumentLayoutDetailWithPermissions> = {}): DocumentLayoutDetailWithPermissions {
  return {
    id: 9,
    name: 'Standard quote layout',
    code: 'standard_quote',
    description: null,
    module: 'quotes',
    module_label: 'Quotes',
    is_active: true,
    is_default: false,
    config: createEmptyDocumentLayoutConfig(),
    images: [],
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { documentLayouts: documentLayoutsEn }, true, true)
})

beforeEach(() => {
  createDocumentLayoutMock.mockReset()
})

describe('DocumentLayoutFormBody — content editor (spec 0069 wave 2)', () => {
  it('shows the canvas zones open, without any tab', () => {
    render(<DocumentLayoutFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.getByText('Header')).toBeInTheDocument()
    expect(screen.getByText('Body')).toBeInTheDocument()
    expect(screen.getByText('Footer')).toBeInTheDocument()
  })

  it('round-trips an edited config into the create payload untouched (AC-128)', async () => {
    createDocumentLayoutMock.mockResolvedValue(documentLayout())

    render(<DocumentLayoutFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Name' }) }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Layout with content' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Code' }) }))
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'layout_with_content' } })

    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[1], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Spacer' }))

    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createDocumentLayoutMock).toHaveBeenCalledTimes(1))
    const payload = createDocumentLayoutMock.mock.calls[0][0]
    expect(payload.config.body.blocks).toHaveLength(1)
    expect(payload.config.body.blocks[0]).toMatchObject({ type: 'spacer', height: 12 })
    expect(payload.config.header.blocks).toHaveLength(0)
  })

  it('swaps in the invoice starter when the module changes while the content is untouched', async () => {
    createDocumentLayoutMock.mockResolvedValue(documentLayout())

    render(<DocumentLayoutFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Module' }) }))
    fireEvent.click(screen.getByRole('combobox', { name: 'Module' }))
    fireEvent.click(await screen.findByRole('option', { name: i18n.t('documentLayouts.modules.invoices') }))

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Name' }) }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Invoice layout' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Code' }) }))
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'invoice_layout' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createDocumentLayoutMock).toHaveBeenCalledTimes(1))
    const payload = createDocumentLayoutMock.mock.calls[0][0]
    expect(payload.module).toBe('invoices')
    expect(payload.config.body.blocks.length).toBeGreaterThan(0)
  })
})
