import { useState } from 'react'

/** Conversation windows side by side at most (spec 0432 D-7): opening one more closes the oldest. */
export const MAX_OPEN_WINDOWS = 3

/** The assistant's window key; a channel's is `channelWindowKey(workOrderId)`. */
export const AI_WINDOW_KEY = 'ai'

export type ChatDockWindowKey = typeof AI_WINDOW_KEY | `channel:${number}`

export function channelWindowKey(workOrderId: number): ChatDockWindowKey {
  return `channel:${workOrderId}`
}

export interface ChatDockWindow {
  key: ChatDockWindowKey
  minimized: boolean
}

const NO_WINDOWS: ChatDockWindow[] = []

/**
 * The dock's open conversation windows (the assistant and the channels), oldest first. `open` brings an
 * already open window back up; `single` (a phone, where a window takes the
 * whole screen) keeps only the one being opened.
 */
export function useChatDockWindows() {
  const [windows, setWindows] = useState(NO_WINDOWS)

  const open = (key: ChatDockWindowKey, single = false) => {
    setWindows((current) => {
      if (single) {
        return [{ key, minimized: false }]
      }
      if (current.some((window) => window.key === key)) {
        return current.map((window) => (window.key === key ? { ...window, minimized: false } : window))
      }
      return [...current, { key, minimized: false }].slice(-MAX_OPEN_WINDOWS)
    })
  }

  const toggleMinimized = (key: ChatDockWindowKey) => {
    setWindows((current) => current.map((window) => (window.key === key ? { ...window, minimized: !window.minimized } : window)))
  }

  const close = (key: ChatDockWindowKey) => {
    setWindows((current) => current.filter((window) => window.key !== key))
  }

  return { windows, open, toggleMinimized, close }
}
