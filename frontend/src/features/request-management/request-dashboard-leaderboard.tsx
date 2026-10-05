import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, ArrowUpDown, Trophy } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { UserAvatar } from '@/components/user-avatar'
import { toPercentage } from '@/components/ui/stat-chart-tokens'
import {
  FALLBACK_INDICATOR_COLOR,
  INDICATOR_COLORS,
  indicatorTint,
} from '@/features/request-management/dashboard-indicator-meta'
import {
  defaultLeaderboardSort,
  LEADERBOARD_LABEL_SORT,
  type Leaderboard,
  type LeaderboardSort,
  sortLeaderboard,
} from '@/features/request-management/dashboard-leaderboard'
import { cn } from '@/lib/utils'

/** Places that get a podium badge (D-3). */
const PODIUM_SIZE = 3
const PODIUM_CLASS = ['bg-warning/15 text-warning', 'bg-muted text-foreground', 'bg-chart-1/15 text-chart-1'] as const

const ROW_CLASS = 'group border-b last:border-b-0 hover:bg-muted/40'
/**
 * The sticky name cell needs an OPAQUE fill to cover the columns scrolling
 * under it, so the row's translucent hover tint cannot show through it: on
 * hover it takes the same tint pre-mixed over the card, matching the row.
 */
const STICKY_ROW_HEADER_CLASS =
  'sticky left-0 z-10 bg-card px-2 py-1.5 text-left font-medium group-hover:bg-[color-mix(in_oklab,var(--muted)_40%,var(--card))]'

const HEADER_BUTTON_CLASS =
  'inline-flex max-w-full items-center gap-1 rounded-md px-1 py-0.5 outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50'

function ariaSort(sort: LeaderboardSort, key: string): 'ascending' | 'descending' | 'none' {
  if (sort.key !== key) {
    return 'none'
  }

  return sort.direction === 'asc' ? 'ascending' : 'descending'
}

function SortIcon({ sort, columnKey }: { sort: LeaderboardSort; columnKey: string }) {
  const Icon = sort.key !== columnKey ? ArrowUpDown : sort.direction === 'asc' ? ArrowUp : ArrowDown

  return <Icon aria-hidden="true" className={cn('size-3 shrink-0', sort.key !== columnKey && 'opacity-40')} />
}

/** Rank cell: a podium badge for the top three of a by-value ranking (never for a 0), a plain number otherwise. */
function RankBadge({ rank, podium }: { rank: number | null; podium: boolean }) {
  if (rank === null) {
    return <span className="text-muted-foreground">—</span>
  }
  if (!podium || rank > PODIUM_SIZE) {
    return <span className="tabular-nums text-muted-foreground">{rank}</span>
  }

  return (
    <span
      className={cn(
        'inline-flex size-6 items-center justify-center gap-0.5 rounded-full text-xs font-semibold tabular-nums',
        PODIUM_CLASS[rank - 1],
      )}
    >
      {rank === 1 ? <Trophy aria-hidden="true" className="size-3" /> : rank}
      {rank === 1 ? <span className="sr-only">1</span> : null}
    </span>
  )
}

export interface DashboardLeaderboardProps {
  leaderboard: Leaderboard
}

/**
 * The operator ranking of one category (spec 0192 D-3): the former
 * per-indicator operator charts as ONE sortable table. Every indicator is a
 * column with an inline bar against its own maximum; the heading button
 * sorts (descending first, again to flip) and exposes `aria-sort`. "Non
 * assegnato" is listed last and never ranked.
 */
