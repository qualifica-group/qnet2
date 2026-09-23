import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Check } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { updateProfile } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'
import {
  COLOR_PRESETS,
  toColorPreset,
  type ColorPreset,
} from '@/features/appearance/color-preset'

const HEADING_ID = 'color-preset-heading'

/** Tokens each card previews, in the order the swatches are drawn. */
const SWATCH_CLASSES = ['bg-sidebar', 'bg-primary', 'bg-sidebar-primary'] as const

/**
 * Settings section for the per-user accent palette. Each card carries
 * `[data-color-preset]` so its swatches render that preset's real tokens (in
 * the current light/dark mode) straight from `color-presets.css`. Save sends a
 * partial PATCH /auth/me and primes the ['auth','me'] cache, after which
 * ColorPresetProvider re-tints the app.
 */
export function ColorPresetForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const savedPreset = toColorPreset(user?.color_preset)
  const [preset, setPreset] = useState<ColorPreset>(savedPreset)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const onSave = async () => {
    setIsSaving(true)
    setError(null)
    try {
      const updatedUser = await updateProfile({ color_preset: preset })
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(t('settings.colorPreset.saved'))
    } catch {
      setError(t('settings.genericError'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 id={HEADING_ID} className="text-base font-semibold">
          {t('settings.colorPreset.title')}
        </h3>
        <p className="text-sm text-muted-foreground">{t('settings.colorPreset.subtitle')}</p>
      </div>

      <div
        role="radiogroup"
        aria-labelledby={HEADING_ID}
        className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
      >
        {COLOR_PRESETS.map((option) => {
          const isSelected = option === preset
          return (
            <button
              key={option}
              type="button"
              role="radio"
              aria-checked={isSelected}
              onClick={() => setPreset(option)}
              data-color-preset={option}
              className={cn(
                'relative flex flex-col gap-2 rounded-lg border bg-card p-2.5 text-left shadow-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                isSelected ? 'border-primary ring-2 ring-primary/30' : 'hover:border-primary/40',
              )}
            >
              {isSelected ? (
                <span className="absolute top-1.5 right-1.5 rounded-full bg-primary p-0.5 text-primary-foreground">
                  <Check className="size-3" aria-hidden="true" />
                </span>
              ) : null}
              <span className="flex gap-1" aria-hidden="true">
                {SWATCH_CLASSES.map((swatch) => (
                  <span key={swatch} className={cn('h-7 flex-1 rounded-md border', swatch)} />
                ))}
              </span>
              <span className="truncate text-xs font-medium">
                {t(`settings.colorPreset.options.${option}`)}
              </span>
            </button>
          )
        })}
      </div>

      {error && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      )}

      <div>
        <Button
          type="button"
          onClick={() => void onSave()}
          disabled={isSaving || preset === savedPreset}
        >
          {isSaving ? t('settings.savingProfile') : t('settings.saveProfile')}
        </Button>
      </div>
    </div>
  )
}
