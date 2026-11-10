import { useCallback, type MouseEvent, type ReactNode } from 'react'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { isModifiedClick } from '@/lib/modified-click'

export interface UseRecordModalLinkResult {
  /** `onClick` for the record's `<Link>`: a plain click opens the modal, a modified click is left to the browser. */
  onClick: (event: MouseEvent<HTMLAnchorElement>) => void
  /** The modal Sheet; render it once next to the link. */
  sheet: ReactNode
}

/**
 * Turns a link to another record on a detail surface into a MODAL opener (user
 * directive 2026-09-14): the record being read is never abandoned, and the
 * modal's toolbar (`SheetDetailPageLink`) offers the jump to the dedicated page.
 * The caller keeps a real `<Link>` to the record's route, so new tab, copy and
 * keyboard reach still work. `domain` must be registered (`useModuleOpener`
 * throws otherwise): resolve it before mounting the hook.
 */
export function useRecordModalLink(domain: string, id: number): UseRecordModalLinkResult {
  const { openView, sheet } = useModuleOpener(domain, { forceMode: OPEN_MODE_MODAL })

  const onClick = useCallback(
    (event: MouseEvent<HTMLAnchorElement>) => {
      if (isModifiedClick(event)) {
        return
      }
      event.preventDefault()
      openView({ id, actions: [] })
    },
    [openView, id],
  )

  return { onClick, sheet }
}
