import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRegistry, registryDetailQueryKey } from '@/features/registries/api'
import { RegistryForm } from '@/features/registries/registry-form'
import type { RegistryDetail } from '@/features/registries/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of a registry (spec 0022, replaces the create and
 * edit Sheets). One page serves both routes: `/registries/new` (no `:id`) and
 * `/registries/:id/edit`. In edit mode the fresh, re-authorized detail is
 * fetched before the form mounts, so the partial PATCH starts from
 * authoritative values. `RegistryForm` and its hook/payload are reused as-is.
 */
export default function RegistryFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
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

  const onSuccess = useCallback(
    (saved: RegistryDetail) => {
      queryClient.invalidateQueries({ queryKey: registryDetailQueryKey(saved.id) })
      void navigate(`/registries/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/registries/${registryId}` : '/registries')
  }, [isEdit, navigate, registryId])

  if (isEdit && registryId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'registries.update' : 'registries.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('registries.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        {/* No `bg-card`: the form paints its own `bg-surface` panel and the
            sections inside it are the `bg-card` rung (ui-design.md §1-bis). */}
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          {isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive" role="alert">
                {t('registries.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : isEdit && (isLoading || !registry) ? (
            <RecordFormSkeleton />
          ) : (
            <RegistryForm
              mode={registry ? { type: 'edit', registry } : { type: 'create' }}
              onSuccess={onSuccess}
              onCancel={onCancel}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
