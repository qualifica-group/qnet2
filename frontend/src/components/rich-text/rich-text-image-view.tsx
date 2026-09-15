import { useEffect, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { ImageOff } from 'lucide-react'
import { NodeViewWrapper } from '@tiptap/react'
import type { NodeViewProps } from '@tiptap/react'
import { attachmentBinaryQueryKey, fetchAttachmentBinary } from '@/features/attachments/api'

const THUMBNAIL_CLASS = 'my-1 inline-block max-w-full rounded-md align-middle'

/**
 * A saved image's bytes come from the authenticated blob endpoint, never a
 * plain `<img src>` — the API authenticates with a Bearer token in
 * localStorage (not a cookie), so a direct `/api/attachments/{id}/view` URL
 * on an `<img>` tag would just answer 401 (spec 0128 context, mirrors
 * `use-attachment-binary.ts`'s own reasoning). Distinguishes loading from
 * error (unlike the shared thumbnail hook) so a broken attachment shows an
 * accessible placeholder instead of hanging in a perpetual skeleton.
 */
function useSavedImageObjectUrl(attachmentId: number | null) {
  const { data, isError } = useQuery<Blob, AxiosError>({
    queryKey: attachmentBinaryQueryKey(attachmentId ?? 0),
    queryFn: () => fetchAttachmentBinary(attachmentId as number, 'view'),
    enabled: attachmentId !== null,
    staleTime: Infinity,
  })
  const objectUrl = useMemo(() => (data ? URL.createObjectURL(data) : null), [data])

  useEffect(() => {
    if (!objectUrl) {
      return
    }
    return () => URL.revokeObjectURL(objectUrl)
  }, [objectUrl])

  return { objectUrl, isError }
}

/**
 * Read-only rendering of a rich text image node (D-3), shared by
 * `RichTextEditor` and `RichTextContent`. An unsaved draft (`data:` URI)
 * renders directly; a saved one (`data-attachment-id`) fetches its blob.
 */
export function RichTextImageView({ node }: NodeViewProps) {
  const { t } = useTranslation()
  const alt = typeof node.attrs.alt === 'string' ? node.attrs.alt : ''
  const draftSrc = typeof node.attrs.src === 'string' && node.attrs.src.startsWith('data:') ? node.attrs.src : null
  const attachmentId =
    draftSrc === null && (typeof node.attrs.attachmentId === 'string' || typeof node.attrs.attachmentId === 'number')
      ? Number(node.attrs.attachmentId)
      : null

  const { objectUrl, isError } = useSavedImageObjectUrl(attachmentId)

  if (draftSrc) {
    return (
      <NodeViewWrapper as="span">
        <img src={draftSrc} alt={alt} className={THUMBNAIL_CLASS} />
      </NodeViewWrapper>
    )
  }

  if (attachmentId === null) {
    return null
  }

  if (isError) {
    return (
      <NodeViewWrapper as="span">
        <span
          role="img"
          aria-label={t('richText.content.imageUnavailable', { defaultValue: 'Immagine non disponibile' })}
          className="my-1 inline-flex h-24 w-32 items-center justify-center rounded-md border border-border bg-muted align-middle text-muted-foreground"
        >
          <ImageOff className="size-4" aria-hidden="true" />
        </span>
      </NodeViewWrapper>
    )
  }

  if (!objectUrl) {
    return (
      <NodeViewWrapper as="span">
        <span
          role="img"
          aria-label={t('richText.content.imageLoading', { defaultValue: 'Caricamento immagine…' })}
          className="my-1 inline-flex h-24 w-32 animate-pulse items-center justify-center rounded-md bg-muted align-middle"
        />
      </NodeViewWrapper>
    )
  }

  return (
    <NodeViewWrapper as="span">
      <img src={objectUrl} alt={alt} className={THUMBNAIL_CLASS} />
    </NodeViewWrapper>
  )
}
