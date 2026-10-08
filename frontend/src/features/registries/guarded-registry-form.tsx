import { useTranslation } from 'react-i18next'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { RegistryForm } from '@/features/registries/registry-form'
import type { RegistryDetail } from '@/features/registries/types'

interface GuardedRegistryFormProps {
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * The anagrafica create form behind its leave guard: Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — leaving always asks first (spec 0195 D-9
 * applied to Anagrafiche, spec 0200). Shared by the modal screen and the
 * dedicated `/registries/new` page.
 */
export function GuardedRegistryForm({ onSuccess, onCancel }: GuardedRegistryFormProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('registries.form.leaveConfirm.title'),
    description: t('registries.form.leaveConfirm.description'),
    confirmLabel: t('registries.form.leaveConfirm.confirm'),
    cancelLabel: t('registries.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: RegistryDetail) => {
    // Saved: the navigation to the new anagrafica's detail is no "leaving".
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
      <RegistryForm onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
    </>
  )
}
