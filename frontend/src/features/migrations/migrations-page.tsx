import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Eye, ListOrdered, Play } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
// Side effect: registers the `migrations` i18next namespace (see the module
// doc comment) before this page's own `t()` calls run.
import '@/features/migrations/i18n'
import { ImportDialog } from '@/features/migrations/import-dialog'
import { MassImportDialog } from '@/features/migrations/mass-import-dialog'
import { MigrationPlanPanel } from '@/features/migrations/migration-plan-panel'
import { MigrationPreviewTable } from '@/features/migrations/migration-preview-table'
import { MigrationRequirementsCard } from '@/features/migrations/migration-requirements-card'
import { MigrationStatusOverview } from '@/features/migrations/migration-status-overview'
import { MigrationTemplatePanel } from '@/features/migrations/migration-template-panel'
import type {
  MigrationColumn,
  MigrationDependency,
  MigrationPreviewRow,
  MigrationSourceSummary,
} from '@/features/migrations/types'
import {
  useMigrationColumns,
  useMigrationPreview,
  useMigrationSources,
} from '@/features/migrations/use-migrations'

const FIRST_PAGE = 1
const EMPTY_COLUMNS: MigrationColumn[] = []
const EMPTY_ROWS: MigrationPreviewRow[] = []
const EMPTY_DEPENDENCIES: MigrationDependency[] = []

/**
 * Migrations page (spec 0013, super-admin only, template-first redesign):
 * qnet is the contract owner, so selecting a source's primary view is the
 * EXPECTED field schema (`MigrationTemplatePanel`, no external call) rather
 * than a live external fetch. The read-only external preview and the import
 * flow are both opt-in actions from there: the preview only fires once
 * explicitly requested, and import opens the existing `ImportDialog`. No
 * edit/delete/create control ever appears -- everything here is read-only.
 */
