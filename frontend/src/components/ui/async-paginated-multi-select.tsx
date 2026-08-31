import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, ChevronsUpDown, Loader2, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import {
  flattenForSelectPages,
  useForSelect,
  useForSelectLabels,
} from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'

export interface AsyncPaginatedMultiSelectLabels {
  /** Shown in the trigger when nothing is selected. */
  placeholder: string
  /** Placeholder of the search input. */
  searchPlaceholder: string
  /** Shown when the query succeeds but returns no options. */
  empty: string
  /** Shown when the query fails. */
  error: string
  /** Aria-label for a badge's remove button (entity label is appended). */
  removeLabel: string
  /** Aria-label for the trigger button. */
  triggerLabel: string
  /** Retry action shown when the query fails. */
  retry: string
}

interface AsyncPaginatedMultiSelectProps {
  /** Resource segment of the endpoint, e.g. `users` → `/users/for-select`. */
  resource: string
  /** Currently selected ids (controlled). */
  value: number[]
  /** Called with the next selection whenever an option is toggled/removed. */
  onChange: (value: number[]) => void
  /**
   * Already-known items for the selected ids, used to render badge labels for
   * selections that are not on the current page (edit-mode hydration). Merged
   * with whatever the query returns; the query's `ids[]` hydration backfills the
   * rest.
   */
  selectedItems?: ForSelectItem[]
  labels: AsyncPaginatedMultiSelectLabels
  /**
   * When set, every option and selected badge renders an avatar (the item's
   * `avatar_url`, falling back to the label's initials). Opt-in so non-avatar
   * selects stay text-only.
   */
  showAvatar?: boolean
  disabled?: boolean
  className?: string
  /**
   * Forwarded to the trigger button so `FormControl` (Radix `Slot`) can wire up
   * the label association and the accessible error triad: `Slot` clones its
   * `id`/`aria-describedby`/`aria-invalid` onto this component's props, but a
   * plain function component does not auto-spread onto its internal DOM node
   * the way a native `<input>` or a Radix primitive would.
   */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
  /**
   * Optional slot rendered inline next to the trigger (e.g. a quick-create
   * icon-button). Domain-agnostic: this component only reserves the layout
   * space, it has no knowledge of what the action does. Omitted, the trigger
   * renders exactly as before (no extra wrapper node).
   */
  action?: ReactNode
  /**
   * Extra, resource-specific query parameters forwarded to the for-select
   * request (spec 0032 `dependency.param`: a parent filter's current value).
   * Changing it starts a fresh paginated query. An array value is serialized
   * as repeated `key[]=` params (Laravel convention).
   */
  params?: Record<string, string | number | string[] | number[]>
}

/**
 * Reusable async, paginated, server-searched multi-select (ADR 0011). Renders the
 * current selection as removable badges and opens a searchable popup whose option
 * list is fed by {@link useForSelect}: debounced server search, infinite scroll
 * (IntersectionObserver sentinel), a skeleton loading state (never a spinner for
 * the initial load), and the loading / error / empty triad. Generic over any
 * `/api/{resource}/for-select` endpoint; keep the static `MultiSelect` for
 * in-memory option lists.
 *
 * No HTTP lives here — data flows through the for-select hook. The component owns
 * only UI state (open, search term).
 */
