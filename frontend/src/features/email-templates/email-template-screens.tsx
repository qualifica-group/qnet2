/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { emailTemplateDetailQueryKey, fetchEmailTemplate } from '@/features/email-templates/api'
import { EmailTemplateForm } from '@/features/email-templates/email-template-form'
import { EmailTemplateDetailView } from '@/features/email-templates/email-template-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import type { EmailTemplate } from '@/features/email-templates/types'

/**
 * Content-only `email-templates` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function EmailTemplateDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: emailTemplate,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(emailTemplateDetailQueryKey(id), () => fetchEmailTemplate(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('emailTemplates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !emailTemplate) {
    return <DetailLoading />
  }

  return <EmailTemplateDetailView emailTemplate={emailTemplate} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a template being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function EmailTemplateFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('emailTemplates.form.leaveConfirm.title'),
    description: t('emailTemplates.form.leaveConfirm.description'),
    confirmLabel: t('emailTemplates.form.leaveConfirm.confirm'),
    cancelLabel: t('emailTemplates.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: EmailTemplate) => {
    queryClient.invalidateQueries({ queryKey: emailTemplateDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
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
      <EmailTemplateForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: EmailTemplateDetailScreen,
  FormScreen: EmailTemplateFormScreen,
}
