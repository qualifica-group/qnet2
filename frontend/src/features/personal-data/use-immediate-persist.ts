import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'

/**
 * Shared orchestration for the managers' immediate-persistence path (edit of an
 * owner whose card already exists): tracks a single `pending` flag and wraps an
 * async write with success/error toasts. Returns `true` when the write
 * committed, so the caller can close the dialog only on success and keep it open
 * (for a retry) on failure. Owner-agnostic: the caller supplies the write and
 * the localized message keys.
 */
export function useImmediatePersist() {
  const { t, i18n } = useTranslation()
  const [pending, setPending] = useState(false)

  const run = async (
    action: () => Promise<void>,
    successKey: string,
    errorKey: string,
  ): Promise<boolean> => {
    setPending(true)
    try {
      await action()
      toast.success(t(successKey))
      return true
    } catch (error) {
      toast.error(apiErrorMessage(error, t(errorKey), i18n))
      return false
    } finally {
      setPending(false)
    }
  }

  return { pending, run }
}
