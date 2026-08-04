/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchFieldChangeRequest } from '@/features/field-change-requests/api'
import { fieldChangeRequestKeys } from '@/features/field-change-requests/query-keys'
import { FieldChangeRequestDetailView } from '@/features/field-change-requests/field-change-request-detail'
import { FIELD_CHANGE_REQUESTS_DOMAIN } from '@/features/field-change-requests/types'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleRegistryEntry,
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
    refetch,
  } = useEntityDetail(fieldChangeRequestKeys.detail(id), () => fetchFieldChangeRequest(id))
  const [current, setCurrent] = useState<FieldChangeRequestResource | null>(null)

  if (isError) {
    return (
      <DetailError
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
 * because `ModuleRegistryEntry.FormScreen` is a mandatory field of the
 * registry type (mirrors `ContractFormScreen`).
 */
export function FieldChangeRequestFormScreen() {
  return null
}

/**
 * Auto-registered in the module registry (spec 0042). `generateRoutes:
 * false`: a request is never created/edited through a route (D-2, the
 * generic proposal dialog is the only entry point) — only `/field-change-
 * requests` (list) and `/field-change-requests/:id` (the generic
 * `ModuleDetailPage`) are wired, by hand, in `router.tsx`, mirroring
 * `contracts`. `detailOwnsEditAction: true` for the same reason `contracts`
 * sets it: there is no edit affordance to gate at all.
 */
export const moduleScreen: ModuleRegistryEntry = {
  domain: FIELD_CHANGE_REQUESTS_DOMAIN,
  basePath: '/field-change-requests',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.fieldChangeRequests',
  generateRoutes: false,
  detailOwnsEditAction: true,
  DetailScreen: FieldChangeRequestDetailScreen,
  FormScreen: FieldChangeRequestFormScreen,
}
