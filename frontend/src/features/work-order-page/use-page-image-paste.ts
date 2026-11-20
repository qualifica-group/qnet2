import { useEffect } from 'react'
import type { Editor } from '@tiptap/react'
import { useRichTextImageInsert } from '@/components/rich-text/use-rich-text-image-insert'

/**
 * Paste/drop of image files into the page editor, with the shared validation
 * of the rich text image pipeline. Returns `insertFiles` for the image picker.
 */
export function usePageImagePaste(editor: Editor | null, enabled: boolean) {
  const { insertFiles } = useRichTextImageInsert(editor)

  useEffect(() => {
    const dom = editor?.view.dom
    if (!dom || !enabled) {
      return
    }

    const handleFiles = (event: ClipboardEvent | DragEvent, files: File[]) => {
      if (files.length === 0) {
        return
      }
      event.preventDefault()
      void insertFiles(files)
    }
    const onPaste = (event: ClipboardEvent) => handleFiles(event, Array.from(event.clipboardData?.files ?? []))
    const onDrop = (event: DragEvent) => handleFiles(event, Array.from(event.dataTransfer?.files ?? []))

    dom.addEventListener('paste', onPaste)
    dom.addEventListener('drop', onDrop)
    return () => {
      dom.removeEventListener('paste', onPaste)
      dom.removeEventListener('drop', onDrop)
    }
  }, [editor, enabled, insertFiles])

  return { insertFiles }
}
