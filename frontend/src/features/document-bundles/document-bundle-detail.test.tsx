import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { DocumentBundleDetailView } from '@/features/document-bundles/document-bundle-detail'
import type { DocumentBundleWithPermissions } from '@/features/document-bundles/types'

/**
 * Spec 0175: the detail shows the name (hero title), description, active
 * flag, files count and both timestamps, and mounts the shared
 * `DocumentsSection` (alias `document_bundle`) gated by the generic
 * `attachments.*` abilities rather than a per-record permission. Name,
 * description and active flag edit in place (wave 3 of the "no edit pages"
 * plan), each save a PATCH of that field alone.
 */

const updateDocumentBundleMock = vi.fn()

vi.mock('@/features/document-bundles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-bundles/api')>()),
  updateDocumentBundle: (...args: unknown[]) => updateDocumentBundleMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => {
    documentsSectionMock(props)
    return <div>documents-section</div>
  },
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const activityLogSectionMock = vi.fn()
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function documentBundle(
  overrides: Partial<DocumentBundleWithPermissions> = {},
): DocumentBundleWithPermissions {
  return {
    id: 3,
    name: 'Onboarding kit',
    description: 'Standard welcome pack',
    is_active: true,
    files_count: 2,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  documentsSectionMock.mockReset()
  activityLogSectionMock.mockReset()
  updateDocumentBundleMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('DocumentBundleDetailView — detail fields', () => {
  it('shows the name, description, active flag, files count and both timestamps', () => {
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    expect(screen.getByRole('heading', { name: 'Onboarding kit' })).toBeInTheDocument()
    expect(screen.getByText('Standard welcome pack')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description', () => {
    render(<DocumentBundleDetailView documentBundle={documentBundle({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('DocumentBundleDetailView — files section', () => {
  it('mounts DocumentsSection scoped to this bundle, gated by the generic attachments abilities', () => {
    canMock.mockImplementation((permission) => permission === 'attachments.create')

    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    expect(documentsSectionMock).toHaveBeenCalledWith({
      resource: 'document_bundle',
      id: 3,
      canUpload: true,
      canDelete: false,
    })
  })
})

describe('DocumentBundleDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <DocumentBundleDetailView
        documentBundle={documentBundle({
          permissions: { ...documentBundle().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'document-bundles', id: 3 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('DocumentBundleDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('documentBundles.form.name')).toBeInTheDocument()
    expect(pencilOf('documentBundles.form.isActive')).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateDocumentBundleMock.mockResolvedValue(documentBundle({ name: 'Renamed' }))
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    fireEvent.click(pencilOf('documentBundles.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateDocumentBundleMock).toHaveBeenCalledWith(3, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    fireEvent.click(pencilOf('documentBundles.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateDocumentBundleMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateDocumentBundleMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<DocumentBundleDetailView documentBundle={documentBundle()} />)

    fireEvent.click(pencilOf('documentBundles.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <DocumentBundleDetailView
        documentBundle={documentBundle({
          permissions: {
            ...documentBundle().permissions,
            resource: { ...documentBundle().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('documentBundles.form.name')).not.toBeInTheDocument()
    expect(pencilOf('documentBundles.form.isActive')).not.toBeInTheDocument()
  })
})
