import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PipelineStatusForm } from '@/features/pipeline-statuses/pipeline-status-form'
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0023 / 0039 / 0235: the form is create-only, a replica of the detail
 * with its rows closed (the in-place edits live in `pipeline-status-detail.test.tsx`).
 */

const createPipelineStatusMock = vi.fn()

vi.mock('@/features/pipeline-statuses/api', () => ({
  createPipelineStatus: (...args: unknown[]) => createPipelineStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

// The create-context metadata, also read by the custom-fields integration (spec 0021)
// to build the dynamic schema; this suite has no custom fields, so the catalogue is empty.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Opens a closed row through its pencil. */
async function openRow(key: string) {
  fireEvent.click(
    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }),
  )
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function pipelineStatus(): PipelineStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Draft',
    color: null,
    sort_order: 1,
    system_key: null,
    group: 'open',
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createPipelineStatusMock.mockReset()
})

describe('PipelineStatusForm — create', () => {
  it('starts with every row closed, with no order input (D-5)', async () => {
    render(<PipelineStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('pipelineStatuses.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('opens the group row on its select, defaulting to Open', async () => {
    render(<PipelineStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('pipelineStatuses.form.group.label')

    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Open')
  })

  it('does not call the API and shows the error on the closed row when name is empty', async () => {
    render(<PipelineStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await screen.findAllByRole('button', { name: 'Save' })
    save()

    expect(await screen.findByText(i18n.t('pipelineStatuses.form.nameRequired'))).toHaveAttribute('role', 'alert')
    expect(createPipelineStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createPipelineStatusMock.mockResolvedValue(pipelineStatus())
    const onSuccess = vi.fn()

    render(<PipelineStatusForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('pipelineStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Draft' } })
    save()

    await waitFor(() => expect(createPipelineStatusMock).toHaveBeenCalledTimes(1))
    expect(createPipelineStatusMock).toHaveBeenCalledWith({ name: 'Draft', color: null, group: 'open' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(pipelineStatus()))
  })

  it('submits the picked group', async () => {
    createPipelineStatusMock.mockResolvedValue(pipelineStatus())

    render(<PipelineStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await openRow('pipelineStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Draft' } })
    await openRow('pipelineStatuses.form.group.label')
    fireEvent.click(screen.getByRole('combobox', { name: 'Group' }))
    fireEvent.click(screen.getByRole('option', { name: 'Closed' }))
    save()

    await waitFor(() => expect(createPipelineStatusMock).toHaveBeenCalledTimes(1))
    expect(createPipelineStatusMock).toHaveBeenCalledWith({ name: 'Draft', color: null, group: 'closed' })
  })
})
