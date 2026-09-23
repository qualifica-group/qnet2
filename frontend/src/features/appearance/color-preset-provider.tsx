import { useEffect, type ReactNode } from 'react'
import { useAuth } from '@/features/auth/use-auth'
import {
  COLOR_PRESET_ATTRIBUTE,
  toColorPreset,
} from '@/features/appearance/color-preset'

/**
 * Applies the authenticated user's `color_preset` to the whole app by setting
 * `[data-color-preset]` on <body>. The palettes themselves are pure CSS
 * (`color-presets.css`) and already carry their dark-mode variants, so the
 * light/dark switch needs nothing from here. <body> rather than <html>: a rule
 * matching body always beats the tokens it inherits from :root/.dark, without
 * a specificity fight. A save primes the ['auth','me'] cache and flows back here.
 */
export function ColorPresetProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const preset = toColorPreset(user?.color_preset)

  useEffect(() => {
    document.body.setAttribute(COLOR_PRESET_ATTRIBUTE, preset)
  }, [preset])

  return children
}
