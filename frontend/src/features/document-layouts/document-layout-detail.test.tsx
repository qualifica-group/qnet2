import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { DocumentLayoutDetailView } from '@/features/document-layouts/document-layout-detail'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'

/**
 * Spec 0069: the detail shows name, code, description, module, active and
 * default status, and both timestamps, with the shared detail kit's
 * placeholder on the nullable field. Wave 3 of the "no edit pages" plan:
 * every row but the immutable module edits in place, each save a PATCH of
 * that field alone, and the content (the visual editor) is one full-width
 * row. The Activity Log section gate is exercised directly on the
 * `permissions.actions.view_activity` prop.
 */

const updateDocumentLayoutMock = vi.fn()

vi.mock('@/features/document-layouts/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-layouts/api')>()),
  updateDocumentLayout: (...args: unknown[]) => updateDocumentLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/document-layouts/variables-api', () => ({
  useDocumentLayoutVariables: () => ({ data: { module: 'quotes', categories: [] }, isLoading: false }),
}))
vi.mock('@/features/document-layouts/editor/images/document-layout-images-api', () => ({
  useDocumentLayoutImages: () => ({ data: [] }),
  useUploadDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
  useDeleteDocumentLayoutImage: () => ({ mutate: vi.fn(), isPending: false }),
}))

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function documentLayout(
  overrides: Partial<DocumentLayoutDetailWithPermissions> = {},
): DocumentLayoutDetailWithPermissions {
  return {
    id: 7,
    name: 'Standard quote layout',
    code: 'standard',
    description: 'Default layout used for every quote',
    module: 'quotes',
    module_label: 'Quotes',
    is_active: true,
    is_default: true,
    config: createEmptyDocumentLayoutConfig(),
    images: [],
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

const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateDocumentLayoutMock.mockReset()
})

describe('DocumentLayoutDetailView — detail fields (AC-134)', () => {
  it('shows name, code, description, module label, active/default status and both timestamps', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.getByRole('heading', { name: 'Standard quote layout' })).toBeInTheDocument()
    expect(screen.getByText('standard')).toBeInTheDocument()
    expect(screen.getByText('Default layout used for every quote')).toBeInTheDocument()
    expect(screen.getByText('Quotes')).toBeInTheDocument()
    expect(screen.getAllByText('Yes')).toHaveLength(2)
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('shows the inactive and non-default status', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({ is_active: false, is_default: false })}
      />,
    )

    expect(screen.getAllByText('No')).toHaveLength(2)
  })
})

describe('DocumentLayoutDetailView — activity log section', () => {
  it('mounts the section for the viewed layout when view_activity is granted', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({
          permissions: { ...documentLayout().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'document-layouts', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('DocumentLayoutDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil, except the immutable module', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(i18n.t('documentLayouts.form.name'))).not.toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.name')).toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.isDefault')).toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.tabs.content')).toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.module')).not.toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.code')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never code or module', async () => {
    updateDocumentLayoutMock.mockResolvedValue(documentLayout({ name: 'Renamed' }))
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    fireEvent.click(pencilOf('documentLayouts.form.name') as HTMLElement)
    fireEvent.change(screen.getByLabelText(new RegExp(`^${i18n.t('documentLayouts.form.name')}`)), {
      target: { value: 'Renamed' },
    })
    saveRow()

    await waitFor(() => expect(updateDocumentLayoutMock).toHaveBeenCalledWith(7, { name: 'Renamed' }))
  })

  it('the content row opens the visual editor and saves the config alone', async () => {
    updateDocumentLayoutMock.mockResolvedValue(documentLayout())
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    fireEvent.click(pencilOf('documentLayouts.form.tabs.content') as HTMLElement)
    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[1], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Spacer' }))
    saveRow()

    await waitFor(() => expect(updateDocumentLayoutMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateDocumentLayoutMock.mock.calls[0]
    expect(id).toBe(7)
    expect(Object.keys(payload)).toEqual(['config'])
    expect(payload.config.body.blocks[0]).toMatchObject({ type: 'spacer' })
  })

  it('Cancel restores the row without calling the API', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    fireEvent.click(pencilOf('documentLayouts.form.name') as HTMLElement)
    fireEvent.change(screen.getByLabelText(new RegExp(`^${i18n.t('documentLayouts.form.name')}`)), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateDocumentLayoutMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateDocumentLayoutMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    fireEvent.click(pencilOf('documentLayouts.form.name') as HTMLElement)
    fireEvent.change(screen.getByLabelText(new RegExp(`^${i18n.t('documentLayouts.form.name')}`)), {
      target: { value: 'Duplicate' },
    })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('maps a config path 422 onto the offending block of the open editor (AC-129)', async () => {
    updateDocumentLayoutMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { 'config.body.blocks.0.height': ['Height must be at least 1.'] },
        },
      } as never),
    )
    const layout = documentLayout()
    layout.config = { ...layout.config, body: { blocks: [{ id: 's1', type: 'spacer', height: 12 }] } }
    render(<DocumentLayoutDetailView documentLayout={layout} />)

    fireEvent.click(pencilOf('documentLayouts.form.tabs.content') as HTMLElement)
    fireEvent.pointerDown(screen.getAllByRole('button', { name: 'Add block' })[0], { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Spacer' }))
    saveRow()

    await waitFor(() =>
      expect(screen.getByText(i18n.t('documentLayouts.form.configValidationError'))).toBeInTheDocument(),
    )
    // The block added to the header comes first in the DOM; the refused one is the body's.
    fireEvent.click(screen.getAllByText('Spacer (12pt)')[1])
    expect(screen.getByText('Height must be at least 1.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({
          permissions: {
            ...documentLayout().permissions,
            resource: { ...documentLayout().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('documentLayouts.form.name')).not.toBeInTheDocument()
    expect(pencilOf('documentLayouts.form.tabs.content')).not.toBeInTheDocument()
  })
})

describe('DocumentLayoutDetailView — clone (spec 0339, AC-007)', () => {
  const cloneButton = () => screen.queryByRole('button', { name: i18n.t('documentLayouts.clone.action') })

  it('opens the pre-filled clone dialog from the header when create is allowed', () => {
    render(
      <MemoryRouter>
        <DocumentLayoutDetailView documentLayout={documentLayout()} />
      </MemoryRouter>,
    )

    fireEvent.click(cloneButton() as HTMLElement)

    expect(screen.getByRole('dialog', { name: i18n.t('documentLayouts.clone.title') })).toBeInTheDocument()
    expect(screen.getByDisplayValue('standard_copy')).toBeInTheDocument()
  })

  it('offers no clone button without create', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({
          permissions: {
            ...documentLayout().permissions,
            resource: { ...documentLayout().permissions.resource, create: false },
          },
        })}
      />,
    )

    expect(cloneButton()).not.toBeInTheDocument()
  })
})
