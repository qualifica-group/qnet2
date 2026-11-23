import { useMemo, useRef, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import { RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES } from '@/components/rich-text/rich-text-constants'
import { BlockHandle } from '@/features/work-order-page/menus/block-handle'
import { BottomBar } from '@/features/work-order-page/menus/bottom-bar'
import type { PageMenuActions } from '@/features/work-order-page/menus/menu-items'
import { SelectionBubbleMenu } from '@/features/work-order-page/menus/selection-bubble-menu'
import { useSlashMenu } from '@/features/work-order-page/menus/use-slash-menu'

const IMAGE_ACCEPT = RICH_TEXT_ALLOWED_IMAGE_MIME_TYPES.join(',')

export interface PageEditorMenusProps {
  editor: Editor
  /** Opens the "new task" dialog; on confirm the task is created and its block inserted. */
  onInsertTask: () => void
  /** Opens the "new poll" dialog; on confirm the poll is created and its block inserted. */
  onInsertPoll: () => void
  /** Inserts picked image files (same validation as paste/drop). */
  onPickImages: (files: File[]) => void
}

/**
 * Editing menus of the page, mounted only while it is editable: selection bar,
 * "/" menu, bottom insert bar, and the drag handle with its block menu.
 */
export function PageEditorMenus({ editor, onInsertTask, onInsertPoll, onPickImages }: PageEditorMenusProps) {
  const { t } = useTranslation()
  const fileInputRef = useRef<HTMLInputElement>(null)

  const actions = useMemo<PageMenuActions>(
    () => ({
      insertTask: onInsertTask,
      insertPoll: onInsertPoll,
      pickImage: () => fileInputRef.current?.click(),
    }),
    [onInsertTask, onInsertPoll],
  )

  useSlashMenu(editor, actions)

  const handleFiles = (event: ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(event.target.files ?? [])
    // Reset, so picking the same file twice still fires a change.
    event.target.value = ''
    if (files.length > 0) {
      onPickImages(files)
    }
  }

  return (
    <>
      <SelectionBubbleMenu editor={editor} />
      <BlockHandle editor={editor} />
      <BottomBar editor={editor} actions={actions} />
      <input
        ref={fileInputRef}
        type="file"
        accept={IMAGE_ACCEPT}
        multiple
        tabIndex={-1}
        className="sr-only"
        aria-label={t('workOrderPage.menus.imagePicker')}
        onChange={handleFiles}
      />
    </>
  )
}
