import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskStatusDetailView } from '@/features/task-statuses/task-status-detail'
import type { TaskStatusDetailWithPermissions } from '@/features/task-statuses/types'

/**
 * Spec 0101: the detail shows the name (hero title), description, the palette
 * color as its localized token name, the icon as its canonical lucide name,
 * the phase label, the completion percentage, the server-managed order and the
 * active flag. Purely
 * presentational, so the Activity Log gate is exercised directly on the
 * `permissions.actions.view_activity` prop.
 *
 * The fixture values deliberately never read like a field LABEL: the detail
 * renders label and value as siblings, so a `description` of "Description"
 * makes `getByText` ambiguous the moment the locale bundle resolves the
 * label to the same word.
 */

const activityLogSectionMock = vi.fn()

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
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
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the localized label of the phase the status belongs to', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus({ group: 'in_validation' })} />)

    expect(screen.getByText(i18n.t('taskStatuses.form.group.in_validation'))).toBeInTheDocument()
  })

  it('shows the completion percentage projected onto every task in this status (D-6)', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus({ completion_percentage: 100 })} />)

    expect(screen.getByText('100%')).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description and an unset icon', () => {
    render(<TaskStatusDetailView taskStatus={taskStatus({ description: null, icon: null })} />)

    expect(screen.getAllByText('—')).toHaveLength(2)
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
