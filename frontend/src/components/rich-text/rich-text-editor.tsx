import { useEffect, useRef } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import type { Editor, Extensions } from '@tiptap/react'
import { cn } from '@/lib/utils'
import { RichTextToolbar } from '@/components/rich-text/rich-text-toolbar'
import { useRichTextImageInsert } from '@/components/rich-text/use-rich-text-image-insert'
import { createRichTextExtensions } from '@/components/rich-text/rich-text-extensions'
import { RICH_TEXT_PLACEHOLDER_CLASS, RICH_TEXT_PROSE_CLASS } from '@/components/rich-text/rich-text-prose'

export type RichTextEditorMinHeight = 'compact' | 'default'

export interface RichTextEditorProps {
  /** Sanitized HTML fragment (RichTextHtml, D-1) or `null`/empty for no content. */
  value: string | null
  /** Emits `null` when the editor holds neither visible text nor an image (D-2). */
  onChange: (html: string | null) => void
  placeholder?: string
  disabled?: boolean
  /** F2's configured `Mention` extension, injected only where D-7 allows it (notes). */
  extraExtensions?: Extensions
  id?: string
  /** `compact` for inline contexts (notes); `default` for a full-page field (task/template). */
  minHeight?: RichTextEditorMinHeight
  'aria-invalid'?: boolean
  'aria-describedby'?: string
}

const MIN_HEIGHT_CLASS: Record<RichTextEditorMinHeight, string> = {
  compact: 'min-h-16',
  default: 'min-h-32',
}

/** Whether the document holds at least one image node (saved or unsaved, D-2). */
function hasImageNode(editor: Editor): boolean {
  let found = false
  editor.state.doc.descendants((node) => {
    if (node.type.name === 'image') {
      found = true
    }
  })
  return found
}

function toChangeValue(editor: Editor): string | null {
  return editor.isEmpty && !hasImageNode(editor) ? null : editor.getHTML()
}

/**
 * Shared rich text field (D-11): compact toolbar + Tiptap editor. Server
 * sanitizes on save (constraints) — this component never bypasses React's own
 * escaping, it only ever reads/writes through the editor's own HTML output.
 */
export function RichTextEditor({
  value,
  onChange,
  placeholder,
  disabled = false,
  extraExtensions,
  id,
  minHeight = 'default',
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
}: RichTextEditorProps) {
  // Tracks the HTML this component itself last emitted, so an external reset
  // (e.g. `form.reset()` loading a different record) is told apart from the
  // parent simply echoing back our own change — resetting on the latter would
  // fight the user's cursor on every keystroke.
  const lastEmittedRef = useRef<string | null>(value)

  const editor = useEditor({
    extensions: createRichTextExtensions({ placeholder, extraExtensions }),
    content: value ?? '',
    editable: !disabled,
    editorProps: {
      attributes: {
        ...(id ? { id } : {}),
        role: 'textbox',
        'aria-multiline': 'true',
      },
    },
    onUpdate: ({ editor: current }) => {
      const html = toChangeValue(current)
      lastEmittedRef.current = html
      onChange(html)
    },
  })

  const { insertFiles } = useRichTextImageInsert(editor)

  useEffect(() => {
    if (!editor || value === lastEmittedRef.current) {
      return
    }
    lastEmittedRef.current = value
    editor.commands.setContent(value ?? '', { emitUpdate: false })
  }, [editor, value])

  useEffect(() => {
    editor?.setEditable(!disabled)
  }, [editor, disabled])

  useEffect(() => {
    const dom = editor?.view.dom
    if (!dom) {
      return
    }
    if (ariaInvalid) {
      dom.setAttribute('aria-invalid', 'true')
    } else {
      dom.removeAttribute('aria-invalid')
    }
    if (ariaDescribedBy) {
      dom.setAttribute('aria-describedby', ariaDescribedBy)
    } else {
      dom.removeAttribute('aria-describedby')
    }
  }, [editor, ariaInvalid, ariaDescribedBy])

  // Native paste/drop listeners on the ProseMirror DOM node (not React's
  // synthetic `onPaste`/`onDrop` on EditorContent's wrapper, which sits one
  // level above the actual contentEditable element ProseMirror manages).
  useEffect(() => {
    const dom = editor?.view.dom
    if (!dom) {
      return
    }

    const handlePaste = (event: ClipboardEvent) => {
      const files = Array.from(event.clipboardData?.files ?? [])
      if (files.length === 0) {
        return
      }
      event.preventDefault()
      void insertFiles(files)
    }
    const handleDrop = (event: DragEvent) => {
      const files = Array.from(event.dataTransfer?.files ?? [])
      if (files.length === 0) {
        return
      }
      event.preventDefault()
      void insertFiles(files)
    }

    dom.addEventListener('paste', handlePaste)
    dom.addEventListener('drop', handleDrop)
    return () => {
      dom.removeEventListener('paste', handlePaste)
      dom.removeEventListener('drop', handleDrop)
    }
  }, [editor, insertFiles])

  return (
    <div
      className={cn(
        'w-full rounded-md border border-input bg-field shadow-xs transition-[color,box-shadow]',
        'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
        'has-[[aria-invalid=true]]:border-destructive has-[[aria-invalid=true]]:ring-destructive/20',
        disabled && 'pointer-events-none opacity-50',
      )}
    >
      <RichTextToolbar editor={editor} disabled={disabled} onPickImages={insertFiles} />
      <EditorContent
        editor={editor}
        className={cn(
          'px-3 py-2 text-sm outline-none',
          MIN_HEIGHT_CLASS[minHeight],
          RICH_TEXT_PROSE_CLASS,
          RICH_TEXT_PLACEHOLDER_CLASS,
        )}
      />
    </div>
  )
}
