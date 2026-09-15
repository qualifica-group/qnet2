import { useMemo } from 'react'
import { Mention } from '@tiptap/extension-mention'
import { ReactRenderer } from '@tiptap/react'
import type { Extensions } from '@tiptap/react'
import type { SuggestionKeyDownProps, SuggestionProps } from '@tiptap/suggestion'
import {
  richTextMentionRenderHTML,
  richTextMentionRenderText,
} from '@/components/rich-text/rich-text-extensions'
import { fetchMentionableUsers, NOTES_MENTIONABLE_PAGE_SIZE } from '@/features/notes/api'
import {
  MentionSuggestionList,
  type MentionSuggestionListHandle,
  type MentionSuggestionListProps,
} from '@/features/notes/mention-suggestion-list'
import type { ForSelectItem } from '@/features/for-select/types'

/** Mirrors `useDebouncedValue`'s default (`hooks/use-debounced-value.ts`): same search rhythm as the retired `MentionTextarea`. */
const MENTION_SEARCH_DEBOUNCE_MS = 300

/** `MentionPickerPanel`'s own `min-w-56`: the mounted popup has no positioned ancestor to size its `w-full` against. */
const MENTION_POPUP_WIDTH = '14rem'

type MentionPopupRenderer = ReactRenderer<MentionSuggestionListHandle, MentionSuggestionListProps>

interface UseNoteMentionExtensionOptions {
  entityType: string
  entityId: number
}

/**
 * F2's configured Tiptap `Mention` extension (D-7, D-12): the `@` trigger
 * feeds off the same contextual lookup as the retired `MentionTextarea`
 * (`fetchMentionableUsers`, D-10), debounced the same amount, and inserts the
 * D-7 wire format through F1's shared `richTextMentionRenderText`/
 * `richTextMentionRenderHTML`. The popup reuses `MentionPickerPanel`, mounted
 * next to the caret via the `Suggestion` plugin's own `clientRect` — the
 * shared `components/rich-text/` components own none of this positioning.
 */
export function useNoteMentionExtension({ entityType, entityId }: UseNoteMentionExtensionOptions): Extensions {
  return useMemo(
    () => [
      Mention.configure({
        renderText: richTextMentionRenderText,
        renderHTML: richTextMentionRenderHTML,
        suggestion: {
          items: async ({ query }: { query: string }): Promise<ForSelectItem[]> => {
            const page = await fetchMentionableUsers({
              entityType,
              entityId,
              search: query,
              offset: 0,
              limit: NOTES_MENTIONABLE_PAGE_SIZE,
            })
            return page.items
          },
          debounce: MENTION_SEARCH_DEBOUNCE_MS,
          render: createMentionPopupRenderer,
          // Mirrors the extension's own default insertion (trailing space,
          // absorbing one already there) minus its trailing native
          // `window.getSelection().collapseToEnd()` housekeeping call, which
          // jsdom rejects ("no selection to collapse") when nothing native
          // is selected — ProseMirror's own selection state doesn't need it.
          command: ({ editor, range, props }) => {
            const nodeAfter = editor.view.state.selection.$to.nodeAfter
            if (nodeAfter?.text?.startsWith(' ')) {
              range.to += 1
            }
            editor
              .chain()
              .focus()
              .insertContentAt(range, [
                { type: 'mention', attrs: props },
                { type: 'text', text: ' ' },
              ])
              .run()
          },
        },
      }),
    ],
    [entityType, entityId],
  )
}

/** `suggestion.render()` factory: Tiptap calls this once per open/close cycle of the popup. */
function createMentionPopupRenderer() {
  let popup: MentionPopupRenderer | null = null

  function position(clientRect: SuggestionProps<ForSelectItem>['clientRect']) {
    const rect = clientRect?.()
    if (!popup || !rect) {
      return
    }
    Object.assign(popup.element.style, {
      position: 'fixed',
      left: `${rect.left}px`,
      top: `${rect.bottom}px`,
      width: MENTION_POPUP_WIDTH,
      zIndex: '50',
    })
  }

  function popupProps(props: SuggestionProps<ForSelectItem>): MentionSuggestionListProps {
    return { items: props.items, loading: props.loading, command: props.command }
  }

  return {
    onStart(props: SuggestionProps<ForSelectItem>) {
      popup = new ReactRenderer(MentionSuggestionList, { editor: props.editor, props: popupProps(props) })
      document.body.appendChild(popup.element)
      position(props.clientRect)
    },
    onUpdate(props: SuggestionProps<ForSelectItem>) {
      popup?.updateProps(popupProps(props))
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
