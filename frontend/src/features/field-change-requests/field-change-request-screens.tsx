/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchFieldChangeRequest } from '@/features/field-change-requests/api'
import { fieldChangeRequestKeys } from '@/features/field-change-requests/query-keys'
import { FieldChangeRequestDetailView } from '@/features/field-change-requests/field-change-request-detail'
import type {
  ModuleDetailScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

/**
 * Content-only `field-change-requests` screen for the module registry (spec
 * 0042/0078). `onEdit` is unused: a request has no edit surface — it is
 * proposed via the generic dialog (E1/D-2) and resolved via its own
 * approve/reject actions, never a route. The freshly fetched detail is kept
 * in local state so approve/reject (AC-047) can update the view in place
 * without a refetch; `current` is only trusted while it still matches the
 * on-open fetch's own id, so navigating to a different request's URL never
 * shows a stale handled state left over from a previous one.
 */
export function FieldChangeRequestDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: request,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(fieldChangeRequestKeys.detail(id), () => fetchFieldChangeRequest(id))
  const [current, setCurrent] = useState<FieldChangeRequestResource | null>(null)

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('fieldChangeRequests.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !request) {
    return <DetailLoading />
  }

  const displayed = current && current.id === request.id ? current : request

  return <FieldChangeRequestDetailView request={displayed} onChanged={setCurrent} />
}

/**
 * A field change request has no create/edit route of its own (`generateRoutes:
 * false` below), so this branch is unreachable in practice — kept only
 * because `ModuleScreens.FormScreen` is a mandatory field of the
 * registry type (mirrors `ContractFormScreen`).
 */
export function FieldChangeRequestFormScreen() {
  return null
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: FieldChangeRequestDetailScreen,
  FormScreen: FieldChangeRequestFormScreen,
}
