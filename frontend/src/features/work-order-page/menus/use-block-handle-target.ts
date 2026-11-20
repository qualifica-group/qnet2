import { useEffect, useState } from 'react'
import type { Editor } from '@tiptap/react'
import type { EditorView } from '@tiptap/pm/view'
import { resolveBlock } from '@/features/work-order-page/editor/block-target'

export interface BlockHandleTarget {
  /** Start position of the top-level block the handle acts on. */
  pos: number
  /** Offset of the block top inside `host`, in px. */
  top: number
  /** True while the pointer is over the page (desktop shows the handle only then). */
  hovering: boolean
}

/** Top-level block whose box holds `clientY`, or null in the gaps between blocks / over collapsed ones. */
function findBlockPosAtY(view: EditorView, clientY: number): number | null {
  let found: number | null = null
  view.state.doc.forEach((_node, offset) => {
    if (found !== null) {
      return
    }
    const dom = view.nodeDOM(offset)
    if (!(dom instanceof HTMLElement)) {
      return
    }
    const rect = dom.getBoundingClientRect()
    if (rect.height > 0 && clientY >= rect.top && clientY <= rect.bottom) {
      found = offset
    }
  })
  return found
}

/**
 * Which block the drag handle sits next to: the hovered one (desktop), else
 * the one holding the caret. Returns null when there is nothing to anchor to.
 * The handle is absolutely placed in the editor's parent element (positioned by the page).
 *
 * Everything is measured from event callbacks (transaction, pointer, resize):
 * block geometry is layout, which React state cannot know.
 */
export function useBlockHandleTarget(editor: Editor): BlockHandleTarget | null {
  const [target, setTarget] = useState<BlockHandleTarget | null>(null)

  useEffect(() => {
    const host = editor.view.dom.parentElement
    let hoverPos: number | null = null
    let frame = 0
    let disposed = false

    const refresh = () => {
      if (disposed || editor.isDestroyed) {
        return
      }
      const pos = hoverPos ?? resolveBlock(editor.state)?.pos ?? null
      const dom = pos === null ? null : editor.view.nodeDOM(pos)
      if (pos === null || !(dom instanceof HTMLElement) || !host) {
        setTarget(null)
        return
      }
      const top = dom.getBoundingClientRect().top - host.getBoundingClientRect().top - host.clientTop
      const hovering = hoverPos !== null
      setTarget((previous) =>
        previous?.pos === pos && previous.top === top && previous.hovering === hovering
          ? previous
          : { pos, top, hovering },
      )
    }

    const onTransaction = ({ transaction }: { transaction: { docChanged: boolean } }) => {
      if (transaction.docChanged) {
        hoverPos = null
      }
      refresh()
    }
    const onMove = (event: MouseEvent) => {
      // Over the handle itself the hovered block must not change.
      if (event.target instanceof Element && event.target.closest('[data-block-handle]')) {
        return
      }
      cancelAnimationFrame(frame)
      frame = requestAnimationFrame(() => {
        const found = findBlockPosAtY(editor.view, event.clientY)
        if (found !== null) {
          hoverPos = found
          refresh()
        }
      })
    }
    const onLeave = () => {
      cancelAnimationFrame(frame)
      hoverPos = null
      refresh()
    }

    editor.on('transaction', onTransaction)
    window.addEventListener('resize', refresh)
    host?.addEventListener('mousemove', onMove)
    host?.addEventListener('mouseleave', onLeave)
    queueMicrotask(refresh)

    return () => {
      disposed = true
      cancelAnimationFrame(frame)
      editor.off('transaction', onTransaction)
      window.removeEventListener('resize', refresh)
      host?.removeEventListener('mousemove', onMove)
      host?.removeEventListener('mouseleave', onLeave)
    }
  }, [editor])

  return target
}
