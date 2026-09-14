import { Check } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import type { ForSelectItem } from '@/features/for-select/types'

interface AsyncSelectOptionRowProps {
  item: ForSelectItem
  checked: boolean
  /**
   * Visible but not selectable (spec 0123 D-5). Click/Enter/Space are a
   * no-op; the caller's `onSelect` is never invoked for this row.
   */
  disabled: boolean
  showAvatar: boolean
  onSelect: (item: ForSelectItem) => void
}

/**
 * Single row of {@link AsyncPaginatedSelect} / `AsyncPaginatedMultiSelect`'s
 * popup listbox. Extracted so the parent components stay under the file-size
 * limit — this carries no state of its own, it only renders what it is told.
 */
export function AsyncSelectOptionRow({
  item,
  checked,
  disabled,
  showAvatar,
  onSelect,
}: AsyncSelectOptionRowProps) {
  return (
    <div
      role="option"
      aria-selected={checked}
      aria-disabled={disabled}
      tabIndex={0}
      onClick={() => {
        if (!disabled) {
          onSelect(item)
        }
      }}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          if (!disabled) {
            onSelect(item)
          }
        }
      }}
      className={cn(
        'flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none',
        disabled
          ? 'cursor-not-allowed opacity-50'
          : 'cursor-pointer hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50',
      )}
    >
      <Check
        className={cn('size-4 shrink-0', checked ? 'opacity-100' : 'opacity-0')}
        aria-hidden="true"
      />
      {showAvatar ? (
        <UserAvatar name={item.label} src={item.avatar_url} className="shrink-0" />
      ) : null}
      <span className="flex min-w-0 flex-col">
        <span className="truncate">{item.label}</span>
        {item.subtitle ? (
          <span className="truncate text-xs text-muted-foreground">
            {item.subtitle}
          </span>
        ) : null}
      </span>
    </div>
  )
}

/** Skeleton shaped like a short list of options. */
export function OptionsSkeleton({ showAvatar = false }: { showAvatar?: boolean }) {
  return (
    <div className="space-y-1 p-1" data-testid="async-select-skeleton">
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="flex items-center gap-2 px-2 py-1.5">
          <Skeleton className="size-4 shrink-0 rounded-sm" />
          {showAvatar ? (
            <Skeleton className="size-8 shrink-0 rounded-full" />
          ) : null}
          <div className="flex-1 space-y-1.5">
            <Skeleton className="h-3.5 w-[55%]" />
            <Skeleton className="h-3 w-[75%]" />
          </div>
        </div>
      ))}
    </div>
  )
}
