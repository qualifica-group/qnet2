import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchReferent, referentDetailQueryKey } from '@/features/referents/api'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ReferentDetail } from '@/features/referents/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of a referent (spec 0022, replaces the create and
 * edit Sheets). One page serves `/referents/new` and `/referents/:id/edit`;
 * `ReferentForm` and its hook/payload are reused as-is.
 */
export default function ReferentFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
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

  const onSuccess = useCallback(
    (saved: ReferentDetail) => {
      queryClient.invalidateQueries({ queryKey: referentDetailQueryKey(saved.id) })
      void navigate(`/referents/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/referents/${referentId}` : '/referents')
  }, [isEdit, navigate, referentId])

  if (isEdit && referentId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'referents.update' : 'referents.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('referents.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        {/* No `bg-card`: the form paints its own `bg-surface` panel and the
            sections inside it are the `bg-card` rung (ui-design.md §1-bis). */}
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          {isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive" role="alert">
                {t('referents.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : isEdit && (isLoading || !referent) ? (
            <RecordFormSkeleton />
          ) : (
            <ReferentForm
              mode={referent ? { type: 'edit', referent } : { type: 'create' }}
              onSuccess={onSuccess}
              onCancel={onCancel}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
