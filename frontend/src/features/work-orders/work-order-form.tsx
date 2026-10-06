import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchWorkOrderNextCode } from '@/features/work-orders/api'
import { WorkOrderFormBody } from '@/features/work-orders/work-order-form-body'
import type { WorkOrderDetail } from '@/features/work-orders/types'

interface WorkOrderFormProps {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (workOrder: WorkOrderDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
  /** Spec 0199: narrows the Offerta picker to one anagrafica's offers (create from its Commesse tab). */
  registryId?: number | null
}

/**
 * RHF + Zod form used for creating a work order — editing happens in place on
 * the detail (spec 0195 applied to Commesse). Metadata-driven (spec 0004):
 * resolves the create-context `ResourcePermissions` (`GET /meta/work-orders`)
 * before rendering, and suggests the next sequential `code`
 * (`GET /work-orders/next-code`, D-1), kept uncached (`staleTime`/`gcTime` 0,
 * mirrors `QuoteForm`) so every new form gets a fresh suggestion.
 */
export function WorkOrderForm({ onSuccess, onCancel, registryId = null }: WorkOrderFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('work-orders', true)

  const nextCode = useQuery({
    queryKey: ['work-orders', 'next-code'],
    queryFn: fetchWorkOrderNextCode,
    staleTime: 0,
    gcTime: 0,
  })

  if (metaQuery.isPending || nextCode.isLoading) {
    return <RecordFormSkeleton />
  }

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data?.permissions ?? null}>
      <WorkOrderFormBody
        onSuccess={onSuccess}
        onCancel={onCancel}
        initialCode={nextCode.data ?? ''}
        registryId={registryId}
      />
    </ResourcePermissionsProvider>
  )
}
