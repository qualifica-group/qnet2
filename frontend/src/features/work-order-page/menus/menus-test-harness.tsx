import { useEffect } from 'react'
import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import { createPageExtensions } from '@/features/work-order-page/editor/create-page-extensions'
import '@/features/work-order-page/menus/menus-test-utils'
import { PageEditorMenus } from '@/features/work-order-page/menus/page-editor-menus'

interface MenusHarnessProps {
  content: string
  onEditor: (editor: Editor) => void
  onInsertTask?: () => void
  onInsertPoll?: () => void
  onPickImages?: (files: File[]) => void
}

/** The real editor + `PageEditorMenus`, as the page mounts them (test helper). */
export function MenusHarness({ content, onEditor, onInsertTask, onInsertPoll, onPickImages }: MenusHarnessProps) {
  const editor = useEditor({ extensions: createPageExtensions(), content })

  useEffect(() => {
    onEditor(editor)
  }, [editor, onEditor])

  return (
    <div>
      <EditorContent editor={editor} className="relative" />
      <PageEditorMenus
        editor={editor}
        onInsertTask={onInsertTask ?? (() => {})}
        onInsertPoll={onInsertPoll ?? (() => {})}
        onPickImages={onPickImages ?? (() => {})}
      />
    </div>
  )
}
