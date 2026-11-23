import type { LucideIcon } from 'lucide-react'
import { Baseline, Highlighter } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Editor } from '@tiptap/react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'
import {
  HIGHLIGHT_COLORS,
  MARK_NAME,
  TEXT_COLORS,
  type HighlightColor,
  type TextColor,
} from '@/features/work-order-page/editor/constants'
import { HIGHLIGHT_SWATCH_CLASS, TEXT_COLOR_SWATCH_CLASS } from '@/features/work-order-page/menus/color-swatches'
import { refocusEditor } from '@/features/work-order-page/menus/menu-focus'

export type ColorMenuKind = 'highlight' | 'textColor'

interface ColorMenuConfig {
  icon: LucideIcon
  labelKey: string
  removeKey: string
  mark: string
  colors: readonly string[]
  swatchClass: Record<string, string>
  apply: (editor: Editor, color: string) => void
  remove: (editor: Editor) => void
}

const CONFIG: Record<ColorMenuKind, ColorMenuConfig> = {
  highlight: {
    icon: Highlighter,
    labelKey: 'workOrderPage.menus.toolbar.highlight',
    removeKey: 'workOrderPage.menus.toolbar.removeHighlight',
    mark: MARK_NAME.highlight,
    colors: HIGHLIGHT_COLORS,
    swatchClass: HIGHLIGHT_SWATCH_CLASS,
    apply: (editor, color) => editor.chain().focus().setHighlight(color as HighlightColor).run(),
    remove: (editor) => editor.chain().focus().unsetHighlight().run(),
  },
  textColor: {
    icon: Baseline,
    labelKey: 'workOrderPage.menus.toolbar.textColor',
    removeKey: 'workOrderPage.menus.toolbar.removeTextColor',
    mark: MARK_NAME.textColor,
    colors: TEXT_COLORS,
    swatchClass: TEXT_COLOR_SWATCH_CLASS,
    apply: (editor, color) => editor.chain().focus().setTextColor(color as TextColor).run(),
    remove: (editor) => editor.chain().focus().unsetTextColor().run(),
  },
}

interface SelectionColorMenuProps {
  editor: Editor
  kind: ColorMenuKind
  /** Whether the mark is active on the selection (reactive, from the toolbar's editor state). */
  active: boolean
}

/** Palette dropdown of the selection bar: one entry per palette color plus "remove". */
export function SelectionColorMenu({ editor, kind, active }: SelectionColorMenuProps) {
  const { t } = useTranslation()
  const config = CONFIG[kind]
  const Icon = config.icon

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          aria-label={t(config.labelKey)}
          aria-pressed={active}
          className={cn(active && 'bg-accent text-accent-foreground')}
        >
          <Icon className="size-3.5" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" onCloseAutoFocus={refocusEditor(editor)} className="min-w-36">
        {config.colors.map((color) => (
          <DropdownMenuItem key={color} className="text-xs" onSelect={() => config.apply(editor, color)}>
            <span aria-hidden="true" className={cn('size-3 rounded-full border border-border', config.swatchClass[color])} />
            {t(`workOrderPage.menus.colors.${color}`)}
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem className="text-xs" disabled={!active} onSelect={() => config.remove(editor)}>
          {t(config.removeKey)}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
