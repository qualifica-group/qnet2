import { forwardRef, useEffect, useId, useImperativeHandle, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { SuggestionKeyDownProps } from '@tiptap/suggestion'
import { cn } from '@/lib/utils'
import type { PageMenuItem } from '@/features/work-order-page/menus/menu-items'
import { formatShortcut } from '@/features/work-order-page/menus/shortcut-label'

export interface SlashMenuListProps {
  items: PageMenuItem[]
  command: (item: PageMenuItem) => void
}

export interface SlashMenuListHandle {
  /** Delegated from the suggestion `render().onKeyDown`. */
  onKeyDown: (props: SuggestionKeyDownProps) => boolean
}

/** Keyboard-driven block list of the "/" menu: arrows move, Enter/Tab insert (Esc is the plugin's). */
export const SlashMenuList = forwardRef<SlashMenuListHandle, SlashMenuListProps>(function SlashMenuList(
  { items, command },
  ref,
) {
  const { t } = useTranslation()
  const listboxId = useId()
  const [activeIndex, setActiveIndex] = useState(0)

  // A new query restarts at the top candidate.
  useEffect(() => {
    setActiveIndex(0)
  }, [items])

  useEffect(() => {
    document.getElementById(`${listboxId}-${activeIndex}`)?.scrollIntoView({ block: 'nearest' })
  }, [listboxId, activeIndex])

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
            command(item)
          }
          return true
        }
        return false
      },
    }),
    [items, activeIndex, command],
  )

  if (items.length === 0) {
    return (
      <p role="status" className="rounded-md border border-border bg-popover p-2 text-xs text-muted-foreground shadow-md">
        {t('workOrderPage.menus.slash.empty')}
      </p>
    )
  }

  return (
    <div
      role="listbox"
      aria-label={t('workOrderPage.menus.slash.label')}
      className="max-h-64 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
    >
      {items.map((item, index) => (
        <div
          key={item.id}
          id={`${listboxId}-${index}`}
          role="option"
          aria-selected={index === activeIndex}
          // mousedown, not click: the editor must not lose its selection before the command runs.
          onMouseDown={(event) => {
            event.preventDefault()
            command(item)
          }}
          onMouseEnter={() => setActiveIndex(index)}
          className={cn(
            'flex cursor-default items-center gap-2 rounded-sm px-2 py-1 text-xs select-none',
            index === activeIndex && 'bg-accent text-accent-foreground',
          )}
        >
          <item.icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span className="min-w-0 flex-1 truncate">{t(`workOrderPage.menus.items.${item.id}`)}</span>
          {item.shortcut ? (
            <span className="shrink-0 text-xs text-muted-foreground">{formatShortcut(item.shortcut)}</span>
          ) : null}
        </div>
      ))}
    </div>
  )
})
