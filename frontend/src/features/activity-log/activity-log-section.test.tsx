import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { ActivityLogEntry, ActivityLogPage } from '@/features/activity-log/types'

/**
 * Spec 0034, AC-014: the timeline shows date/time, author, operation type,
 * module and the field-level diff for every entry; loads more pages on
 * demand; covers the loading/empty/error states.
 *
 * Requirement change (event filter, spec 0034 follow-up AC-024): the timeline
 * is preceded by an "All / Creations only / Changes only" strip whose value is
 * sent to the backend as the `event` query param, so `fetchActivityLog` now
 * carries a fifth argument.
 *
 * Requirement change (backend now resolves FK labels, spec 0034 follow-up):
 * `ActivityLogChange` grew `old_display`/`new_display`, and the diff row no
 * longer shows a "— →" for `created`/`deleted` entries — the field-label
 * assertion also moved from the raw key to its (now translated) label.
 */

const fetchActivityLogMock = vi.fn()

vi.mock('@/features/activity-log/api', () => ({
  ACTIVITY_LOG_DEFAULT_PAGE_SIZE: 25,
  fetchActivityLog: (...args: unknown[]) => fetchActivityLogMock(...args),
}))

function entry(overrides: Partial<ActivityLogEntry> = {}): ActivityLogEntry {
  return {
    id: 1,
    logged_at: '2026-07-16T10:30:00.000Z',
    event: 'updated',
    module: 'user',
    subject_id: 1,
    causer: { id: 9, name: 'Jane Doe' },
    changes: [
      {
        field: 'email',
        old_value: 'old@example.com',
        new_value: 'new@example.com',
        old_display: null,
        new_display: null,
      },
    ],
    ...overrides,
  }
}

function page(overrides: Partial<ActivityLogPage> = {}): ActivityLogPage {
  return { items: [entry()], next_cursor: null, ...overrides }
}

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ActivityLogSection resource="users" id={1} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchActivityLogMock.mockReset()
})

describe('ActivityLogSection', () => {
  it('renders logged_at, causer, event, module and the field diff for each entry', async () => {
    fetchActivityLogMock.mockResolvedValue(page())

    renderSection()

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.getByText('Updated')).toBeInTheDocument()
    expect(screen.getByText('User')).toBeInTheDocument()
    expect(screen.getByText('Email')).toBeInTheDocument()
    expect(screen.getByText(/old@example.com/)).toBeInTheDocument()
    expect(screen.getByText(/new@example.com/)).toBeInTheDocument()
  })

  it('shows the resolved FK label instead of the raw id when the backend provides one', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({
        items: [
          entry({
            changes: [
              {
                field: 'registry_id',
                old_value: 3,
                new_value: 8,
                old_display: 'Acme Inc.',
                new_display: 'Globex Corp.',
              },
            ],
          }),
        ],
      }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('Client')).toBeInTheDocument())
    expect(screen.getByText('Acme Inc.')).toBeInTheDocument()
    expect(screen.getByText('Globex Corp.')).toBeInTheDocument()
    expect(screen.queryByText('3')).not.toBeInTheDocument()
    expect(screen.queryByText('8')).not.toBeInTheDocument()
  })

  it('falls back to the raw value when the backend resolved no display label', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({
        items: [
          entry({
            changes: [{ field: 'cost', old_value: 10, new_value: 12, old_display: null, new_display: null }],
          }),
        ],
      }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('10')).toBeInTheDocument())
    expect(screen.getByText('12')).toBeInTheDocument()
  })

  it('shows only the new value for a created entry, with no old value or arrow', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({
        items: [
          entry({
            event: 'created',
            changes: [{ field: 'name', old_value: null, new_value: 'Acme Inc.', old_display: null, new_display: null }],
          }),
        ],
      }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('Acme Inc.')).toBeInTheDocument())
    expect(screen.queryByText('—')).not.toBeInTheDocument()
  })

  it('shows only the struck-through old value for a deleted entry', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({
        items: [
          entry({
            event: 'deleted',
            changes: [{ field: 'name', old_value: 'Acme Inc.', new_value: null, old_display: null, new_display: null }],
          }),
        ],
      }),
    )

    renderSection()

    const oldValue = await screen.findByText('Acme Inc.')
    expect(oldValue).toHaveClass('line-through')
  })

  it('falls back to a humanized field key when no translation exists for the field', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({
        items: [
          entry({
            changes: [
              { field: 'unmapped_custom_column', old_value: 'a', new_value: 'b', old_display: null, new_display: null },
            ],
          }),
        ],
      }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('Unmapped custom column')).toBeInTheDocument())
  })

  it('falls back to the system-causer label when causer is null', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({ items: [entry({ causer: { id: null, name: null } })] }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('System')).toBeInTheDocument())
  })

  it('shows the empty state when there are no entries', async () => {
    fetchActivityLogMock.mockResolvedValue(page({ items: [] }))

    renderSection()

    await waitFor(() =>
      expect(screen.getByText('No activity recorded yet.')).toBeInTheDocument(),
    )
  })

  it('requests the unfiltered feed by default and narrows it on filter change (AC-024)', async () => {
    fetchActivityLogMock.mockResolvedValue(page())

    renderSection()

    await waitFor(() => expect(fetchActivityLogMock).toHaveBeenCalledWith('users', 1, null, 25, 'all'))

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Changes only' }))

    await waitFor(() =>
      expect(fetchActivityLogMock).toHaveBeenLastCalledWith('users', 1, null, 25, 'updated'),
    )

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Creations only' }))

    await waitFor(() =>
      expect(fetchActivityLogMock).toHaveBeenLastCalledWith('users', 1, null, 25, 'created'),
    )
  })

  it('keeps the filter reachable when the filtered feed is empty (AC-024)', async () => {
    fetchActivityLogMock.mockResolvedValueOnce(page())
    fetchActivityLogMock.mockResolvedValueOnce(page({ items: [] }))

    renderSection()

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Creations only' }))

    await waitFor(() =>
      expect(screen.getByText('No activity matches the selected filter.')).toBeInTheDocument(),
    )
    expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
  })

  it('shows the error state with a retry action', async () => {
    fetchActivityLogMock.mockRejectedValue(new Error('network error'))

    renderSection()

    await waitFor(() =>
      expect(
        screen.getByText('Unable to load the activity log. Please try again.'),
      ).toBeInTheDocument(),
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('requests the next page when "Load more" is clicked', async () => {
    fetchActivityLogMock.mockResolvedValueOnce(
      page({ items: [entry({ id: 1 })], next_cursor: 'cursor-2' }),
    )
    fetchActivityLogMock.mockResolvedValueOnce(
      page({ items: [entry({ id: 2, causer: { id: null, name: null } })], next_cursor: null }),
    )

    renderSection()

    const loadMore = await screen.findByRole('button', { name: 'Load more' })
    loadMore.click()

    await waitFor(() => expect(fetchActivityLogMock).toHaveBeenCalledTimes(2))
    expect(fetchActivityLogMock).toHaveBeenLastCalledWith('users', 1, 'cursor-2', 25, 'all')
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument(),
    )
  })
})
