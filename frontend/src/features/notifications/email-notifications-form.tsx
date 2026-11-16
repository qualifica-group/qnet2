import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { updateProfile } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'

const SWITCH_ID = 'email-notifications-switch'
const HINT_ID = 'email-notifications-hint'

/**
 * Settings section for the per-user email copy of notifications. Flipping the
 * switch saves at once (partial PATCH /auth/me) and primes the ['auth','me']
 * cache; the in-app bell keeps receiving every notification either way.
 */
export function EmailNotificationsForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const onToggle = async (enabled: boolean) => {
    setIsSaving(true)
    setError(null)
    try {
      const updatedUser = await updateProfile({ email_notifications_enabled: enabled })
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(
        t(enabled ? 'settings.emailNotifications.savedOn' : 'settings.emailNotifications.savedOff'),
      )
    } catch {
      setError(t('settings.genericError'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-semibold">{t('settings.emailNotifications.title')}</h3>
        <p className="text-sm text-muted-foreground">{t('settings.emailNotifications.subtitle')}</p>
      </div>

      <div className="flex items-start gap-3">
        <Switch
          id={SWITCH_ID}
          checked={user?.email_notifications_enabled ?? true}
          disabled={isSaving || !user}
          aria-describedby={HINT_ID}
          onCheckedChange={(checked) => void onToggle(checked)}
          className="mt-0.5"
        />
        <div className="flex min-w-0 flex-col gap-0.5">
          <Label htmlFor={SWITCH_ID}>{t('settings.emailNotifications.label')}</Label>
          <p id={HINT_ID} className="text-xs text-muted-foreground">
            {t('settings.emailNotifications.hint')}
          </p>
        </div>
      </div>

      {error && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
