import { useTranslation } from 'react-i18next'
import { DetailError } from '@/components/detail/detail-panel'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCampaign, campaignDetailQueryKey } from '@/features/campaigns/api'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetail } from '@/features/campaigns/types'

interface CampaignDuplicateLoaderProps {
  campaignId: number
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source campaign before mounting the create
 * form pre-filled from it (row action "duplicate"), so the copy starts from
 * authoritative values rather than the grid row snapshot. The form itself
 * still submits via the create path (`CampaignFormMode: 'duplicate'`).
 * Loading shows the same record skeleton as the create form.
 */
export function CampaignDuplicateLoader({ campaignId, onSuccess, onCancel }: CampaignDuplicateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(campaignId), () => fetchCampaign(campaignId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('campaigns.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !campaign) {
    return <RecordFormSkeleton />
  }

  return <CampaignForm source={campaign} onSuccess={onSuccess} onCancel={onCancel} />
}
