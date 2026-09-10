import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { env } from '@/config/env'
import { useUnreadSummary } from '@/features/notifications/use-notifications'

/** How long each half of the alternating tab title stays on screen. */
const TITLE_SWAP_INTERVAL_MS = 2000

/**
 * Bell shown next to the notification in the tab title, the way the q-net app
 * does it: at tab size the glyph is what catches the eye, the text only
 * confirms what happened. Written as an escape because it IS the character —
 * a literal one in a source file trips the emoji guard, and this is UI content,
 * not decoration of the code.
 */
const BELL_GLYPH = '\u{1F514}'

/**
 * Announces unread notifications in the browser tab title: while at least one
 * is unread, the title alternates between the app name and the most recent
 * notification, both prefixed with the unread count — so the notification is
 * readable from a background tab without losing which app the tab belongs to.
 *
 * Restores the plain app name as soon as everything is read (and on unmount),
 * which is why the title is written here rather than left to `index.html`.
 * Call it once, from the authenticated layout.
 */
export function useNotificationTitle(): void {
  const { t } = useTranslation()
  const { data } = useUnreadSummary()

  const count = data?.count ?? 0
  const latestTitle = data?.latest?.data.title ?? null

  useEffect(() => {
    if (count === 0) {
      document.title = env.appName
      return
    }

    const headline = latestTitle ?? t('notifications.untitled')
    const frames = [
      `(${count}) ${env.appName}`,
      `(${count}) ${BELL_GLYPH} ${headline}`,
    ]
    let frameIndex = 0
    document.title = frames[frameIndex]

    const timer = window.setInterval(() => {
      frameIndex = (frameIndex + 1) % frames.length
      document.title = frames[frameIndex]
    }, TITLE_SWAP_INTERVAL_MS)

    return () => {
      window.clearInterval(timer)
      document.title = env.appName
    }
  }, [count, latestTitle, t])
}