export function AsyncPaginatedMultiSelect({
  resource,
  value,
  onChange,
  selectedItems = [],
  labels,
  showAvatar = false,
  disabled,
  className,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
  action,
  params,
}: AsyncPaginatedMultiSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const debouncedSearch = useDebouncedValue(search.trim())
  const listboxId = useId()
  const scrollRef = useRef<HTMLDivElement>(null)
  const observerRef = useRef<IntersectionObserver | null>(null)
  // Ids whose label the hydration prop does not already cover. Resolved by the
  // dedicated ids-keyed query (collision-free across sibling selects), so a
  // selected badge never falls back to `#id` because another instance's ids won
  // the shared, ids-less list cache.
  const missingIds = useMemo(() => {
    const hydratedIds = new Set(selectedItems.map((item) => item.id))
    return value.filter((id) => !hydratedIds.has(id))
  }, [selectedItems, value])

  const hydratedLabels = useForSelectLabels({
    resource,
    ids: missingIds,
    enabled: missingIds.length > 0,
    params,
  })

  const query = useForSelect({
    resource,
    search: debouncedSearch,
    ids: value,
    enabled: open,
    params,
  })

  const {
    data,
    isPending,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
    refetch,
  } = query

  const options = useMemo(
    () => flattenForSelectPages(data?.pages),
    [data?.pages],
  )

  // Build a label lookup from the hydration prop, the ids-keyed label query, and
  // the loaded options so every selected badge resolves a label even before (or
  // independently of) its page in the paginated list.
  const labelById = useMemo(() => {
    const map = new Map<number, ForSelectItem>()
    for (const item of selectedItems) {
      map.set(item.id, item)
    }
    for (const item of hydratedLabels.values()) {
      map.set(item.id, item)
    }
    for (const item of options) {
      map.set(item.id, item)
    }
    return map
  }, [selectedItems, hydratedLabels, options])

  const selectedSet = useMemo(() => new Set(value), [value])

  // Keep the latest paging state in a ref so the sentinel's callback ref can read
  // fresh values without re-attaching the observer on every render.
  const pagingRef = useRef({ hasNextPage, isFetchingNextPage, fetchNextPage })
  pagingRef.current = { hasNextPage, isFetchingNextPage, fetchNextPage }

  // Callback ref on the bottom sentinel: attaches an IntersectionObserver exactly
  // when the sentinel mounts (which happens in a later commit when the popover
  // portal opens) and disconnects when it unmounts. Scoped to the option list
  // container so it only fires at the end of the list. This is more robust than
  // an effect keyed on refs, which would miss the deferred portal mount.
  const setSentinel = useCallback((node: HTMLDivElement | null) => {
    observerRef.current?.disconnect()
    observerRef.current = null
    if (!node) {
      return
    }
    // Root is the scroll container when already attached (parent refs attach
    // after this child ref in the same commit; null falls back to the viewport,
    // which still correctly detects the sentinel reaching the visible area).
    const observer = new IntersectionObserver(
      (entries) => {
        const { hasNextPage: more, isFetchingNextPage: loading, fetchNextPage: load } =
          pagingRef.current
        if (entries[0]?.isIntersecting && more && !loading) {
          void load()
        }
      },
      { root: scrollRef.current, rootMargin: '0px 0px 80px 0px' },
    )
    observer.observe(node)
    observerRef.current = observer
  }, [])

  // Disconnect the observer when the whole control unmounts.
  useEffect(() => () => observerRef.current?.disconnect(), [])

  // When the control lives inside a modal sheet/dialog, portal the popup back
  // into that content node so wheel/touch scrolling stays inside the modal's
  // allowed scroll tree instead of being blocked as "outside" content.
  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    if (!node) {
      setPortalContainer(null)
      return
    }

    const modalContent = node.closest(
      '[data-slot="sheet-content"], [data-slot="dialog-content"]',
    )

    setPortalContainer(
      modalContent instanceof HTMLElement ? modalContent : null,
    )
  }, [])

  const toggle = (id: number) => {
    onChange(
      selectedSet.has(id)
        ? value.filter((current) => current !== id)
        : [...value, id],
    )
  }

  const remove = (id: number) => {
    onChange(value.filter((current) => current !== id))
  }

  const trigger = (
    <PopoverPrimitive.Trigger asChild>
      <button
        ref={setTrigger}
        type="button"
        id={id}
        disabled={disabled}
        aria-label={labels.triggerLabel}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
        className={cn(
          'flex min-h-9 w-full items-center justify-between gap-2 rounded-md border border-field-border bg-field px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
          action ? 'min-w-0 flex-1' : null,
          className,
        )}
      >
        {value.length > 0 ? (
          <span className="flex flex-1 flex-wrap items-center gap-1">
            {value.map((id) => {
              const item = labelById.get(id)
              return (
                <Badge key={id} variant="secondary" className="gap-1 pl-1">
                  {showAvatar ? (
                    <UserAvatar
                      name={item?.label ?? `#${id}`}
                      src={item?.avatar_url}
                      className="size-4 text-[0.5rem]"
                    />
                  ) : null}
                  {item?.label ?? `#${id}`}
                  <span
                    role="button"
                    tabIndex={0}
                    aria-label={`${labels.removeLabel} ${item?.label ?? id}`}
                    className="rounded-sm outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
                    onClick={(event) => {
                      event.stopPropagation()
                      remove(id)
                    }}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        event.stopPropagation()
                        remove(id)
                      }
                    }}
                  >
                    <X className="size-3" aria-hidden="true" />
                  </span>
                </Badge>
              )
            })}
          </span>
        ) : (
          <span className="flex-1 text-left text-muted-foreground">
            {labels.placeholder}
          </span>
        )}
        <ChevronsUpDown
          className="size-4 shrink-0 opacity-50"
          aria-hidden="true"
        />
      </button>
    </PopoverPrimitive.Trigger>
  )

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      {action ? (
        <div className="flex items-center gap-1.5">
          {trigger}
          {action}
        </div>
      ) : (
        trigger
      )}

      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={4}
          className="z-50 w-(--radix-popover-trigger-width) rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
          onOpenAutoFocus={(event) => {
            // Keep focus on the search input rather than the first option.
            event.preventDefault()
          }}
        >
          <div className="p-1">
            <Input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={labels.searchPlaceholder}
              aria-label={labels.searchPlaceholder}
              aria-controls={listboxId}
            />
          </div>

          <div
            ref={scrollRef}
            id={listboxId}
            role="listbox"
            aria-multiselectable="true"
            className="max-h-64 overflow-y-auto p-1"
          >
            {isPending ? (
              <OptionsSkeleton showAvatar={showAvatar} />
            ) : isError ? (
              <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
                <p className="text-sm text-muted-foreground">{labels.error}</p>
                <button
                  type="button"
                  onClick={() => void refetch()}
                  className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                  {labels.retry}
                </button>
              </div>
            ) : options.length === 0 ? (
              <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                {labels.empty}
              </p>
            ) : (
              <>
                {options.map((item) => {
                  const checked = selectedSet.has(item.id)
                  return (
                    <div
                      key={item.id}
                      role="option"
                      aria-selected={checked}
                      tabIndex={0}
                      onClick={() => toggle(item.id)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          toggle(item.id)
                        }
                      }}
                      className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50"
                    >
                      <Check
                        className={cn(
                          'size-4 shrink-0',
                          checked ? 'opacity-100' : 'opacity-0',
                        )}
                        aria-hidden="true"
                      />
                      {showAvatar ? (
                        <UserAvatar
                          name={item.label}
                          src={item.avatar_url}
                          className="size-7 shrink-0 text-xs"
                        />
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
                })}
                {isFetchingNextPage ? (
                  <div className="flex items-center justify-center py-2">
                    <Loader2
                      className="size-4 animate-spin text-muted-foreground"
                      aria-hidden="true"
                    />
                  </div>
                ) : null}
                {hasNextPage ? (
                  <div ref={setSentinel} aria-hidden="true" />
                ) : null}
              </>
            )}
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}

/** Skeleton shaped like a short list of options. */
function OptionsSkeleton({ showAvatar = false }: { showAvatar?: boolean }) {
  return (
    <div className="space-y-1 p-1" data-testid="async-multi-select-skeleton">
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="flex items-center gap-2 px-2 py-1.5">
          <Skeleton className="size-4 shrink-0 rounded-sm" />
          {showAvatar ? (
            <Skeleton className="size-7 shrink-0 rounded-full" />
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
