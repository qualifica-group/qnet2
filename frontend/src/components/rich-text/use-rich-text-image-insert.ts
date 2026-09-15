import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { Editor } from '@tiptap/react'
import {
  RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES,
  RICH_TEXT_IMAGE_MAX_BYTES,
  RICH_TEXT_MAX_NEW_IMAGES,
} from '@/components/rich-text/rich-text-constants'

const RICH_TEXT_IMAGE_MAX_MB = RICH_TEXT_IMAGE_MAX_BYTES / (1024 * 1024)

function isAllowedImageMimeType(mimeType: string): boolean {
  return (RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES as readonly string[]).includes(mimeType)
}

/** Number of image nodes still carrying a `data:` src (D-3: not an attachment yet). */
function countNewImages(editor: Editor): number {
  let count = 0
  editor.state.doc.descendants((node) => {
    if (node.type.name === 'image' && typeof node.attrs.src === 'string' && node.attrs.src.startsWith('data:')) {
      count += 1
    }
  })
  return count
}

function readFileAsDataUri(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(file)
  })
}

/**
 * Validates and inserts image files (paste/drop/picker, D-3) into a Tiptap
 * editor: MIME allow-list, size cap and the per-field new-image count are all
 * enforced client-side before the file is read, mirroring the server's
 * defenses (D-5) so an invalid file never round-trips through a FileReader
 * just to be rejected on submit.
 */
export function useRichTextImageInsert(editor: Editor | null) {
  const { t } = useTranslation()

  const insertFiles = useCallback(
    async (files: File[]) => {
      if (!editor) {
        return
      }

      for (const file of files) {
        // Step 1: reject a MIME type outside the allow-list
        if (!isAllowedImageMimeType(file.type)) {
          toast.error(
            t('richText.errors.invalidImageType', {
              defaultValue: 'Formato immagine non supportato. Usa PNG, JPEG, GIF o WEBP.',
            }),
          )
          continue
        }
        // Step 2: reject an oversized file
        if (file.size > RICH_TEXT_IMAGE_MAX_BYTES) {
          toast.error(
            t('richText.errors.imageTooLarge', {
              defaultValue: "L'immagine supera la dimensione massima di {{size}} MB.",
              size: RICH_TEXT_IMAGE_MAX_MB,
            }),
          )
          continue
        }
        // Step 3: reject once the field already holds the max unsaved images
        if (countNewImages(editor) >= RICH_TEXT_MAX_NEW_IMAGES) {
          toast.error(
            t('richText.errors.tooManyImages', {
              defaultValue: 'Hai raggiunto il numero massimo di immagini per questo campo ({{max}}).',
              max: RICH_TEXT_MAX_NEW_IMAGES,
            }),
          )
          continue
        }
        // Step 4: read as a data URI and insert the image node
        try {
          const dataUri = await readFileAsDataUri(file)
          editor.chain().focus().insertContent({ type: 'image', attrs: { src: dataUri } }).run()
        } catch {
          toast.error(
            t('richText.errors.imageReadFailed', {
              defaultValue: "Lettura dell'immagine non riuscita. Riprova.",
            }),
          )
        }
      }
    },
    [editor, t],
  )

  return { insertFiles }
}
