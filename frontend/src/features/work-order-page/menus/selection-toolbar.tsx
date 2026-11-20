import { useState, type ReactNode } from 'react'
import { ChevronDown, Link2, Strikethrough } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useEditorState, type Editor } from '@tiptap/react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'
import { MARK_NAME } from '@/features/work-order-page/editor/constants'
import { STYLE_MENU_ITEMS, type PageMenuActions } from '@/features/work-order-page/menus/menu-items'
import { refocusEditor } from '@/features/work-order-page/menus/menu-focus'
import { SelectionColorMenu } from '@/features/work-order-page/menus/selection-color-menu'
import { SelectionLinkForm } from '@/features/work-order-page/menus/selection-link-form'
import { formatShortcut } from '@/features/work-order-page/menus/shortcut-label'

const BAR_CLASS =
  'flex max-w-[calc(100vw-1rem)] items-center gap-0.5 overflow-x-auto rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md'

function noop() {}

/** Style entries never open dialogs or the file picker, so they need no real actions. */
const STYLE_ACTIONS: PageMenuActions = { insertTask: noop, insertPoll: noop, pickImage: noop }

interface SelectionToolbarProps {
  editor: Editor
}

interface MarkButtonProps {
  label: string
  active: boolean
  onClick: () => void
  children: ReactNode
}

function MarkButton({ label, active, onClick, children }: MarkButtonProps) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-xs"
      aria-label={label}
      aria-pressed={active}
      onClick={onClick}
      className={cn(active && 'bg-accent text-accent-foreground')}
    >
      {children}
    </Button>
  )
}

function StylesMenu({ editor, activeIds }: { editor: Editor; activeIds: readonly string[] }) {
  const { t } = useTranslation()

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="xs">
          {t('workOrderPage.menus.toolbar.styles')}
          <ChevronDown aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" onCloseAutoFocus={refocusEditor(editor)} className="min-w-52">
        {STYLE_MENU_ITEMS.map((item) => (
          <DropdownMenuCheckboxItem
            key={item.id}
            className="text-xs"
            checked={activeIds.includes(item.id)}
            onSelect={() => item.run(editor, STYLE_ACTIONS)}
          >
            <item.icon aria-hidden="true" />
            {t(`workOrderPage.menus.items.${item.id}`)}
            {item.shortcut ? <DropdownMenuShortcut>{formatShortcut(item.shortcut)}</DropdownMenuShortcut> : null}
          </DropdownMenuCheckboxItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

/**
 * Formatting bar shown over a text selection (Teams "Note" style): G/C/S and
 * strikethrough, highlighter, text color, link, and the "Stili" menu. The
 * bubble positioning lives in `SelectionBubbleMenu`; this is the content.
 */
export function SelectionToolbar({ editor }: SelectionToolbarProps) {
  const { t } = useTranslation()
  // The link form belongs to the selection it was opened on: another selection shows the buttons again.
  const [linkRange, setLinkRange] = useState<{ from: number; to: number } | null>(null)
  const state = useEditorState({
    editor,
    selector: ({ editor: current }) => ({
      bold: current.isActive('bold'),
      italic: current.isActive('italic'),
      underline: current.isActive('underline'),
      strike: current.isActive('strike'),
      link: current.isActive('link'),
      highlight: current.isActive(MARK_NAME.highlight),
      textColor: current.isActive(MARK_NAME.textColor),
      activeStyles: STYLE_MENU_ITEMS.filter((item) => item.isActive?.(current)).map((item) => item.id),
      from: current.state.selection.from,
      to: current.state.selection.to,
    }),
  })

  const editingLink = linkRange?.from === state.from && linkRange.to === state.to

  if (editingLink) {
    return (
      <div className={BAR_CLASS}>
        <SelectionLinkForm editor={editor} onDone={() => setLinkRange(null)} />
      </div>
    )
  }

  return (
    <div role="toolbar" aria-label={t('workOrderPage.menus.toolbar.label')} className={BAR_CLASS}>
      <MarkButton
        label={t('workOrderPage.menus.toolbar.bold')}
        active={state.bold}
        onClick={() => editor.chain().focus().toggleBold().run()}
      >
        <span aria-hidden="true" className="text-xs font-bold">
          {t('workOrderPage.menus.toolbar.boldShort')}
        </span>
      </MarkButton>
      <MarkButton
        label={t('workOrderPage.menus.toolbar.italic')}
        active={state.italic}
        onClick={() => editor.chain().focus().toggleItalic().run()}
      >
        <span aria-hidden="true" className="text-xs italic">
          {t('workOrderPage.menus.toolbar.italicShort')}
        </span>
      </MarkButton>
      <MarkButton
        label={t('workOrderPage.menus.toolbar.underline')}
        active={state.underline}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
      >
        <span aria-hidden="true" className="text-xs underline">
          {t('workOrderPage.menus.toolbar.underlineShort')}
        </span>
      </MarkButton>
      <MarkButton
        label={t('workOrderPage.menus.toolbar.strike')}
        active={state.strike}
        onClick={() => editor.chain().focus().toggleStrike().run()}
      >
        <Strikethrough className="size-3.5" aria-hidden="true" />
      </MarkButton>
      <SelectionColorMenu editor={editor} kind="highlight" active={state.highlight} />
      <SelectionColorMenu editor={editor} kind="textColor" active={state.textColor} />
      <MarkButton label={t('workOrderPage.menus.toolbar.link')} active={state.link} onClick={() => setLinkRange({ from: state.from, to: state.to })}>
        <Link2 className="size-3.5" aria-hidden="true" />
      </MarkButton>
      <StylesMenu editor={editor} activeIds={state.activeStyles} />
    </div>
  )
}
