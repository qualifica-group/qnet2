import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
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
 * Dedicated page of a single registry (spec 0022, replaces the view Sheet).
 * Fetches the fresh, re-authorized detail on mount and renders
 * `RegistryDetailView`, which edits its fields in place (spec 0200) under the
 * `permissions` block of THIS response, not a static ability: the backend
 * remains the authority. There is no edit page.
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
          <Button variant="outline" asChild>
            <Link to="/registries">
              <ArrowLeft aria-hidden="true" />
              {t('common.back')}
            </Link>
          </Button>
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
