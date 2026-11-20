import { ArrowDown, ArrowUp, Copy, Replace, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import {
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu'
import { BLOCK_TYPES, NODE_NAME, type BlockTypeId } from '@/features/work-order-page/editor/constants'
import { getMenuItem } from '@/features/work-order-page/menus/menu-items'
import { refocusEditor } from '@/features/work-order-page/menus/menu-focus'

interface BlockHandleMenuProps {
  editor: Editor
  /** Start position of the block, frozen when the menu opened. */
  pos: number
}

/** "Trasforma in" / Duplica / Sposta su / Sposta giu' / Elimina for one top-level block. */
export function BlockHandleMenu({ editor, pos }: BlockHandleMenuProps) {
  const { t } = useTranslation()
  const can = editor.can()
  const canTransform = can.setBlockType('paragraph', pos)
  const currentType = blockTypeOf(editor, pos)

  return (
    <DropdownMenuContent align="start" onCloseAutoFocus={refocusEditor(editor)} className="min-w-44">
      <DropdownMenuSub>
        <DropdownMenuSubTrigger className="text-xs" disabled={!canTransform}>
          <Replace aria-hidden="true" />
          {t('workOrderPage.menus.handle.transformInto')}
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent className="min-w-48">
          {BLOCK_TYPES.map((type) => {
            const item = getMenuItem(type)
            return (
              <DropdownMenuCheckboxItem
                key={type}
                className="text-xs"
                checked={currentType === type}
                disabled={!can.setBlockType(type, pos)}
                onSelect={() => editor.chain().focus().setBlockType(type, pos).run()}
              >
                <item.icon aria-hidden="true" />
                {t(`workOrderPage.menus.items.${type}`)}
              </DropdownMenuCheckboxItem>
            )
          })}
        </DropdownMenuSubContent>
      </DropdownMenuSub>
      <DropdownMenuItem
        className="text-xs"
        disabled={!can.duplicateBlock(pos)}
        onSelect={() => editor.chain().focus().duplicateBlock(pos).run()}
      >
        <Copy aria-hidden="true" />
        {t('workOrderPage.menus.handle.duplicate')}
      </DropdownMenuItem>
      <DropdownMenuItem
        className="text-xs"
        disabled={!can.moveBlockUp(pos)}
        onSelect={() => editor.chain().focus().moveBlockUp(pos).run()}
      >
        <ArrowUp aria-hidden="true" />
        {t('workOrderPage.menus.handle.moveUp')}
      </DropdownMenuItem>
      <DropdownMenuItem
        className="text-xs"
        disabled={!can.moveBlockDown(pos)}
        onSelect={() => editor.chain().focus().moveBlockDown(pos).run()}
      >
        <ArrowDown aria-hidden="true" />
        {t('workOrderPage.menus.handle.moveDown')}
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem
        className="text-xs"
        variant="destructive"
        onSelect={() => editor.chain().focus().deleteBlock(pos).run()}
      >
        <Trash2 aria-hidden="true" />
        {t('workOrderPage.menus.handle.delete')}
      </DropdownMenuItem>
    </DropdownMenuContent>
  )
}

/** The "Trasforma in" id matching the block node (node names equal the ids, except headings). */
function blockTypeOf(editor: Editor, pos: number): BlockTypeId | null {
  const node = editor.state.doc.nodeAt(pos)
  if (!node) {
    return null
  }
  const name = node.type.name === NODE_NAME.heading ? `heading${node.attrs.level as number}` : node.type.name
  return BLOCK_TYPES.find((type) => type === name) ?? null
}
