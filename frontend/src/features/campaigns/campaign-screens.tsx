/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { campaignDetailQueryKey, fetchCampaign } from '@/features/campaigns/api'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { CampaignDetail } from '@/features/campaigns/types'

/**
 * Content-only `campaigns` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `CampaignsTable`'s inline loaders, which the rewire removed.
 */
export function CampaignDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(id), () => fetchCampaign(id))

  if (isError) {
    return (
      <DetailError
        message={t('campaigns.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !campaign) {
    return <DetailLoading />
  }

  return <CampaignDetailView campaign={campaign} onEdit={onEdit} />
}

export function CampaignFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: CampaignDetail) => {
    queryClient.invalidateQueries({ queryKey: campaignDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <CampaignForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  if (mode.type === 'duplicate') {
    return <CampaignDuplicateScreen campaignId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <CampaignEditScreen campaignId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface CampaignEditScreenProps {
  campaignId: number
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized campaign detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function CampaignEditScreen({ campaignId, onSuccess, onCancel }: CampaignEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(campaignId), () => fetchCampaign(campaignId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('campaigns.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !campaign) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CampaignForm mode={{ type: 'edit', campaign }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

interface CampaignDuplicateScreenProps {
  campaignId: number
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source campaign before mounting the
 * create form pre-filled from it (row action "duplicate"): the copy still
 * submits via the create path (`CampaignFormMode: 'duplicate'`).
 */
function CampaignDuplicateScreen({ campaignId, onSuccess, onCancel }: CampaignDuplicateScreenProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(campaignId), () => fetchCampaign(campaignId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('campaigns.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !campaign) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CampaignForm mode={{ type: 'duplicate', source: campaign }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'campaigns',
  basePath: '/campaigns',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.campaigns',
  DetailScreen: CampaignDetailScreen,
  FormScreen: CampaignFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}