import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchReferent, referentDetailQueryKey } from '@/features/referents/api'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single referent (spec 0022, replaces the view
 * Sheet). Mirrors `RegistryDetailPage`: fresh re-authorized fetch on mount,
 * unchanged presentational `ReferentDetailView`, "Edit" gated by the
 * `permissions` block of THIS response.
 */
export default function ReferentDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const referentId = parseEntityId(id)

  const {
    data: referent,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    referentDetailQueryKey(referentId),
    () => fetchReferent(referentId as number),
    referentId !== null,
  )

  useBreadcrumbTitle(`/referents/${id}`, referent?.name)

  if (referentId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/referents">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {referent?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/referents/${referentId}/edit`}>
                  <Pencil aria-hidden="true" />
                  {t('common.edit')}
                </Link>
              </Button>
            ) : null}
          </>
        }
      />

      {/* No `bg-card` here: the record canvas paints its own `bg-surface`
          and the cards INSIDE it are the `bg-card` rung. A card wrapper would
          be the very surface those cards lie on (ui-design.md §1-bis). */}
      <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
        {isError ? (
          <DetailError
            message={t('referents.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !referent ? (
          <DetailLoading />
        ) : (
          <ReferentDetailView referent={referent} />
        )}
      </div>
    </div>
  )
}
