import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { DocumentBundleForm } from '@/features/document-bundles/document-bundle-form'
import type { DocumentBundleWithPermissions } from '@/features/document-bundles/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0175 AC-022: name/description/is_active only — files are managed by
 * the detail's `DocumentsSection`, not this form.
 */

const createDocumentBundleMock = vi.fn()
const updateDocumentBundleMock = vi.fn()

vi.mock('@/features/document-bundles/api', () => ({
  createDocumentBundle: (...args: unknown[]) => createDocumentBundleMock(...args),
  updateDocumentBundle: (...args: unknown[]) => updateDocumentBundleMock(...args),
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

vi.mock('@/features/document-bundles/use-document-bundle-form-meta', () => ({
  useDocumentBundleFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
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
  updateDocumentBundleMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('DocumentBundleForm — create (spec 0175)', () => {
  it('renders the name, description and active controls', () => {
    render(<DocumentBundleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(labelFor('documentBundles.form.name'))).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('documentBundles.form.description'))).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<DocumentBundleForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('documentBundles.form.save') }))

    const nameInput = await screen.findByLabelText(labelFor('documentBundles.form.name'))
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText(i18n.t('documentBundles.form.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createDocumentBundleMock).not.toHaveBeenCalled()
  })

  it('submits the create payload', async () => {
    createDocumentBundleMock.mockResolvedValue(documentBundle())
    const onSuccess = vi.fn()

    render(<DocumentBundleForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('documentBundles.form.name')), {
      target: { value: 'Onboarding kit' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('documentBundles.form.save') }))

    await waitFor(() => expect(createDocumentBundleMock).toHaveBeenCalledTimes(1))
    expect(createDocumentBundleMock).toHaveBeenCalledWith({
      name: 'Onboarding kit',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(documentBundle()))
  })
})

describe('DocumentBundleForm — edit (spec 0175)', () => {
  it('hydrates every field from the loaded detail', () => {
    render(
      <DocumentBundleForm
        mode={{ type: 'edit', documentBundle: documentBundle() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('documentBundles.form.name'))).toHaveValue('Onboarding kit')
    expect(screen.getByLabelText(labelFor('documentBundles.form.description'))).toHaveValue(
      'Standard welcome pack',
    )
  })

  it('submits only the changed field on a partial update', async () => {
    updateDocumentBundleMock.mockResolvedValue(documentBundle({ name: 'Renamed' }))

    render(
      <DocumentBundleForm
        mode={{ type: 'edit', documentBundle: documentBundle() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('documentBundles.form.name')), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('documentBundles.form.save') }))

    await waitFor(() => expect(updateDocumentBundleMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateDocumentBundleMock.mock.calls[0]
    expect(id).toBe(3)
    expect(payload).toEqual({ name: 'Renamed' })
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateDocumentBundleMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )

    render(
      <DocumentBundleForm
        mode={{ type: 'edit', documentBundle: documentBundle() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('documentBundles.form.name')), {
      target: { value: 'Duplicate' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('documentBundles.form.save') }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
