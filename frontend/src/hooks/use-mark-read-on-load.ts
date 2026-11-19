import { useEffect, useEffectEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import type { AxiosError } from 'axios'

/**
 * Marks a list read every time its data lands on screen (first load and each
 * refetch), so the "unread" badge of the grids' row actions stops counting
 * what the user has just seen. `loadedAt` is the list query's
 * `dataUpdatedAt` (0 = nothing shown yet); `markRead` resolves to how many
 * items became read, and `onMarked` runs only when that is more than zero,
 * which is when the host's badge needs a refresh.
 */
export function useMarkReadOnLoad(loadedAt: number, markRead: () => Promise<number>, onMarked?: () => void) {
  const { mutate } = useMutation<number, AxiosError>({ mutationFn: () => markRead() })

  const markShownList = useEffectEvent(() => {
    mutate(undefined, {
      onSuccess: (marked) => {
        if (marked > 0) {
          onMarked?.()
        }
      },
    })
  })

  useEffect(() => {
    if (loadedAt > 0) {
      markShownList()
    }
  }, [loadedAt])
}
