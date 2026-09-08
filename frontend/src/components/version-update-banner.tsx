import { useCallback, useEffect, useRef, useState } from 'react'
import { RefreshCw, Sparkles } from 'lucide-react'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'

const VERSION_CHECK_INTERVAL_MS = 60_000
const DISMISSED_BUILD_KEY = 'app-version-update-dismissed-build'
const UPDATE_TOAST_ID = 'app-version-update'

function extractBuildIdFromHtml(html: string) {
  const match = html.match(/<meta\s+name=["']app-build-id["']\s+content=["']([^"']+)["']/i)
  return match?.[1]?.trim() ?? ''
}

function readCurrentBuildId() {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="app-build-id"]')
  return meta?.content?.trim() ?? ''
}

async function clearBrowserCaches() {
  if (!('caches' in window)) {
    return
  }

  const cacheKeys = await window.caches.keys()
  await Promise.all(cacheKeys.map((key) => window.caches.delete(key)))
}

/**
 * Detects that a newer bundle has been deployed while this client kept running
 * and prompts the user to reload onto it.
 *
 * The comparison is between the build id baked into the loaded document and the
 * one in the index.html currently served: no API involved, so the check keeps
 * working on an expired session. Renders nothing — the prompt is a persistent
 * toast, so it never displaces the app chrome.
 */
export function VersionUpdateBanner() {
  const { t } = useTranslation()
  // The build the loaded document was served with: read once per page load, so
  // a later re-render can never compare against a freshly served id.
  const [currentBuildId] = useState(readCurrentBuildId)
  const latestRemoteBuildIdRef = useRef('')
  const hasShownToastRef = useRef(false)
  const isUpdatingRef = useRef(false)
  const [isUpdateAvailable, setIsUpdateAvailable] = useState(false)

  useEffect(() => {
    let isMounted = true

    const checkForNewVersion = async () => {
      if (!currentBuildId || isUpdateAvailable) {
        return
      }

      try {
        const response = await fetch(`/index.html?__version_check=${Date.now()}`, {
          cache: 'no-store',
          headers: { 'cache-control': 'no-cache' },
        })
        if (!response.ok) {
          return
        }

        const html = await response.text()
        const remoteBuildId = extractBuildIdFromHtml(html)
        if (!remoteBuildId) {
          return
        }

        latestRemoteBuildIdRef.current = remoteBuildId
        const dismissedBuildId = window.sessionStorage.getItem(DISMISSED_BUILD_KEY) ?? ''

        if (
          isMounted &&
          remoteBuildId !== currentBuildId &&
          remoteBuildId !== dismissedBuildId
        ) {
          setIsUpdateAvailable(true)
        }
      } catch {
        // Transient network errors are not a deploy: skip this tick silently.
      }
    }

    void checkForNewVersion()
    const intervalId = window.setInterval(() => {
      void checkForNewVersion()
    }, VERSION_CHECK_INTERVAL_MS)

    return () => {
      isMounted = false
      window.clearInterval(intervalId)
    }
  }, [currentBuildId, isUpdateAvailable])

  const handleUpdateClick = useCallback(async () => {
    if (isUpdatingRef.current) {
      return
    }

    isUpdatingRef.current = true
    try {
      // Remember the build we are reloading onto: if the reload lands on it, the
      // next check must not prompt again for the same deploy.
      if (latestRemoteBuildIdRef.current) {
        window.sessionStorage.setItem(DISMISSED_BUILD_KEY, latestRemoteBuildIdRef.current)
      }
      await clearBrowserCaches()
    } finally {
      const nextUrl = new URL(window.location.href)
      nextUrl.searchParams.set('__refresh', Date.now().toString())
      window.location.assign(nextUrl.toString())
    }
  }, [])

  useEffect(() => {
    if (!isUpdateAvailable || hasShownToastRef.current) {
      return
    }

    hasShownToastRef.current = true
    toast(
      <span className="inline-flex items-center gap-1.5">
        <Sparkles className="size-4" />
        {t('appVersion.available')}
      </span>,
      {
        id: UPDATE_TOAST_ID,
        description: t('appVersion.description'),
        duration: Number.POSITIVE_INFINITY,
        action: {
          label: (
            <span className="inline-flex items-center gap-1.5">
              <RefreshCw className="size-3.5" />
              {t('appVersion.update')}
            </span>
          ),
          onClick: () => {
            void handleUpdateClick()
          },
        },
      },
    )
  }, [handleUpdateClick, isUpdateAvailable, t])

  return null
}
