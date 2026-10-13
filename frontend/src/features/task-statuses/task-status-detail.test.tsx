import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskStatusDetailView } from '@/features/task-statuses/task-status-detail'
import type { TaskStatusDetailWithPermissions } from '@/features/task-statuses/types'

/**
 * Spec 0101: the detail shows the name (hero title), description, the palette
 * color as its localized token name, the icon as its canonical lucide name,
 * the server-managed order and the active flag; every row but the order edits
 * in place (wave 3 of the "no edit pages" plan), each save a PATCH of that
 * field alone. The Activity Log gate is exercised directly on the
 * `permissions.actions.view_activity` prop.
 *
 * The fixture values deliberately never read like a field LABEL: the detail
 * renders label and value as siblings, so a `description` of "Description"
 * makes `getByText` ambiguous the moment the locale bundle resolves the
 * label to the same word.
 */

const activityLogSectionMock = vi.fn()
const updateTaskStatusMock = vi.fn()

vi.mock('@/features/task-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/task-statuses/api')>()),
  updateTaskStatus: (...args: unknown[]) => updateTaskStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function taskStatus(
  overrides: Partial<TaskStatusDetailWithPermissions> = {},
): TaskStatusDetailWithPermissions {
  return {
    id: 4,
    name: 'In progress',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    system_key: null,
    group: 'pending',
    completion_percentage: 25,
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
  activityLogSectionMock.mockReset()
  updateTaskStatusMock.mockReset()
})

describe('TaskStatusDetailView — detail fields', () => {
  it('shows the name, description, color token, icon, order and both timestamps', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    expect(screen.getByRole('heading', { name: 'In progress' })).toBeInTheDocument()
    expect(screen.getByText('Follow-up on the client request')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('star')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText(i18n.t('taskStatuses.form.group.pending'))).toBeInTheDocument()
    expect(screen.getByText('25')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description and an unset icon', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus({ description: null, icon: null })} />)

    expect(screen.getAllByText('—')).toHaveLength(2)
  })
})

describe('TaskStatusDetailView — system row', () => {
  it('offers a pencil only on name, color, icon and percentage (SystemStatusGuard)', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus({ system_key: 'closed_positive' })} />)

    expect(pencilOf('taskStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.color')).toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.icon')).toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.completionPercentage')).toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.description')).not.toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.group.label')).not.toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.isActive')).not.toBeInTheDocument()
  })

  it('sends the new percentage of a system row alone, so every task in it follows (AC-021/AC-044)', async () => {
    updateTaskStatusMock.mockResolvedValue(taskStatus({ system_key: 'closed_negative', completion_percentage: 10 }))
    render(<TaskStatusDetailView taskStatus={taskStatus({ system_key: 'closed_negative', completion_percentage: 0 })} />)

    fireEvent.click(pencilOf('taskStatuses.form.completionPercentage') as HTMLElement)
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '10' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateTaskStatusMock).toHaveBeenCalledWith(4, { completion_percentage: 10 }))
  })
})

describe('TaskStatusDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <TaskStatusDetailView
        taskStatus={taskStatus({
          permissions: { ...taskStatus().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'task-statuses', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('TaskStatusDetailView — in-place editing', () => {
  it('saves the phase alone from its select', async () => {
    updateTaskStatusMock.mockResolvedValue(taskStatus({ group: 'in_validation' }))
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    fireEvent.click(pencilOf('taskStatuses.form.group.label') as HTMLElement)
    fireEvent.click(screen.getByRole('combobox'))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('taskStatuses.form.group.in_validation') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateTaskStatusMock).toHaveBeenCalledWith(4, { group: 'in_validation' }))
  })

  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.group.label')).toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updateTaskStatusMock.mockResolvedValue(taskStatus({ name: 'Renamed' }))
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    fireEvent.click(pencilOf('taskStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateTaskStatusMock).toHaveBeenCalledWith(4, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    fireEvent.click(pencilOf('taskStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateTaskStatusMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateTaskStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<TaskStatusDetailView taskStatus={taskStatus()} />)

    fireEvent.click(pencilOf('taskStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <TaskStatusDetailView
        taskStatus={taskStatus({
          permissions: {
            ...taskStatus().permissions,
            resource: { ...taskStatus().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('taskStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('taskStatuses.form.color')).not.toBeInTheDocument()
  })
})
