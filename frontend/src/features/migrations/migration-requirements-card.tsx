import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { MigrationSourceStatus } from '@/features/migrations/migration-source-status'
import type { MigrationDependency, MigrationSourceSummary } from '@/features/migrations/types'

interface MigrationRequirementsCardProps {
  source: string
  dependencies: MigrationDependency[]
  sources: MigrationSourceSummary[]
  isLoading: boolean
  translateLabel: (key: string) => string
}

/**
 * "What it takes to migrate well" for the selected source: its prerequisites
 * in words, the sources to import first (required or recommended) with their
 * migration state, and an alert naming the required ones still missing.
 */
export function MigrationRequirementsCard({
  source,
  dependencies,
  sources,
  isLoading,
  translateLabel,
}: MigrationRequirementsCardProps) {
  const { t } = useTranslation('migrations')
  const dependenciesTitleId = useId()
  const summaries = new Map(sources.map((summary) => [summary.key, summary]))
  const description = t(`sourceRequirements.${source}`, { defaultValue: '' })
  const missingRequired = dependencies
    .filter((dependency) => dependency.required && !summaries.get(dependency.source)?.migrated_at)
    .map((dependency) => translateLabel(dependency.source))

  return (
    <Card>
      <CardHeader className="border-b pb-4">
        <CardTitle>{t('requirements.title')}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {description ? <p className="text-sm">{description}</p> : null}

        {isLoading ? (
          <Skeleton className="h-8 w-full" />
        ) : (
          <>
            {missingRequired.length > 0 ? (
              <p className="text-sm text-destructive" role="alert">
                {t('requirements.missingRequired', { sources: missingRequired.join(', ') })}
              </p>
            ) : null}

            <div className="flex flex-col gap-2">
              <h3 id={dependenciesTitleId} className="text-sm font-semibold">
                {t('requirements.dependenciesTitle')}
              </h3>
              {dependencies.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('requirements.none')}</p>
              ) : (
                <ul aria-labelledby={dependenciesTitleId} className="flex flex-col divide-y rounded-md border">
                  {dependencies.map((dependency) => {
                    const summary = summaries.get(dependency.source)
                    return (
                      <li
                        key={dependency.source}
                        className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                      >
                        <span className="flex min-w-0 items-center gap-2">
                          <span className="truncate text-sm">{translateLabel(dependency.source)}</span>
                          <Badge variant={dependency.required ? 'secondary' : 'outline'}>
                            {dependency.required ? t('requirements.required') : t('requirements.optional')}
                          </Badge>
                        </span>
                        {summary ? <MigrationSourceStatus source={summary} /> : null}
                      </li>
                    )
                  })}
                </ul>
              )}
              {dependencies.length > 0 ? (
                <p className="text-xs text-muted-foreground">{t('requirements.legend')}</p>
              ) : null}
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
