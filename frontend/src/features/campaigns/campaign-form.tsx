import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchCampaignNextCode } from '@/features/campaigns/api'
import { CampaignFormBody } from '@/features/campaigns/campaign-form-body'
import type { CampaignDetail } from '@/features/campaigns/types'

interface CampaignFormProps {
  /** Duplicate (row action "duplicate"): the campaign the new one is prefilled from; absent = bare create. */
  source?: CampaignDetail
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (campaign: CampaignDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The campaign create form — bare or duplicated (spec 0221: there is no edit
 * form, the detail edits in place). Metadata-driven (spec 0004): resolves the
 * create-context `ResourcePermissions` (`GET /meta/campaigns`) and the next
 * sequential code (spec 0025, the source's own code is never copied) before
 * rendering `CampaignFormBody`.
 */
export function CampaignForm(props: CampaignFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('campaigns')
  // Kept uncached (staleTime/gcTime 0) so each new form gets a fresh
  // suggestion; an error degrades gracefully to an empty code to type.
  const nextCode = useQuery({
    queryKey: ['campaigns', 'next-code'],
    queryFn: fetchCampaignNextCode,
    staleTime: 0,
    gcTime: 0,
  })

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

  if (!metaQuery.data || nextCode.isLoading) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <CampaignFormBody {...props} initialCode={nextCode.data ?? ''} />
    </ResourcePermissionsProvider>
  )
}
