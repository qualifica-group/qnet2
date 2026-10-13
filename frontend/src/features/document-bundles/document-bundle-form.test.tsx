import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DocumentBundleForm } from '@/features/document-bundles/document-bundle-form'
import type { DocumentBundleWithPermissions } from '@/features/document-bundles/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0175 AC-022: name/description/is_active only — files are managed by
 * the detail's `DocumentsSection`, not this form. Wave 3 of the "no edit
 * pages" plan: the form is create-only, a replica of the detail with its rows
 * closed (the in-place edits live in `document-bundle-detail.test.tsx`).
 */

const createDocumentBundleMock = vi.fn()

vi.mock('@/features/document-bundles/api', () => ({
  createDocumentBundle: (...args: unknown[]) => createDocumentBundleMock(...args),
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

const ALL_EDITABLE: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: { name: EDITABLE, description: EDITABLE, is_active: EDITABLE },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: metaPermissions }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('documentBundles.form.save') })[0])

function documentBundle(
  overrides: Partial<DocumentBundleWithPermissions> = {},
): DocumentBundleWithPermissions {
  return {
    id: 3,
    name: 'Onboarding kit',
    description: 'Standard welcome pack',
    is_active: true,
    files_count: 2,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: ALL_EDITABLE,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createDocumentBundleMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('DocumentBundleForm — create (spec 0175)', () => {
  it('starts with every row closed; the name row opens on its own control', () => {
    render(<DocumentBundleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('documentBundles.form.name')
    expect(screen.getByLabelText(labelFor('documentBundles.form.name'))).toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<DocumentBundleForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(i18n.t('documentBundles.form.nameRequired'))).toHaveAttribute('role', 'alert')

    openRow('documentBundles.form.name')
    const nameInput = screen.getByLabelText(labelFor('documentBundles.form.name'))
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createDocumentBundleMock).not.toHaveBeenCalled()
  })

  it('submits the create payload', async () => {
    createDocumentBundleMock.mockResolvedValue(documentBundle())
    const onSuccess = vi.fn()

    render(<DocumentBundleForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('documentBundles.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('documentBundles.form.name')), {
      target: { value: 'Onboarding kit' },
    })
    save()

    await waitFor(() => expect(createDocumentBundleMock).toHaveBeenCalledTimes(1))
    expect(createDocumentBundleMock).toHaveBeenCalledWith({
      name: 'Onboarding kit',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(documentBundle()))
  })
})
