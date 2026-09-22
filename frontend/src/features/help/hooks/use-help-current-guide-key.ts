import { useLocation } from 'react-router-dom'
import { resolveCurrentHelpGuideKey } from '@/features/help/help-current-guide'
import type { HelpVisibleGuide } from '@/features/help/help-visible-guides'

/** The guide key for the page the user is currently on (AC-002/AC-003). */
export function useHelpCurrentGuideKey(guides: readonly HelpVisibleGuide[]): string {
  const { pathname } = useLocation()
  return resolveCurrentHelpGuideKey(guides, pathname)
}
