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

export interface AsyncPaginatedSelectLabels {
  /** Shown in the trigger when nothing is selected. */
  placeholder: string
  /** Placeholder of the search input. */
  searchPlaceholder: string
  /** Shown when the query succeeds but returns no options. */
  empty: string
  /** Shown when the query fails. */
  error: string
  /** Aria-label for the trigger's clear button (entity label is appended). */
  clearLabel: string
  /** Aria-label for the trigger button. */
  triggerLabel: string
  /** Retry action shown when the query fails. */
  retry: string
}

interface AsyncPaginatedSelectProps {
  /** Resource segment of the endpoint, e.g. `users` → `/users/for-select`. */
  resource: string
  /** Currently selected id, or `null` when nothing is selected (controlled). */
  value: number | null
  /** Called with the next selection: a new id replaces the current one, `null` clears it. */
  onChange: (value: number | null) => void
  /**
   * Optional sibling of `onChange` that also exposes the full selected
   * `ForSelectItem` (including `meta`) — lets a caller auto-fill a dependent
   * field from a resource-specific presentation bag (e.g. the Lead form
   * prefilling Regione from a Sede's `meta.state_id`). Fired alongside
   * `onChange` on pick/clear; omitted, callers keep working with just the id.
   */
  onItemChange?: (item: ForSelectItem | null) => void
  /**
   * Already-known item for the selected id, used to render the trigger's label
   * when it is not on the current page (edit-mode hydration). Overridden by
   * whatever the query returns once loaded.
   */
  selectedItem?: ForSelectItem | null
  labels: AsyncPaginatedSelectLabels
  /**
   * When set, the trigger and every option render an avatar (the item's
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
   * as repeated `key[]=` params (Laravel convention) — matches the type
   * already accepted by `useForSelect`/`fetchForSelect` and by the
   * `AsyncPaginatedMultiSelect` sibling.
   */
  params?: Record<string, string | number | string[] | number[]>
}

/**
 * Single-select sibling of {@link AsyncPaginatedMultiSelect} (ADR 0011): same
 * async, paginated, server-searched popup fed by {@link useForSelect}, but the
 * trigger holds exactly one value instead of a badge list. Picking an option
 * REPLACES the current selection and closes the popup; a dedicated clear
 * affordance empties it. Kept as its own component (rather than a `single`
 * mode on the multi-select) because the trigger/value semantics differ enough
 * that branching them in one component would out-cost the shared ~15 lines of
 * JSX — the only real duplication is the option row and popup chrome, which
 * both components independently keep small and readable.
 *
 * No HTTP lives here — data flows through the for-select hook. The component
 * owns only UI state (open, search term).
 */
export function AsyncPaginatedSelect({
  resource,
  value,
  onChange,
  onItemChange,
  selectedItem = null,
  labels,
  showAvatar = false,
  disabled,
  className,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
  action,
  params,
}: AsyncPaginatedSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const debouncedSearch = useDebouncedValue(search.trim())
  const listboxId = useId()
  const scrollRef = useRef<HTMLDivElement>(null)
  const observerRef = useRef<IntersectionObserver | null>(null)

  // The selected value's trigger label is resolved by a dedicated ids-keyed
  // query (collision-free across sibling selects), not by the shared paginated
  // list — so it never falls back to `#id` when another instance's ids won the
  // shared cache entry. Skipped when the hydration prop already knows the label.
  const missingSelectedItem = value !== null && selectedItem?.id !== value
  const hydratedLabels = useForSelectLabels({
    resource,
    ids: missingSelectedItem ? [value as number] : [],
    enabled: missingSelectedItem,
    params,
  })

  const query = useForSelect({
    resource,
    search: debouncedSearch,
    ids: value !== null ? [value] : undefined,
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

  // The loaded page wins over the resolved sources (freshest data); the
  // hydration prop and the ids-keyed label query fill the gap before, or
  // independently of, the paginated list.
  const selected = useMemo(() => {
    if (value === null) {
      return null
    }
    return (
      options.find((item) => item.id === value) ??
      hydratedLabels.get(value) ??
      (selectedItem?.id === value ? selectedItem : null)
    )
  }, [options, hydratedLabels, selectedItem, value])

  // Keep the latest paging state in a ref so the sentinel's callback ref can
  // read fresh values without re-attaching the observer on every render.
  const pagingRef = useRef({ hasNextPage, isFetchingNextPage, fetchNextPage })
  pagingRef.current = { hasNextPage, isFetchingNextPage, fetchNextPage }

  // Callback ref on the bottom sentinel: attaches an IntersectionObserver
  // exactly when the sentinel mounts (deferred until the popover portal
  // opens) and disconnects when it unmounts.
  const setSentinel = useCallback((node: HTMLDivElement | null) => {
    observerRef.current?.disconnect()
    observerRef.current = null
    if (!node) {
      return
    }
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

  const select = (item: ForSelectItem) => {
    onChange(item.id)
    onItemChange?.(item)
    setOpen(false)
  }

  const clear = () => {
    onChange(null)
    onItemChange?.(null)
  }

  const triggerLabel = selected?.label ?? (value !== null ? `#${value}` : null)

  const trigger = (
    <PopoverPrimitive.Trigger asChild>
      <button
        ref={setTrigger}
        type="button"
        id={id}
        role="combobox"
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
        {triggerLabel !== null ? (
          <span className="flex flex-1 items-center gap-2 overflow-hidden">
            {showAvatar ? (
              <UserAvatar
                name={triggerLabel}
                src={selected?.avatar_url}
                className="size-6 shrink-0 text-xs"
              />
            ) : null}
            <span className="truncate">{triggerLabel}</span>
          </span>
        ) : (
          <span className="flex-1 truncate text-left text-muted-foreground">
            {labels.placeholder}
          </span>
        )}
        <span className="flex shrink-0 items-center gap-1">
          {triggerLabel !== null ? (
            <span
              role="button"
              tabIndex={0}
              aria-label={`${labels.clearLabel} ${triggerLabel}`}
              className="rounded-sm p-0.5 outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
              onClick={(event) => {
                event.stopPropagation()
                clear()
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault()
                  event.stopPropagation()
                  clear()
                }
              }}
            >
              <X className="size-3.5" aria-hidden="true" />
            </span>
          ) : null}
          <ChevronsUpDown
            className="size-4 shrink-0 opacity-50"
            aria-hidden="true"
          />
        </span>
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
                  const checked = item.id === value
                  return (
                    <div
                      key={item.id}
                      role="option"
                      aria-selected={checked}
                      tabIndex={0}
                      onClick={() => select(item)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          select(item)
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
    <div className="space-y-1 p-1" data-testid="async-select-skeleton">
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
