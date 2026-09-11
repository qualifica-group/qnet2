import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRegistry, registryDetailQueryKey } from '@/features/registries/api'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single registry (spec 0022, replaces the view
 * Sheet). Fetches the fresh, re-authorized detail on mount — same query key as
 * before — and renders the unchanged presentational `RegistryDetailView`. The
 * "Edit" action is gated by the `permissions` block of THIS response, not by a
 * static ability: the backend remains the authority.
 */
export default function RegistryDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const registryId = parseEntityId(id)

  const {
    data: registry,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    registryDetailQueryKey(registryId),
    () => fetchRegistry(registryId as number),
    registryId !== null,
  )

  useBreadcrumbTitle(`/registries/${id}`, registry?.name)

  if (registryId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/registries">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {registry?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/registries/${registryId}/edit`}>
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
            message={t('registries.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !registry ? (
          <DetailLoading />
        ) : (
          <RegistryDetailView registry={registry} />
        )}
      </div>
    </div>
  )
}
