import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { CostTypeDetailView } from '@/features/cost-types/cost-type-detail'
import type { CostTypeDetailWithPermissions } from '@/features/cost-types/types'

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function costType(overrides: Partial<CostTypeDetailWithPermissions> = {}): CostTypeDetailWithPermissions {
  return {
    id: 4,
    name: 'Collaboratori',
    description: 'External staff',
    sort_order: 1,
    is_active: true,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    abilities: { update: true, delete: true },
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

describe('CostTypeDetailView', () => {
  it('shows name, description, status and both timestamps', () => {
    render(<CostTypeDetailView costType={costType()} />)

    expect(screen.getByRole('heading', { name: 'Collaboratori' })).toBeInTheDocument()
    expect(screen.getByText('External staff')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<CostTypeDetailView costType={costType({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('mounts the activity log only when view_activity is granted', () => {
    const base = costType()
    render(
      <CostTypeDetailView
        costType={costType({ permissions: { ...base.permissions, actions: { view_activity: true } } })}
      />,
    )

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'cost-types', id: 4 })
  })
})
