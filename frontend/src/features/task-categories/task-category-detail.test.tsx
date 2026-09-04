import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { TaskCategoryDetailView } from '@/features/task-categories/task-category-detail'
import type { TaskCategoryDetailWithPermissions } from '@/features/task-categories/types'

/**
 * Spec 0101: the detail shows the name (hero title), description, the palette
 * color as its localized token name, the icon as its canonical lucide name,
 * the server-managed order and the active flag. Purely
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

function taskCategory(
  overrides: Partial<TaskCategoryDetailWithPermissions> = {},
): TaskCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Commercial',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
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

describe('TaskCategoryDetailView — detail fields', () => {
  it('shows the name, description, color token, icon, order and both timestamps', () => {
    render(<TaskCategoryDetailView taskCategory={taskCategory()} />)

    expect(screen.getByRole('heading', { name: 'Commercial' })).toBeInTheDocument()
    expect(screen.getByText('Follow-up on the client request')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('star')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description and an unset icon', () => {
    render(<TaskCategoryDetailView taskCategory={taskCategory({ description: null, icon: null })} />)

    expect(screen.getAllByText('—')).toHaveLength(2)
  })
})

describe('TaskCategoryDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <TaskCategoryDetailView
        taskCategory={taskCategory({
          permissions: { ...taskCategory().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'task-categories', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<TaskCategoryDetailView taskCategory={taskCategory()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
