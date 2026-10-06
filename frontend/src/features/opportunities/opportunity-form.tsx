import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { OpportunityFormBody } from '@/features/opportunities/opportunity-form-body'
import type { OpportunityCreateFormMode, OpportunityDetail } from '@/features/opportunities/types'

interface OpportunityFormProps {
  mode: OpportunityCreateFormMode
  /** Called after a successful create so the caller can navigate to the detail page. */
  onSuccess: (opportunity: OpportunityDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * RHF + Zod form used for creating an opportunity (spec 0040) — editing
 * happens in place on the detail (spec 0198). Metadata-driven (spec 0004):
 * resolves the create-context `ResourcePermissions` (`GET /meta/opportunities`)
 * before rendering, then hands off to `OpportunityFormBody`.
 */
export function OpportunityForm(props: OpportunityFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('opportunities', true)

  if (metaQuery.isPending) {
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
      <OpportunityFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
