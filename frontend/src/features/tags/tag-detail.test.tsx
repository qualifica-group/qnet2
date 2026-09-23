import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TagDetailView } from '@/features/tags/tag-detail'
import type { TagDetailWithPermissions } from '@/features/tags/types'

/**
 * Spec 0034, AC-015: representative module for the activity log rollout
 * beyond Users. `TagDetailView` is purely presentational (the caller fetches
 * and passes the detail down), so the section gate is exercised directly on
 * the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function tag(overrides: Partial<TagDetailWithPermissions> = {}): TagDetailWithPermissions {
  return {
    id: 1,
    name: 'Priority',
    created_at: '2026-01-01T00:00:00Z',
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

describe('TagDetailView — activity log section (AC-015)', () => {
  it('mounts the section for the viewed tag when view_activity is granted', () => {
    render(
      <TagDetailView tag={tag({ permissions: { ...tag().permissions, actions: { view_activity: true } } })} />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'tags', id: 1 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(
      <TagDetailView tag={tag({ permissions: { ...tag().permissions, actions: { view_activity: false } } })} />,
    )

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

/** The Modifica action, gated by `onEdit` AND `permissions.resource.update`. */
describe('TagDetailView — edit action', () => {
  it('shows Modifica when onEdit is supplied and the actor can update', () => {
    render(<TagDetailView tag={tag()} onEdit={vi.fn()} />)

    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument()
  })

  it('hides Modifica when onEdit is absent', () => {
    render(<TagDetailView tag={tag()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })

  it('hides Modifica when the actor cannot update the tag', () => {
    render(
      <TagDetailView
        tag={tag({
          permissions: { ...tag().permissions, resource: { ...tag().permissions.resource, update: false } },
        })}
        onEdit={vi.fn()}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })

  it('calls onEdit when Modifica is clicked', () => {
    const onEdit = vi.fn()
    render(<TagDetailView tag={tag()} onEdit={onEdit} />)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(onEdit).toHaveBeenCalledOnce()
  })
})
