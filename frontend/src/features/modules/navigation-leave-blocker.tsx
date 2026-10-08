import { useEffect } from 'react'
import { useBlocker } from 'react-router-dom'

interface NavigationLeaveBlockerProps {
  shouldBlock: () => boolean
  confirmLeave: () => Promise<boolean>
}

/** In-app navigation (a link, Back) while the form is open: confirmed, then let through or cancelled. */
export function NavigationLeaveBlocker({ shouldBlock, confirmLeave }: NavigationLeaveBlockerProps) {
  const blocker = useBlocker(shouldBlock)

  useEffect(() => {
    if (blocker.state !== 'blocked') {
      return
    }
    void confirmLeave().then((confirmed) => (confirmed ? blocker.proceed() : blocker.reset()))
  }, [blocker, confirmLeave])

  return null
}
