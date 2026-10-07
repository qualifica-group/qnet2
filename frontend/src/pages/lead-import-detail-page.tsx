import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { Can } from '@/features/auth/can'
import { useLeadImportDetail } from '@/features/imports/use-lead-import-detail'
import { LeadImportDetailView } from '@/features/imports/lead-import-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single lead import run (spec 0034 AC-013):
 * mirrors `ReferentDetailPage` — a fresh, re-authorized fetch on mount, the
 * presentational `LeadImportDetailView`, and a "Resume import" action when
 * the run is still resumable. Gated behind `leads.import` (`<Can>`,
 * UI-only — the backend re-checks the same ability fail-closed, plus the
 * ownership scoping already baked into the wizard endpoints).
 */
export default function LeadImportDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { runId: runIdParam } = useParams()
  const runId = parseEntityId(runIdParam)

  const { run, summary, isLoading, isError, error, refetch, summaryIsLoading, summaryIsError, isResumable } =
    useLeadImportDetail(runId)

  useBreadcrumbTitle(`/imports/${runIdParam}`, run?.original_filename)

  if (runId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission="leads.import"
      fallback={<p className="text-sm text-muted-foreground">{t('leadImports.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <>
              <Button variant="outline" asChild>
                <Link to="/imports">
                  <ArrowLeft aria-hidden="true" />
                  {t('common.back')}
                </Link>
              </Button>
              {isResumable ? (
                <Button onClick={() => void navigate(`/imports/new?runId=${runId}`)}>
                  <RotateCcw aria-hidden="true" />
                  {t('leadImports.detail.resume')}
                </Button>
              ) : null}
            </>
          }
        />

        <div className="flex min-h-0 flex-1 flex-col overflow-y-auto">
          {isError ? (
            <DetailError
              error={error}
              message={t('leadImports.detail.loadError')}
              retryLabel={t('common.retry')}
              onRetry={() => refetch()}
            />
          ) : isLoading || !run ? (
            <DetailLoading />
          ) : (
            <LeadImportDetailView
              run={run}
              summary={summary}
              summaryIsLoading={summaryIsLoading}
              summaryIsError={summaryIsError}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
