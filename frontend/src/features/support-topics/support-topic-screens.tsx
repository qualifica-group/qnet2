/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchSupportTopic, supportTopicDetailQueryKey } from '@/features/support-topics/api'
import { SupportTopicForm } from '@/features/support-topics/support-topic-form'
import { SupportTopicDetailView } from '@/features/support-topics/support-topic-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { SupportTopicDetail } from '@/features/support-topics/types'

/** Content-only `support-topics` screens for the module registry (spec 0042): fetch + presentational view/form. */
export function SupportTopicDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: supportTopic,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(supportTopicDetailQueryKey(id), () => fetchSupportTopic(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('supportTopics.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !supportTopic) {
    return <DetailLoading />
  }

  return <SupportTopicDetailView supportTopic={supportTopic} onChanged={onChanged} />
}

/** Create only (the detail edits in place): leaving a record being created always asks first. */
export function SupportTopicFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('supportTopics.form.leaveConfirm.title'),
    description: t('supportTopics.form.leaveConfirm.description'),
    confirmLabel: t('supportTopics.form.leaveConfirm.confirm'),
    cancelLabel: t('supportTopics.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: SupportTopicDetail) => {
    queryClient.invalidateQueries({ queryKey: supportTopicDetailQueryKey(saved.id) })
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
      <SupportTopicForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'support-topics',
  basePath: '/support-topics',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.supportTopics',
  DetailScreen: SupportTopicDetailScreen,
  FormScreen: SupportTopicFormScreen,
  generateEditRoute: false,
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}
