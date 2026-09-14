/**
 * Day list section of the dashboard (spec 0122 D-1/AC-035): sorting control,
 * empty state, skeletons, one `TimeEntryDayCard` per day, and the
 * infinite-scroll sentinel. Purely presentational — the caller (MT-F7's page)
 * owns the `useTimeEntriesDays` query and passes its result down, the same
 * split `features/notifications/notification-list.tsx` already uses.
 */

import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import {
  TimeEntryDayCard,
  TimeEntryDayCardSkeleton,
} from '@/features/time-entries/days/time-entry-day-card'
import { TimeEntriesSortingDialog } from '@/features/time-entries/days/time-entries-sorting-dialog'
import { getTodayDateKey } from '@/features/time-entries/time-entry-period'
import type { DaySummary, TimeEntriesSortBy } from '@/features/time-entries/types'

/** How many skeleton cards stand in for the first page while it loads. */
const SKELETON_CARD_COUNT = 3

/** Distance (px) before the sentinel reaching the viewport that triggers the next page. */
const INFINITE_SCROLL_ROOT_MARGIN = '120px'

interface TimeEntriesDayListProps {
  days: DaySummary[]
  isLoading: boolean
  isError: boolean
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
  sortBy: TimeEntriesSortBy
  sortDirection: 'asc' | 'desc'
  onSortChange: (sortBy: TimeEntriesSortBy, sortDirection: 'asc' | 'desc') => void
  /** `meta.can_write` of the loaded list (D-8): selected user is self, or the actor has `manageAll`. */
  canWrite: boolean
  /** The dashboard's selected user (D-8), forwarded to every day note's payload. */
  selectedUserId?: number
  onEditEntry: (entryId: number) => void
  onCreateForDate: (date: string) => void
}

export function TimeEntriesDayList({
  days,
  isLoading,
  isError,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
  sortBy,
  sortDirection,
  onSortChange,
  canWrite,
  selectedUserId,
  onEditEntry,
  onCreateForDate,
}: TimeEntriesDayListProps) {
  const { t } = useTranslation()
  const todayDateKey = getTodayDateKey()
  const [expandedDates, setExpandedDates] = useState<Record<string, boolean>>({})
  const sentinelRef = useRef<HTMLDivElement>(null)

  // Loads the next page once the bottom sentinel scrolls into the viewport
  // (page-level scroll, hence `root: null`), same convention as
  // `notification-list.tsx`'s panel-scoped observer.
  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel || !hasNextPage) {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          onLoadMore()
        }
      },
      { root: null, rootMargin: INFINITE_SCROLL_ROOT_MARGIN },
    )
    observer.observe(sentinel)

    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, onLoadMore])

  const toggleDay = (date: string) => {
    setExpandedDates((current) => ({ ...current, [date]: !current[date] }))
  }

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <TimeEntriesSortingDialog onChange={onSortChange} sortBy={sortBy} sortDirection={sortDirection} />
      </div>

      {isLoading ? (
        <div className="space-y-4">
          {Array.from({ length: SKELETON_CARD_COUNT }).map((_, index) => (
            <TimeEntryDayCardSkeleton key={index} />
          ))}
        </div>
      ) : null}

      {!isLoading && isError ? (
        <p className="rounded-2xl border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground">
          {t('timeEntries.page.loadError')}
        </p>
      ) : null}

      {!isLoading && !isError && days.length === 0 ? (
        <p className="rounded-2xl border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground">
          {t('timeEntries.dayCard.noEntries')}
        </p>
      ) : null}

      {!isLoading && !isError && days.length > 0 ? (
        <div className="space-y-4">
          {days.map((day) => {
            const isToday = day.date === todayDateKey
            return (
              <TimeEntryDayCard
                canWrite={canWrite}
                day={day}
                isExpanded={isToday || expandedDates[day.date] === true}
                isToday={isToday}
                key={day.date}
                onCreateForDate={onCreateForDate}
                onEditEntry={onEditEntry}
                onToggle={() => toggleDay(day.date)}
                selectedUserId={selectedUserId}
              />
            )
          })}
          {hasNextPage ? (
            <div ref={sentinelRef}>{isFetchingNextPage ? <TimeEntryDayCardSkeleton /> : null}</div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
