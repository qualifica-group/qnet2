import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { RewardStatusDetailView } from '@/features/reward-statuses/reward-status-detail'
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'

/**
 * The detail shows the record's fields; every row but the server-managed order
 * edits in place (spec 0235), each save a PATCH of that field alone. The
 * Activity Log gate is exercised on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updateRewardStatusMock = vi.fn()

vi.mock('@/features/reward-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/reward-statuses/api')>()),
  updateRewardStatus: (...args: unknown[]) => updateRewardStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function rewardStatus(overrides: Partial<RewardStatusDetailWithPermissions> = {}): RewardStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Granted',
    description: 'Voucher handed over',
    color: 'blue',
    group: 'pending',
    sort_order: 3,
    is_active: true,
    system_key: null,
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
  updateRewardStatusMock.mockReset()
})

describe('RewardStatusDetailView — detail fields', () => {
  it('shows the fields and the timestamps', () => {
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    expect(screen.getByRole('heading', { name: 'Granted' })).toBeInTheDocument()
    expect(screen.getByText('Voucher handed over')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('Pending')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })
})

describe('RewardStatusDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <RewardStatusDetailView
        rewardStatus={rewardStatus({
          permissions: { ...rewardStatus().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'reward-statuses', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('RewardStatusDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('rewardStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('rewardStatuses.form.isActive')).toBeInTheDocument()
    expect(pencilOf('rewardStatuses.form.group.label')).toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updateRewardStatusMock.mockResolvedValue(rewardStatus({ name: 'Renamed' }))
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    fireEvent.click(pencilOf('rewardStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateRewardStatusMock).toHaveBeenCalledWith(7, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    fireEvent.click(pencilOf('rewardStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateRewardStatusMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateRewardStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    fireEvent.click(pencilOf('rewardStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = rewardStatus()
    render(
      <RewardStatusDetailView
        rewardStatus={rewardStatus({
          permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } },
        })}
      />,
    )

    expect(pencilOf('rewardStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('rewardStatuses.form.color')).not.toBeInTheDocument()
  })

  it('Save sends only the newly picked group', async () => {
    updateRewardStatusMock.mockResolvedValue(rewardStatus({ group: 'closed_won' }))
    render(<RewardStatusDetailView rewardStatus={rewardStatus()} />)

    fireEvent.click(pencilOf('rewardStatuses.form.group.label') as HTMLElement)
    fireEvent.click(screen.getByRole('combobox', { name: 'Group' }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('rewardStatuses.form.group.closed_won') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateRewardStatusMock).toHaveBeenCalledWith(7, { group: 'closed_won' }))
  })

  it('keeps description, group and is_active fixed on a system row; name and color still edit', () => {
    render(<RewardStatusDetailView rewardStatus={rewardStatus({ system_key: 'won' })} />)

    expect(pencilOf('rewardStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('rewardStatuses.form.color')).toBeInTheDocument()
    for (const key of ['description', 'group.label', 'isActive']) {
      expect(pencilOf(`rewardStatuses.form.${key}`)).not.toBeInTheDocument()
    }
  })
})
