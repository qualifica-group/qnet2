import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import { DropdownMenu, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'
import { BlockHandleMenu } from '@/features/work-order-page/menus/block-handle-menu'
import { endBlockDrag, startBlockDrag } from '@/features/work-order-page/menus/block-drag'
import { useBlockHandleTarget } from '@/features/work-order-page/menus/use-block-handle-target'
import { useCanDrag } from '@/features/work-order-page/menus/use-can-drag'

interface BlockHandleProps {
  editor: Editor
}

/**
 * The drag handle of the page: a grip button on the left of the hovered block
 * (desktop) or of the block holding the caret. Drag moves the block; a click
 * opens the block menu. Below `md` it is always visible and only opens the
 * menu (no drag on touch). It is a real button, so it is reachable from the
 * keyboard (Tab out of the editor lands on it).
 */
export function BlockHandle({ editor }: BlockHandleProps) {
  const { t } = useTranslation()
  const canDrag = useCanDrag()
  // The editor's parent is managed by Tiptap, not React: the portal gets its own child layer,
  // otherwise React fails to remove its nodes when the editor tears the parent down.
  const [layer] = useState(() => document.createElement('div'))
  // Frozen while the menu is open: hovering the menu itself must not retarget it.
  const [openTarget, setOpenTarget] = useState<{ pos: number; top: number } | null>(null)
  const live = useBlockHandleTarget(editor)

  useEffect(() => {
    const parent = editor.view.dom.parentElement
    parent?.appendChild(layer)
    return () => layer.remove()
  }, [editor, layer])

  const target = openTarget ?? live
  if (!target) {
    return null
  }
  const visible = openTarget !== null || (live?.hovering ?? false)

  return createPortal(
    <DropdownMenu
      modal={false}
      open={openTarget !== null}
      onOpenChange={(open) => setOpenTarget(open && live ? { pos: live.pos, top: live.top } : null)}
    >
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          data-block-handle=""
          aria-label={t('workOrderPage.menus.handle.label')}
          draggable={canDrag}
          // Desktop: Radix would open the menu on pointerdown, i.e. at the start of every drag; the click opens it.
          onPointerDown={(event) => {
            if (canDrag) {
              event.preventDefault()
            }
          }}
          onClick={() => {
            if (canDrag && live) {
              setOpenTarget((current) => (current ? null : { pos: live.pos, top: live.top }))
            }
          }}
          onDragStart={(event) => startBlockDrag(editor, target.pos, event.nativeEvent)}
          onDragEnd={() => endBlockDrag(editor)}
          style={{ top: target.top }}
          className={cn(
            'absolute left-1 z-10 flex h-6 w-5 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            canDrag && 'cursor-grab active:cursor-grabbing',
            visible || !canDrag ? 'opacity-100' : 'opacity-0',
          )}
        >
          <GripVertical className="size-3.5" aria-hidden="true" />
        </button>
      </DropdownMenuTrigger>
      {openTarget ? <BlockHandleMenu editor={editor} pos={openTarget.pos} /> : null}
    </DropdownMenu>,
    layer,
  )
}
