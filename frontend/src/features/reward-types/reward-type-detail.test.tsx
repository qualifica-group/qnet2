import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { RewardTypeDetailView } from '@/features/reward-types/reward-type-detail'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'

/**
 * Spec 0058, AC-024: the detail shows name, color preview, both timestamps
 * and the Activity Log section. `RewardTypeDetailView` is purely
 * presentational (the caller fetches and passes the detail down), so the
 * section gate is exercised directly on the `permissions.actions.view_activity`
 * prop (mirrors `TagDetailView`'s suite).
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function rewardType(
  overrides: Partial<RewardTypeDetailWithPermissions> = {},
): RewardTypeDetailWithPermissions {
  return {
    id: 1,
    name: 'Amazon voucher',
    color: 'blue',
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

describe('RewardTypeDetailView — detail fields (AC-024)', () => {
  it('shows the name, the localized color preview and both timestamps', () => {
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    expect(screen.getByRole('heading', { name: 'Amazon voucher' })).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })
})

describe('RewardTypeDetailView — activity log section', () => {
  it('mounts the section for the viewed reward type when view_activity is granted', () => {
    render(
      <RewardTypeDetailView
        rewardType={rewardType({ permissions: { ...rewardType().permissions, actions: { view_activity: true } } })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'reward-types', id: 1 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(
      <RewardTypeDetailView
        rewardType={rewardType({ permissions: { ...rewardType().permissions, actions: { view_activity: false } } })}
      />,
    )

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
