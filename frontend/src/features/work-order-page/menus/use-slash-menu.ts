import { useEffect, useRef } from 'react'
import { PluginKey } from '@tiptap/pm/state'
import { ReactRenderer, type Editor } from '@tiptap/react'
import { Suggestion, type SuggestionKeyDownProps, type SuggestionProps } from '@tiptap/suggestion'
import i18n from '@/i18n'
import {
  SLASH_MENU_ITEMS,
  type PageMenuActions,
  type PageMenuItem,
} from '@/features/work-order-page/menus/menu-items'
import {
  SlashMenuList,
  type SlashMenuListHandle,
  type SlashMenuListProps,
} from '@/features/work-order-page/menus/slash-menu-list'

const SLASH_PLUGIN_KEY = new PluginKey('pageSlashMenu')
const SLASH_POPUP_WIDTH = '15rem'
/** Above the sticky bottom bar and the dialogs of the page. */
const SLASH_POPUP_Z_INDEX = '50'

type SlashPopup = ReactRenderer<SlashMenuListHandle, SlashMenuListProps>

function filterItems(query: string): PageMenuItem[] {
  const needle = query.trim().toLowerCase()
  if (needle === '') {
    return SLASH_MENU_ITEMS
  }
  return SLASH_MENU_ITEMS.filter((item) =>
    i18n.t(`workOrderPage.menus.items.${item.id}`).toLowerCase().includes(needle),
  )
}

function createSlashPopup() {
  let popup: SlashPopup | null = null

  function position(clientRect: SuggestionProps<PageMenuItem>['clientRect']) {
    const rect = clientRect?.()
    if (!popup || !rect) {
      return
    }
    Object.assign(popup.element.style, {
      position: 'fixed',
      left: `${rect.left}px`,
      top: `${rect.bottom + 4}px`,
      width: SLASH_POPUP_WIDTH,
      zIndex: SLASH_POPUP_Z_INDEX,
      // A modal Radix dialog disables pointer events on <body>; the popup is appended there.
      pointerEvents: 'auto',
    })
  }

  return {
    onStart(props: SuggestionProps<PageMenuItem>) {
      popup = new ReactRenderer(SlashMenuList, {
        editor: props.editor,
        props: { items: props.items, command: props.command },
      })
      document.body.appendChild(popup.element)
      position(props.clientRect)
    },
    onUpdate(props: SuggestionProps<PageMenuItem>) {
      popup?.updateProps({ items: props.items, command: props.command })
      position(props.clientRect)
    },
    onKeyDown(props: SuggestionKeyDownProps): boolean {
      if (props.event.key === 'Escape') {
        popup?.element.remove()
        return true
      }
      return popup?.ref?.onKeyDown(props) ?? false
    },
    onExit() {
      popup?.element.remove()
      popup?.destroy()
      popup = null
    },
  }
}

/**
 * Registers the "/" block menu on the editor while mounted. It opens when "/"
 * is typed at the start of a block or after a space; the typed "/query" is
 * removed before the chosen block is inserted.
 */
export function useSlashMenu(editor: Editor, actions: PageMenuActions): void {
  const actionsRef = useRef(actions)
  useEffect(() => {
    actionsRef.current = actions
  }, [actions])

  useEffect(() => {
    const plugin = Suggestion<PageMenuItem, PageMenuItem>({
      editor,
      pluginKey: SLASH_PLUGIN_KEY,
      char: '/',
      // Code has no blocks to insert: "/" there is just a slash.
      allow: ({ state, range }) => !state.doc.resolve(range.from).parent.type.spec.code,
      items: ({ query }) => filterItems(query),
      command: ({ editor: current, range, props: item }) => {
        current.chain().focus().deleteRange(range).run()
        item.run(current, actionsRef.current)
      },
      render: createSlashPopup,
    })
    // First in the list: the core keymap would otherwise consume Enter/Arrow before the menu sees them.
    editor.registerPlugin(plugin, (added, existing) => [added, ...existing])
    return () => {
      editor.unregisterPlugin(SLASH_PLUGIN_KEY)
    }
  }, [editor])
}