export function DashboardLeaderboard({ leaderboard }: DashboardLeaderboardProps) {
  const { t } = useTranslation()
  const [sort, setSort] = useState<LeaderboardSort>(() => defaultLeaderboardSort(leaderboard))
  const rows = sortLeaderboard(leaderboard.rows, sort)
  const podium = sort.key !== LEADERBOARD_LABEL_SORT && sort.direction === 'desc'

  const toggleSort = (key: string) =>
    setSort((current) =>
      current.key === key
        ? { key, direction: current.direction === 'desc' ? 'asc' : 'desc' }
        : { key, direction: key === LEADERBOARD_LABEL_SORT ? 'asc' : 'desc' },
    )

  return (
    <Card className="min-w-0 gap-3 py-3">
      <div className="flex items-center gap-2 px-3">
        <Trophy aria-hidden="true" className="size-3.5 text-warning" />
        <h3 className="text-sm font-semibold">{t('requestManagement.dashboard.leaderboard.title')}</h3>
      </div>
      <div className="w-0 min-w-full overflow-x-auto">
        <table className="w-full text-xs">
          <caption className="sr-only">{t('requestManagement.dashboard.leaderboard.title')}</caption>
          <thead>
            <tr className="border-b text-muted-foreground">
              <th scope="col" className="w-10 px-3 py-1.5 text-left font-medium">
                {t('requestManagement.dashboard.leaderboard.rank')}
              </th>
              <th
                scope="col"
                aria-sort={ariaSort(sort, LEADERBOARD_LABEL_SORT)}
                className="sticky left-0 z-10 bg-card px-2 py-1.5 text-left font-medium"
              >
                <button type="button" className={HEADER_BUTTON_CLASS} onClick={() => toggleSort(LEADERBOARD_LABEL_SORT)}>
                  {t('requestManagement.dashboard.leaderboard.operator')}
                  <SortIcon sort={sort} columnKey={LEADERBOARD_LABEL_SORT} />
                </button>
              </th>
              {leaderboard.columns.map((column) => (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={ariaSort(sort, column.key)}
                  className="min-w-24 px-2 py-1.5 text-right font-medium"
                >
                  <button
                    type="button"
                    title={column.label}
                    className={cn(HEADER_BUTTON_CLASS, 'ml-auto', sort.key === column.key && 'text-foreground')}
                    onClick={() => toggleSort(column.key)}
                  >
                    <span
                      aria-hidden="true"
                      className="size-2 shrink-0 rounded-full"
                      style={{ backgroundColor: INDICATOR_COLORS[column.key] ?? FALLBACK_INDICATOR_COLOR }}
                    />
                    <span className="line-clamp-2 text-right">{column.label}</span>
                    <SortIcon sort={sort} columnKey={column.key} />
                  </button>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => {
              // "Non assegnato" always sorts last, so the index IS the rank of every other row.
              return (
                <tr key={row.label} className={ROW_CLASS}>
                  <td className="px-3 py-1.5">
                    <RankBadge
                      rank={row.unassigned ? null : index + 1}
                      podium={podium && (row.values[sort.key] ?? 0) > 0}
                    />
                  </td>
                  <th scope="row" className={STICKY_ROW_HEADER_CLASS}>
                    <span className="flex min-w-36 items-center gap-2">
                      <UserAvatar name={row.label} size="sm" />
                      <span className={cn('truncate', row.unassigned && 'text-muted-foreground italic')}>
                        {row.label}
                      </span>
                    </span>
                  </th>
                  {leaderboard.columns.map((column) => {
                    const value = row.values[column.key] ?? 0
                    const color = INDICATOR_COLORS[column.key] ?? FALLBACK_INDICATOR_COLOR

                    return (
                      <td key={column.key} className="px-2 py-1.5 text-right">
                        {/* Tinted pill, foreground text: a zero stays plain and muted so the eye lands on real numbers. */}
                        <span
                          className={cn(
                            'inline-block min-w-7 rounded-md px-1.5 py-0.5 text-center tabular-nums',
                            value > 0 ? 'font-semibold text-foreground' : 'text-muted-foreground',
                          )}
                          style={value > 0 ? { backgroundColor: indicatorTint(color) } : undefined}
                        >
                          {value.toLocaleString()}
                        </span>
                        <span aria-hidden="true" className="mt-1 ml-auto block h-1 w-full max-w-24 overflow-hidden rounded-full bg-muted">
                          <span
                            className={cn('block h-full rounded-full', sort.key === column.key ? null : 'opacity-60')}
                            style={{ width: `${toPercentage(value, column.max)}%`, backgroundColor: color }}
                          />
                        </span>
                      </td>
                    )
                  })}
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
