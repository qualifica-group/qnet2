import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { PipelineStatusDetailView } from '@/features/pipeline-statuses/pipeline-status-detail'
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'

/**
 * Spec 0023 / 0039 / 0235: the detail shows name, color, group and the
 * server-managed order; every row but the order edits in place, each save a
 * PATCH of that field alone. A system row ("Nuovo"/"Chiuso") keeps its group
 * fixed: name and color still edit.
 */

const updatePipelineStatusMock = vi.fn()

vi.mock('@/features/pipeline-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/pipeline-statuses/api')>()),
  updatePipelineStatus: (...args: unknown[]) => updatePipelineStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// No custom fields on this resource: the detail's custom-field sections stay empty.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: pipelineStatus().permissions }),
}))

function pipelineStatus(
  overrides: Partial<PipelineStatusDetailWithPermissions> = {},
): PipelineStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Draft',
    color: 'blue',
    sort_order: 3,
    system_key: null,
    group: 'open',
    created_at: '2026-01-01T09:00:00Z',
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
  updatePipelineStatusMock.mockReset()
})

describe('PipelineStatusDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus()} />)

    expect(screen.getByRole('heading', { name: 'Draft' })).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('Open')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('pipelineStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('pipelineStatuses.form.group.label')).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updatePipelineStatusMock.mockResolvedValue(pipelineStatus({ name: 'Renamed' }))
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus()} />)

    fireEvent.click(pencilOf('pipelineStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updatePipelineStatusMock).toHaveBeenCalledWith(9, { name: 'Renamed' }))
  })

  it('Save sends only the newly picked group', async () => {
    updatePipelineStatusMock.mockResolvedValue(pipelineStatus({ group: 'closed' }))
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus()} />)

    fireEvent.click(pencilOf('pipelineStatuses.form.group.label') as HTMLElement)
    fireEvent.click(screen.getByRole('combobox', { name: 'Group' }))
    fireEvent.click(screen.getByRole('option', { name: 'Closed' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updatePipelineStatusMock).toHaveBeenCalledWith(9, { group: 'closed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus()} />)

    fireEvent.click(pencilOf('pipelineStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updatePipelineStatusMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updatePipelineStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus()} />)

    fireEvent.click(pencilOf('pipelineStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = pipelineStatus()
    render(
      <PipelineStatusDetailView
        pipelineStatus={pipelineStatus({
          permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } },
        })}
      />,
    )

    expect(pencilOf('pipelineStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('pipelineStatuses.form.group.label')).not.toBeInTheDocument()
  })

  it('keeps the group of a system row fixed while name and color still edit', () => {
    render(<PipelineStatusDetailView pipelineStatus={pipelineStatus({ name: 'Nuovo', system_key: 'new' })} />)

    expect(pencilOf('pipelineStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('pipelineStatuses.form.color')).toBeInTheDocument()
    expect(pencilOf('pipelineStatuses.form.group.label')).not.toBeInTheDocument()
  })
})
