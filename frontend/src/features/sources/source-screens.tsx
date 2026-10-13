/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchSource, sourceDetailQueryKey } from '@/features/sources/api'
import { SourceForm } from '@/features/sources/source-form'
import { SourceDetailView } from '@/features/sources/source-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { SourceDetail } from '@/features/sources/types'

/**
 * Content-only `sources` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function SourceDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(sourceDetailQueryKey(id), () => fetchSource(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('sources.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !source) {
    return <DetailLoading />
  }

  return <SourceDetailView source={source} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a source being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function SourceFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('sources.form.leaveConfirm.title'),
    description: t('sources.form.leaveConfirm.description'),
    confirmLabel: t('sources.form.leaveConfirm.confirm'),
    cancelLabel: t('sources.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: SourceDetail) => {
    queryClient.invalidateQueries({ queryKey: sourceDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <SourceForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'sources',
  basePath: '/sources',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.sources',
  DetailScreen: SourceDetailScreen,
  FormScreen: SourceFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
