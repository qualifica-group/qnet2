import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { RewardTypeDetailView } from '@/features/reward-types/reward-type-detail'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'

/**
 * Spec 0058, AC-024: the detail shows name, color preview, both timestamps
 * and the Activity Log section. `RewardTypeDetailView` is purely
 * presentational (the caller fetches and passes the detail down), so the
 * section gate is exercised directly on the `permissions.actions.view_activity`
 * prop (mirrors `TagDetailView`'s suite). Spec 0235: name and color edit in
 * place, each save a PATCH of that field alone.
 */

const activityLogSectionMock = vi.fn()
const updateRewardTypeMock = vi.fn()

vi.mock('@/features/reward-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/reward-types/api')>()),
  updateRewardType: (...args: unknown[]) => updateRewardTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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
  updateRewardTypeMock.mockReset()
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

describe('RewardTypeDetailView — in-place editing (spec 0235)', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('rewardTypes.form.name')).toBeInTheDocument()
    expect(pencilOf('rewardTypes.form.color')).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateRewardTypeMock.mockResolvedValue(rewardType({ name: 'Renamed' }))
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    fireEvent.click(pencilOf('rewardTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateRewardTypeMock).toHaveBeenCalledWith(1, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    fireEvent.click(pencilOf('rewardTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-017 (D-5b): clearing the color with the picker "X" is refused on the row, without a request', async () => {
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    fireEvent.click(pencilOf('rewardTypes.form.color') as HTMLElement)
    fireEvent.click(screen.getByRole('button', { name: 'Clear color' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Color is required.')).toBeInTheDocument()
    expect(updateRewardTypeMock).not.toHaveBeenCalled()
  })

  it('AC-016: maps a server 422 onto the open row', async () => {
    updateRewardTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<RewardTypeDetailView rewardType={rewardType()} />)

    fireEvent.click(pencilOf('rewardTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when the actor cannot update the reward type', () => {
    render(
      <RewardTypeDetailView
        rewardType={rewardType({
          permissions: {
            ...rewardType().permissions,
            resource: { ...rewardType().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('rewardTypes.form.name')).not.toBeInTheDocument()
    expect(pencilOf('rewardTypes.form.color')).not.toBeInTheDocument()
  })
})
