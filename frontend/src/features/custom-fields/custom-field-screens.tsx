/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCustomFieldDefinition } from '@/features/custom-fields/api'
import { CustomFieldDefinitionForm } from '@/features/custom-fields/custom-field-definition-form'
import { CustomFieldDetailView } from '@/features/custom-fields/custom-field-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/** Query key for a single definition's detail (fresh-on-open pattern), moved verbatim from `CustomFieldsTable`. */
function detailQueryKey(id: number) {
  return ['custom-fields', 'detail', id] as const
}

/**
 * Content-only `custom-fields` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `CustomFieldsTable`'s inline loaders, which the rewire removed.
 */
export function CustomFieldDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: definition,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchCustomFieldDefinition(id))

  if (isError) {
    return (
      <DetailError
        message={t('customFields.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !definition) {
    return <DetailLoading />
  }

  return <CustomFieldDetailView definition={definition} onEdit={onEdit} />
}

export function CustomFieldFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: CustomFieldDefinitionDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <CustomFieldDefinitionForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <CustomFieldEditScreen definitionId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface CustomFieldEditScreenProps {
  definitionId: number
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized definition detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function CustomFieldEditScreen({ definitionId, onSuccess, onCancel }: CustomFieldEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: definition,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(definitionId), () => fetchCustomFieldDefinition(definitionId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('customFields.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !definition) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CustomFieldDefinitionForm
      mode={{ type: 'edit', definition }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'custom-fields',
  basePath: '/custom-fields',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.customFields',
  DetailScreen: CustomFieldDetailScreen,
  FormScreen: CustomFieldFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}