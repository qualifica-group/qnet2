import { BookOpen, type LucideIcon } from 'lucide-react'
import { resolveIcon } from '@/features/navigation/icon-map'
import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'

/**
 * Icon shown for a guide entry (AC-014): the synthetic `general` guide has
 * no menu node to take an icon from, so it gets a fixed one; every other
 * guide reuses its own menu entry's icon, via the same `resolveIcon` the
 * sidebar uses.
 */
export function resolveHelpGuideIcon(key: string, icon: string | null): LucideIcon {
  if (key === GENERAL_HELP_KEY) {
    return BookOpen
  }
  return resolveIcon(icon)
}