export default function MigrationsPage() {
  const { t } = useTranslation('migrations')
  const [selectedSource, setSelectedSource] = useState<string | null>(null)
  const [page, setPage] = useState(FIRST_PAGE)
  const [previewRequested, setPreviewRequested] = useState(false)
  const [importOpen, setImportOpen] = useState(false)
  const [planOpen, setPlanOpen] = useState(false)
  const [massImportOpen, setMassImportOpen] = useState(false)

  const sourcesQuery = useMigrationSources()
  const columnsQuery = useMigrationColumns(selectedSource)
  const previewQuery = useMigrationPreview(selectedSource, page, undefined, previewRequested)

  const handleSourceChange = (next: string) => {
    setSelectedSource(next)
    setPage(FIRST_PAGE)
    setPreviewRequested(false)
  }

  const sources = sourcesQuery.data ?? []
  // Source names are user-facing: translate the backend key via i18n, falling
  // back to the backend's English label when a key has no translation.
  const translateSourceLabel = (key: string, fallback: string) =>
    t(`sources.${key}`, { defaultValue: fallback })
  const labelOfKey = (key: string) =>
    translateSourceLabel(key, sources.find((source) => source.key === key)?.label ?? key)
  const labelOfSource = (source: MigrationSourceSummary) => translateSourceLabel(source.key, source.label)
  const selectedSourceMatch = sources.find((source) => source.key === selectedSource)
  const selectedSourceLabel = selectedSourceMatch
    ? labelOfSource(selectedSourceMatch)
    : (selectedSource ?? '')

  const pagination = previewQuery.data?.pagination
  const isPreviewLoading = columnsQuery.isLoading || previewQuery.isLoading
  const isPreviewError = columnsQuery.isError || previewQuery.isError

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeader />

      <Card>
        <CardContent className="flex flex-col gap-4">
          {/* Mass import (spec 0046): configure the ordered plan once, then run
              every enabled source in one click. The single-source select below
              stays for manual, one-at-a-time imports (spec 0013). */}
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" variant="secondary" size="sm" onClick={() => setPlanOpen(true)}>
              <ListOrdered aria-hidden="true" />
              {t('plan.configureButton')}
            </Button>
            <Button type="button" size="sm" onClick={() => setMassImportOpen(true)}>
              <Play aria-hidden="true" />
              {t('massImport.button')}
            </Button>
          </div>

          <div className="flex flex-col gap-1.5 sm:w-64">
            <Label htmlFor="migration-source">{t('page.sourceLabel')}</Label>
            {sourcesQuery.isLoading ? (
              <Skeleton className="h-9 w-full" />
            ) : sourcesQuery.isError ? (
              <p className="text-sm text-destructive" role="alert">
                {t('page.sourcesLoadError')}
              </p>
            ) : sources.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('page.sourcesEmpty')}</p>
            ) : (
              <Select value={selectedSource ?? undefined} onValueChange={handleSourceChange}>
                <SelectTrigger id="migration-source" className="w-full">
                  <SelectValue placeholder={t('page.sourcePlaceholder')} />
                </SelectTrigger>
                <SelectContent>
                  {sources.map((source) => (
                    <SelectItem key={source.key} value={source.key}>
                      {labelOfSource(source)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>
        </CardContent>
      </Card>

      {sources.length > 0 ? (
        <MigrationStatusOverview
          sources={sources}
          selectedSource={selectedSource}
          onSelect={handleSourceChange}
          translateLabel={labelOfSource}
        />
      ) : null}

      {selectedSource ? (
        <MigrationRequirementsCard
          source={selectedSource}
          dependencies={columnsQuery.data?.dependencies ?? EMPTY_DEPENDENCIES}
          sources={sources}
          isLoading={columnsQuery.isLoading}
          translateLabel={labelOfKey}
        />
      ) : null}

      {selectedSource ? (
        <MigrationTemplatePanel
          sourceLabel={selectedSourceLabel}
          columns={columnsQuery.data?.columns ?? EMPTY_COLUMNS}
          request={columnsQuery.data?.request}
          sample={columnsQuery.data?.sample}
          isLoading={columnsQuery.isLoading}
          isError={columnsQuery.isError}
          onImportClick={() => setImportOpen(true)}
        />
      ) : null}

      {selectedSource && !previewRequested ? (
        <div>
          <Button type="button" variant="outline" onClick={() => setPreviewRequested(true)}>
            <Eye aria-hidden="true" />
            {t('preview.showButton')}
          </Button>
        </div>
      ) : null}

      {selectedSource && previewRequested ? (
        <Card>
          <CardContent className="flex flex-col gap-4">
            <MigrationPreviewTable
              columns={columnsQuery.data?.columns ?? EMPTY_COLUMNS}
              rows={previewQuery.data?.rows ?? EMPTY_ROWS}
              isLoading={isPreviewLoading}
              isError={isPreviewError}
            />

            {pagination ? (
              <div className="flex items-center justify-between gap-2">
                <span className="text-sm text-muted-foreground">
                  {t('page.pageIndicator', { page: pagination.page })}
                </span>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((current) => Math.max(FIRST_PAGE, current - 1))}
                    disabled={pagination.page <= FIRST_PAGE}
                  >
                    <ChevronLeft aria-hidden="true" />
                    {t('page.previous')}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((current) => current + 1)}
                    disabled={!pagination.has_more}
                  >
                    {t('page.next')}
                    <ChevronRight aria-hidden="true" />
                  </Button>
                </div>
              </div>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      {selectedSource ? (
        <ImportDialog
          source={selectedSource}
          sourceLabel={selectedSourceLabel}
          open={importOpen}
          onOpenChange={setImportOpen}
        />
      ) : null}

      <MigrationPlanPanel open={planOpen} onOpenChange={setPlanOpen} />
      <MassImportDialog open={massImportOpen} onOpenChange={setMassImportOpen} />
    </div>
  )
}
