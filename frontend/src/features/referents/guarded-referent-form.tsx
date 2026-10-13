import { useTranslation } from 'react-i18next'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ReferentDetail } from '@/features/referents/types'

interface GuardedReferentFormProps {
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * The referente create form behind its leave guard: Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — leaving always asks first (spec 0195 D-9
 * applied to Referenti, spec 0216). Shared by the modal screen and the
 * dedicated `/referents/new` page.
 */
export function GuardedReferentForm({ onSuccess, onCancel }: GuardedReferentFormProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('referents.form.leaveConfirm.title'),
    description: t('referents.form.leaveConfirm.description'),
    confirmLabel: t('referents.form.leaveConfirm.confirm'),
    cancelLabel: t('referents.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: ReferentDetail) => {
    // Saved: the navigation to the new referente's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ReferentForm onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
    </>
  )
}
