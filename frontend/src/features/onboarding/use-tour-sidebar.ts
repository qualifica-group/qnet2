import { useEffect, useRef } from 'react'
import { useSidebar } from '@/components/ui/sidebar'
import type { StepSurface } from '@/features/onboarding/types'

/**
 * Keeps the side menu visible while the tour talks about it (spec 0368 D-11):
 * expanded on desktop, the drawer open on a phone. Outside the menu steps the
 * phone drawer closes (it would cover the header). When the tour ends the menu
 * goes back to how the user had it. `surface` is null when no tour is open.
 */
export function useTourSidebar(surface: StepSurface | null): void {
  const { isMobile, open, setOpen, setOpenMobile } = useSidebar()
  const initialOpen = useRef<boolean | null>(null)

  useEffect(() => {
    if (surface === null) {
      if (initialOpen.current !== null) {
        setOpen(initialOpen.current)
        setOpenMobile(false)
        initialOpen.current = null
      }
      return
    }

    if (initialOpen.current === null) {
      initialOpen.current = open
    }

    if (surface === 'sidebar') {
      if (isMobile) {
        setOpenMobile(true)
      } else if (!open) {
        setOpen(true)
      }
    } else if (surface === 'page' && isMobile) {
      setOpenMobile(false)
    }
  }, [surface, isMobile, open, setOpen, setOpenMobile])
}
