import { forwardRef, useEffect, useId, useImperativeHandle, useState } from 'react'
import type { SuggestionKeyDownProps } from '@tiptap/suggestion'
import { MentionPickerPanel } from '@/features/notes/mention-picker-panel'
import type { ForSelectItem } from '@/features/for-select/types'

export interface MentionSuggestionListProps {
  items: ForSelectItem[]
  loading: boolean
  /** Mention's own suggestion command (D-7): inserts the node for the picked user. */
  command: (attrs: { id: string; label: string }) => void
}

export interface MentionSuggestionListHandle {
  /** Delegated from `use-note-mention-extension.ts`'s `suggestion.render().onKeyDown`. */
  onKeyDown: (props: SuggestionKeyDownProps) => boolean
}

/**
 * Keyboard-driven candidate list mounted by `use-note-mention-extension.ts`
 * next to the caret via Tiptap's `Suggestion` plugin (D-12): thin glue around
 * the shared `MentionPickerPanel`, owning only the highlighted index and the
 * Enter/Tab/arrow handling the plugin delegates to it.
 */
export const MentionSuggestionList = forwardRef<MentionSuggestionListHandle, MentionSuggestionListProps>(
  function MentionSuggestionList({ items, loading, command }, ref) {
    const listboxId = useId()
    const [activeIndex, setActiveIndex] = useState(0)

    // A fresh query's results restart highlighting at the top candidate.
    useEffect(() => {
      setActiveIndex(0)
    }, [items])

    useImperativeHandle(
      ref,
      () => ({
        onKeyDown({ event }) {
          if (items.length === 0) {
            return false
          }
          if (event.key === 'ArrowDown') {
            setActiveIndex((index) => (index + 1) % items.length)
            return true
          }
          if (event.key === 'ArrowUp') {
            setActiveIndex((index) => (index - 1 + items.length) % items.length)
            return true
          }
          if (event.key === 'Enter' || event.key === 'Tab') {
            const item = items[activeIndex]
            if (item) {
              command({ id: String(item.id), label: item.label })
            }
            return true
          }
          return false
        },
      }),
      [items, activeIndex, command],
    )

    return (
      <MentionPickerPanel
        listboxId={listboxId}
        options={items}
        isPending={loading}
        activeIndex={activeIndex}
        onActiveIndexChange={setActiveIndex}
        onSelect={(item) => command({ id: String(item.id), label: item.label })}
        optionId={(userId) => `${listboxId}-option-${userId}`}
      />
    )
  },
)
