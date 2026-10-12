import { useTranslation } from 'react-i18next'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { MigrationSourceStatus } from '@/features/migrations/migration-source-status'
import type { MigrationSourceSummary } from '@/features/migrations/types'

interface MigrationStatusOverviewProps {
  sources: MigrationSourceSummary[]
  selectedSource: string | null
  onSelect: (source: string) => void
  translateLabel: (source: MigrationSourceSummary) => string
}

interface PhaseGroup {
  phase: number | null
  sources: MigrationSourceSummary[]
}

/** Groups the sources by import phase, in phase order; sources outside the order come last. */
function groupByPhase(sources: MigrationSourceSummary[]): PhaseGroup[] {
  const groups = new Map<number | null, MigrationSourceSummary[]>()
  for (const source of sources) {
    groups.set(source.phase, [...(groups.get(source.phase) ?? []), source])
  }

  return [...groups.entries()]
    .sort(([a], [b]) => (a ?? Number.MAX_SAFE_INTEGER) - (b ?? Number.MAX_SAFE_INTEGER))
    .map(([phase, items]) => ({ phase, sources: items }))
}

/** The when-and-how line under a source: its migration time, or its latest run when never migrated. */
function SourceMeta({ source }: { source: MigrationSourceSummary }) {
  const { t } = useTranslation('migrations')
  const failedRows = source.last_run?.status === 'completed' ? source.last_run.failed_rows : 0

  const when = source.migrated_at
    ? t('overview.migratedAt', { date: formatDateTime(source.migrated_at) })
    : source.last_run
      ? t('overview.lastRunAt', { date: formatDateTime(source.last_run.created_at) })
      : null

  if (when === null && failedRows === 0) return null

  return (
    <span className="truncate text-xs text-muted-foreground">
      {[when, failedRows > 0 ? t('overview.failedRows', { count: failedRows }) : null]
        .filter(Boolean)
        .join(' · ')}
    </span>
  )
}

/**
 * Every source with its migration state, grouped by the phase it is imported
 * in, so what is still missing shows at a glance. Selecting a source opens its
 * requirements and template below.
 */
export function MigrationStatusOverview({ sources, selectedSource, onSelect, translateLabel }: MigrationStatusOverviewProps) {
  const { t } = useTranslation('migrations')
  const migratedCount = sources.filter((source) => source.migrated_at !== null).length

  return (
    <Card>
      <CardHeader className="border-b pb-4">
        <CardTitle>{t('overview.title')}</CardTitle>
        <CardDescription>{t('overview.description')}</CardDescription>
        <p className="text-sm font-medium">
          {t('overview.progress', { migrated: migratedCount, total: sources.length })}
        </p>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {groupByPhase(sources).map((group) => (
          <section key={group.phase ?? 'unordered'} className="flex flex-col gap-2">
            <h3 className="text-xs font-semibold uppercase text-muted-foreground">
              {group.phase !== null ? t('overview.phase', { phase: group.phase }) : t('overview.unordered')}
            </h3>
            <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
              {group.sources.map((source) => (
                <li key={source.key} className="min-w-0">
                  <button
                    type="button"
                    aria-pressed={selectedSource === source.key}
                    onClick={() => onSelect(source.key)}
                    className={cn(
                      'flex w-full min-w-0 flex-col gap-1 rounded-md border bg-muted/40 px-2.5 py-1.5 text-left transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                      selectedSource === source.key && 'border-primary ring-1 ring-primary',
                    )}
                  >
                    <span className="flex min-w-0 items-center justify-between gap-2">
                      <span className="truncate text-sm font-medium">{translateLabel(source)}</span>
                      <MigrationSourceStatus source={source} />
                    </span>
                    <SourceMeta source={source} />
                  </button>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </CardContent>
    </Card>
  )
}
