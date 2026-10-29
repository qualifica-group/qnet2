import { useState } from 'react'
import { LogIn } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/use-auth'

/**
 * Persistent notice shown for the whole duration of an impersonation session
 * (spec 0050). `impersonator` (the original actor) gates visibility; the
 * displayed name is the impersonated user's — `user` from `useAuth()`, since
 * the session's `me` already resolves to the target while impersonating.
 */
export function ImpersonationBanner() {
  const { t, i18n } = useTranslation()
  const { user, impersonator, stopImpersonation } = useAuth()
  const [isExiting, setIsExiting] = useState(false)

  if (!impersonator || !user) {
    return null
  }

  const handleExit = async () => {
    setIsExiting(true)
    try {
      await stopImpersonation()
    } catch (error) {
      toast.error(apiErrorMessage(error, t('impersonation.stopError'), i18n))
    } finally {
      setIsExiting(false)
    }
  }

  return (
    <div
      role="status"
      className="flex items-center justify-between gap-2 border-b border-amber-500/40 bg-amber-500/10 px-4 py-1 text-xs text-amber-700 dark:text-amber-400"
    >
      <span className="flex min-w-0 items-center gap-1.5">
        <LogIn className="size-3.5 shrink-0" aria-hidden="true" />
        <span className="truncate">{t('impersonation.operatingAs', { name: user.name })}</span>
      </span>
      <Button
        type="button"
        variant="secondary"
        size="xs"
        disabled={isExiting}
        onClick={() => void handleExit()}
      >
        {t('impersonation.exit')}
      </Button>
    </div>
  )
}
