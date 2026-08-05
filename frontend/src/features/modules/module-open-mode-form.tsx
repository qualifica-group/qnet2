import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { updateProfile } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'
import { ModuleOpenModeField } from '@/features/modules/module-open-mode-field'
import {
  DEFAULT_MODULE_OPEN_PREFERENCES,
  type ModuleOpenPreferences,
} from '@/features/modules/types'

/**
 * Self-contained settings section for the per-user module open-mode preference
 * (spec 0042). Owns its own state and save (partial PATCH /auth/me with only
 * `module_open_preferences`), independent of the profile form. On success it
 * primes the `['auth','me']` cache so the whole app applies the new mode
 * without a reload.
 */
export function ModuleOpenModeForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()

  const [value, setValue] = useState<ModuleOpenPreferences>(
    () => user?.module_open_preferences ?? DEFAULT_MODULE_OPEN_PREFERENCES,
  )
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const persist = async (preferences: ModuleOpenPreferences) => {
    setIsSaving(true)
    setError(null)
    try {
      const updatedUser = await updateProfile({ module_open_preferences: preferences })
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(t('settings.moduleOpenMode.saved'))
    } catch {
      setError(t('settings.genericError'))
    } finally {
      setIsSaving(false)
    }
  }

  const onSave = () => void persist(value)

  // Restore the initial defaults ({mode:'page', overrides:{}} = every module
  // opens as a full page) and apply them in one click.
  const onReset = () => {
    setValue(DEFAULT_MODULE_OPEN_PREFERENCES)
    void persist(DEFAULT_MODULE_OPEN_PREFERENCES)
  }

  return (
    <div className="flex flex-col gap-4">
      <ModuleOpenModeField value={value} onChange={setValue} />

      {error && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" onClick={onSave} disabled={isSaving}>
          {isSaving ? t('settings.savingProfile') : t('settings.saveProfile')}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={onReset}
          disabled={isSaving}
        >
          <RotateCcw aria-hidden="true" />
          {t('settings.moduleOpenMode.reset')}
        </Button>
      </div>
    </div>
  )
}
