import { useCallback, useEffect, useRef } from 'react'

/** Distance from the bottom under which the list keeps following new content. */
const FOLLOW_THRESHOLD_PX = 80

/**
 * Keeps a scrollable list pinned to its bottom while content grows (streaming),
 * unless the user scrolled up to read earlier messages.
 */
export function useStickToBottom(trigger: unknown) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const followingRef = useRef(true)

  const onScroll = useCallback(() => {
    const element = containerRef.current
    if (element) {
      followingRef.current =
        element.scrollHeight - element.scrollTop - element.clientHeight <= FOLLOW_THRESHOLD_PX
    }
  }, [])

  useEffect(() => {
    const element = containerRef.current
    if (element && followingRef.current) {
      element.scrollTop = element.scrollHeight
    }
  }, [trigger])

  return { containerRef, onScroll }
}
