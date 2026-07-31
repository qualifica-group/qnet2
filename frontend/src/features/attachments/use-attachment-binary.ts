import { useEffect, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { attachmentBinaryQueryKey, fetchAttachmentBinary } from '@/features/attachments/api'
import type { Attachment } from '@/features/attachments/types'
import { saveBlob } from '@/lib/download'

/**
 * How long an opened preview keeps its `blob:` URL alive. Revoking it right
 * after `window.open()` would abort the new tab's own load, so the URL is
 * released on a timer once the tab has had time to read it.
 */
const PREVIEW_URL_LIFETIME_MS = 60_000

/**
 * Object URL of an attachment's inline binary, for an `<img>` thumbnail.
 * `null` while loading, on error, or while disabled (non-image files never
 * fetch). The URL is revoked when the blob changes or the tile unmounts.
 */
export function useAttachmentThumbnail(id: number, enabled: boolean): string | null {
  const { data } = useQuery<Blob, AxiosError>({
    queryKey: attachmentBinaryQueryKey(id),
    queryFn: () => fetchAttachmentBinary(id, 'view'),
    enabled,
    // The binary of a stored attachment is immutable (a new upload is a new
    // row), so it never needs a refetch within the session.
    staleTime: Infinity,
  })
  const objectUrl = useMemo(() => (data ? URL.createObjectURL(data) : null), [data])

  // Keyed on the URL itself, so the previous one is released as soon as a new
  // blob replaces it (and on unmount) — never while it is still rendered.
  useEffect(() => {
    if (!objectUrl) {
      return
    }

    return () => URL.revokeObjectURL(objectUrl)
  }, [objectUrl])

  return objectUrl
}

/**
 * Preview / download actions for one attachment. Both stream the binary
 * through the authenticated client (see `fetchAttachmentBinary`), so neither
 * can be a plain anchor.
 */
export function useAttachmentBinaryActions(attachment: Attachment) {
  const { t } = useTranslation()

  // The tab is opened synchronously on the click: opening it after the await
  // would have lost the user gesture and be swallowed by the popup blocker.
  const openPreview = () => {
    const tab = window.open('', '_blank')
    if (!tab) {
      toast.error(t('attachments.errors.previewBlocked'))
      return
    }

    fetchAttachmentBinary(attachment.id, 'view')
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        tab.location.href = url
        window.setTimeout(() => URL.revokeObjectURL(url), PREVIEW_URL_LIFETIME_MS)
      })
      .catch(() => {
        tab.close()
        toast.error(t('attachments.errors.preview'))
      })
  }

  const download = () => {
    fetchAttachmentBinary(attachment.id, 'download')
      .then((blob) => saveBlob(blob, attachment.original_name))
      .catch(() => toast.error(t('attachments.errors.download')))
  }

  return { openPreview, download }
}
