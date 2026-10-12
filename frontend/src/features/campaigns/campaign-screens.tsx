/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCampaign, campaignDetailQueryKey } from '@/features/campaigns/api'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import { CampaignDuplicateLoader } from '@/features/campaigns/campaign-duplicate-loader'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
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
 * pages (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding
 * chrome.
 *
 * Spec 0220: the detail edits its fields in place, so `onEdit` is never used;
 * each save reports through `onChanged`.
 */
export function CampaignDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(id), () => fetchCampaign(id))

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
    return <DetailLoading />
  }

  return <CampaignDetailView campaign={campaign} onChanged={onChanged} />
}

/**
 * Create and duplicate only (spec 0221): there is no edit form, and the
 * registry generates no `:id/edit` route. Leaving a campaign being created —
 * Cancel, the Sheet's X/overlay/Esc, a link, a reload — always asks first.
 */
export function CampaignFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('campaigns.form.leaveConfirm.title'),
    description: t('campaigns.form.leaveConfirm.description'),
    confirmLabel: t('campaigns.form.leaveConfirm.confirm'),
    cancelLabel: t('campaigns.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type === 'edit') {
    return null
  }

  const handleSuccess = (saved: CampaignDetail) => {
    queryClient.invalidateQueries({ queryKey: campaignDetailQueryKey(saved.id) })
    // Saved: the navigation to the new campaign's detail is no "leaving".
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
      {mode.type === 'duplicate' ? (
        <CampaignDuplicateLoader campaignId={mode.id} onSuccess={handleSuccess} onCancel={handleCancel} />
      ) : (
        <CampaignForm onSuccess={handleSuccess} onCancel={handleCancel} />
      )}
    </>
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
  // The detail IS the edit form (spec 0221): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
