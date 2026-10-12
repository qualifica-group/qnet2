import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useCostTypeFormMeta } from '@/features/cost-types/use-cost-type-form-meta'
import { CostTypeFormBody } from '@/features/cost-types/cost-type-form-body'
import type { CostTypeDetail, CostTypeFormMode } from '@/features/cost-types/types'

interface CostTypeFormProps {
  mode: CostTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (costType: CostTypeDetail) => void
  onCancel: () => void
}

/**
 * Create/edit form of a cost type. Resolves the resource `ResourcePermissions`
 * first (edit: from the loaded detail; create: `GET /meta/cost-types`), then
 * hands off to `CostTypeFormBody`.
 */
export function CostTypeForm(props: CostTypeFormProps) {
  const { t } = useTranslation()
  const meta = useCostTypeFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <CostTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
