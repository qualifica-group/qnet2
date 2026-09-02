import { useTranslation } from 'react-i18next'
import { UserAvatar } from '@/components/user-avatar'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import type { ForSelectItem } from '@/features/for-select/types'

export interface MentionPickerPanelProps {
  /** Id shared with the field's `aria-controls`/`aria-activedescendant` wiring. */
  listboxId: string
  options: ForSelectItem[]
  isPending: boolean
  activeIndex: number
  onActiveIndexChange: (index: number) => void
  onSelect: (item: ForSelectItem) => void
  /** Builds an option's DOM id, so the field can point `aria-activedescendant` at it. */
  optionId: (userId: number) => string
}

/**
 * Candidate list of the inline `@mention` autocomplete: a plain `role="listbox"`
 * panel under the field (no Radix popover, so arrow keys never leave the
 * textarea). Presentation only — highlighting and insertion are owned by
 * `MentionTextarea`, which drives it.
 */
export function MentionPickerPanel({
  listboxId,
  options,
  isPending,
  activeIndex,
  onActiveIndexChange,
  onSelect,
  optionId,
}: MentionPickerPanelProps) {
  const { t } = useTranslation()

  return (
    <div
      id={listboxId}
      role="listbox"
      aria-label={t('notes.mentionPicker.label', { defaultValue: 'Mentionable users' })}
      className="absolute top-full left-0 z-50 mt-1.5 w-full min-w-56 overflow-hidden rounded-lg border border-muted-foreground/20 bg-white text-popover-foreground shadow-xl dark:bg-muted/40"
    >
      <div className="flex items-center justify-between gap-2 border-b border-muted-foreground/15 bg-muted/50 px-2 py-1">
        <span className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
          {t('notes.mentionPicker.title', { defaultValue: 'Mention a colleague' })}
        </span>
        <span className="text-[10px] text-muted-foreground">
          {t('notes.mentionPicker.hint', { defaultValue: 'Tab or Enter to insert' })}
        </span>
      </div>
      <div className="max-h-56 overflow-y-auto p-1">
        {isPending ? (
          <MentionOptionsSkeleton />
        ) : options.length === 0 ? (
          <p className="px-2 py-3 text-center text-xs text-muted-foreground">
            {t('notes.mentionPicker.empty', { defaultValue: 'No matching users' })}
          </p>
        ) : (
          options.map((item, index) => (
            <div
              key={item.id}
              id={optionId(item.id)}
              role="option"
              aria-selected={index === activeIndex}
              onMouseEnter={() => onActiveIndexChange(index)}
              // `mousedown` (not `click`): the textarea's own `onBlur` closes the
              // picker, which would unmount the option before a `click` ever
              // lands. Preventing the default also keeps focus in the field.
              onMouseDown={(event) => {
                event.preventDefault()
                onSelect(item)
              }}
              className={cn(
                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors',
                index === activeIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
              )}
            >
              <UserAvatar name={item.label} src={item.avatar_url} size="sm" />
              <span className="flex min-w-0 flex-col">
                <span className="truncate font-medium">{item.label}</span>
                {item.subtitle ? (
                  <span className="truncate text-[10px] text-muted-foreground">{item.subtitle}</span>
                ) : null}
              </span>
            </div>
          ))
        )}
      </div>
    </div>
  )
}

/** Skeleton shaped like a short list of mention candidates. */
function MentionOptionsSkeleton() {
  return (
    <div className="space-y-1 p-1" data-testid="mention-options-skeleton">
      {Array.from({ length: 3 }).map((_, index) => (
        <div key={index} className="flex items-center gap-2 px-2 py-1">
          <Skeleton className="size-6 shrink-0 rounded-full" />
          <Skeleton className="h-3 w-[60%]" />
        </div>
      ))}
    </div>
  )
}
