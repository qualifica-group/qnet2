import { Ellipsis } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  BOTTOM_BAR_MORE_ITEMS,
  BOTTOM_BAR_PRIMARY_ITEMS,
  type PageMenuActions,
} from '@/features/work-order-page/menus/menu-items'
import { refocusEditor } from '@/features/work-order-page/menus/menu-focus'

interface BottomBarProps {
  editor: Editor
  actions: PageMenuActions
}

/**
 * Insert bar pinned to the bottom of the panel (Teams "Note" style): the five
 * most used blocks plus a "..." menu with the rest. It scrolls on its own
 * when the width is short, so the page never gains a horizontal scrollbar.
 */
export function BottomBar({ editor, actions }: BottomBarProps) {
  const { t } = useTranslation()

  return (
    <div
      role="toolbar"
      aria-label={t('workOrderPage.menus.bottomBar.label')}
      className="sticky bottom-0 z-10 flex w-full items-center gap-0.5 overflow-x-auto rounded-lg border border-border bg-card p-1 shadow-sm"
    >
      {BOTTOM_BAR_PRIMARY_ITEMS.map((item) => (
        <Button
          key={item.id}
          type="button"
          variant="ghost"
          size="xs"
          className="shrink-0"
          onClick={() => item.run(editor, actions)}
        >
          <item.icon aria-hidden="true" />
          {t(`workOrderPage.menus.items.${item.id}`)}
        </Button>
      ))}
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="shrink-0"
            aria-label={t('workOrderPage.menus.bottomBar.more')}
          >
            <Ellipsis aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" side="top" onCloseAutoFocus={refocusEditor(editor)} className="min-w-52">
          {BOTTOM_BAR_MORE_ITEMS.map((item) => (
            <DropdownMenuItem key={item.id} className="text-xs" onSelect={() => item.run(editor, actions)}>
              <item.icon aria-hidden="true" />
              {t(`workOrderPage.menus.items.${item.id}`)}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}
