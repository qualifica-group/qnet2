import { useCallback, useId, useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Loader2, Plus } from 'lucide-react'
import { buttonVariants } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages } from '@/features/for-select/use-for-select'
import { fetchRewardType } from '@/features/reward-types/api'
import { useRewardTypesForSelect } from '@/features/reward-types/for-select-api'
import { RewardChipList, type RewardChipListItem } from '@/features/rewards/reward-chip-list'
import type { RewardAssignmentRef, RewardTypeRef } from '@/features/rewards/types'

/** The RHF field's element shape (spec 0059 §4): only the type id travels, the beneficiary/date are server-derived. */
export interface RewardAssignmentValue {
  reward_type_id: number
}

interface RewardAssignmentFieldProps {
  /** Current RHF `rewards` field value. */
  value: RewardAssignmentValue[]
  onChange: (next: RewardAssignmentValue[]) => void
  /**
   * The record's persisted assignments (edit mode; `[]` on create), seeding
   * every already-hydrated chip's name/color without a fetch — a reward type
   * newly picked THIS session is resolved once, at pick time, and cached the
   * same way (see `handlePick`).
   */
  initialAssignments: RewardAssignmentRef[]
  /** D-3: the beneficiary is always the current reporter, never chosen here; `null` disables the add control. */
  reporterId: number | null
  fieldLabel: string
  disabledHint: string
  addLabel: string
  removeLabel: (rewardTypeName: string) => string
  searchPlaceholder: string
  emptyLabel: string
  errorLabel: string
  retryLabel: string
  loadMoreLabel: string
  className?: string
}

/**
 * The "abbinamento buono" control (spec 0059 D-3): a row of `RewardChip`s
 * plus a compact add popover, shared verbatim by the Opportunity form and the
 * Gestione Richiesta work panel's attribution section so the two never drift.
 *
 * Not `AsyncPaginatedSelect` (components/ui/, out of this feature's
 * ownership): that component shows ONE persisted selection in its trigger,
 * but this control is always an "add" affordance whose own picks live in
 * `RewardChipList` instead — and it must exclude already-assigned types from
 * the list (no reproposing a duplicate, which the backend would reject with
 * a `distinct` 422). Reuses `useRewardTypesForSelect` (the module's for-select
 * hook) for the data and the same Sheet/Dialog portal targeting technique as
 * `AsyncPaginatedSelect`, kept small since it never paginates a persisted
 * value or shows avatars.
 */
export function RewardAssignmentField({
  value,
  onChange,
  initialAssignments,
  reporterId,
  fieldLabel,
  disabledHint,
  addLabel,
  removeLabel,
  searchPlaceholder,
  emptyLabel,
  errorLabel,
  retryLabel,
  loadMoreLabel,
  className,
}: RewardAssignmentFieldProps) {
  const [resolved, setResolved] = useState<Map<number, RewardTypeRef>>(
    () => new Map(initialAssignments.map((assignment) => [assignment.reward_type.id, assignment.reward_type])),
  )
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [pendingId, setPendingId] = useState<number | null>(null)
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const debouncedSearch = useDebouncedValue(search.trim())
  const listboxId = useId()
  const hintId = useId()

  const disabled = reporterId === null
  const assignedIds = new Set(value.map((assignment) => assignment.reward_type_id))

  const query = useRewardTypesForSelect({ search: debouncedSearch, enabled: open })
  const options = flattenForSelectPages(query.data?.pages).filter((item) => !assignedIds.has(item.id))

  // Portals the popup back into the enclosing Sheet/Dialog content (if any),
  // mirroring `AsyncPaginatedSelect`, so wheel/touch scrolling stays inside
  // the modal's allowed scroll tree instead of being blocked as "outside".
  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    if (!node) {
      setPortalContainer(null)
      return
    }
    const modalContent = node.closest('[data-slot="sheet-content"], [data-slot="dialog-content"]')
    setPortalContainer(modalContent instanceof HTMLElement ? modalContent : null)
  }, [])

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    if (!next) {
      setSearch('')
    }
  }

  const handlePick = async (id: number) => {
    setPendingId(id)
    try {
      const type = await fetchRewardType(id)
      setResolved((previous) => new Map(previous).set(type.id, { id: type.id, name: type.name, color: type.color }))
      onChange([...value, { reward_type_id: id }])
      handleOpenChange(false)
    } finally {
      setPendingId(null)
    }
  }

  const handleRemove = (id: number) => {
    onChange(value.filter((assignment) => assignment.reward_type_id !== id))
  }

  // Every id in `value` was either seeded from `initialAssignments` or added
  // through `handlePick` above, both of which populate `resolved` before the
  // id ever reaches `value` — so a missing entry here cannot happen, but the
  // guard keeps a render defensive rather than throwing on a stale prop.
  const items: RewardChipListItem[] = value.flatMap(({ reward_type_id: rewardTypeId }) => {
    const rewardType = resolved.get(rewardTypeId)
    if (!rewardType) {
      return []
    }
    return disabled
      ? [{ id: rewardTypeId, rewardType }]
      : [{ id: rewardTypeId, rewardType, onRemove: () => handleRemove(rewardTypeId), removeLabel: removeLabel(rewardType.name) }]
  })

  const action = (
    <PopoverPrimitive.Root open={open} onOpenChange={handleOpenChange}>
      <PopoverPrimitive.Trigger asChild>
        <button
          ref={setTrigger}
          type="button"
          disabled={disabled}
          aria-describedby={disabled ? hintId : undefined}
          className={cn(buttonVariants({ variant: 'outline', size: 'xs' }))}
        >
          <Plus className="size-3" aria-hidden="true" />
          {addLabel}
        </button>
      </PopoverPrimitive.Trigger>

      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={4}
          className="z-50 w-64 rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
          onOpenAutoFocus={(event) => event.preventDefault()}
        >
          <div className="p-1">
            <Input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
              aria-controls={listboxId}
            />
          </div>
          <div id={listboxId} role="listbox" className="max-h-56 overflow-y-auto p-1">
            {query.isPending ? (
              <div className="flex items-center justify-center py-4">
                <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
              </div>
            ) : query.isError ? (
              <div className="flex flex-col items-center gap-2 px-2 py-4 text-center">
                <p className="text-xs text-muted-foreground">{errorLabel}</p>
                <button
                  type="button"
                  onClick={() => void query.refetch()}
                  className="text-xs font-medium text-primary underline-offset-4 hover:underline"
                >
                  {retryLabel}
                </button>
              </div>
            ) : options.length === 0 ? (
              <p className="px-2 py-4 text-center text-xs text-muted-foreground">{emptyLabel}</p>
            ) : (
              <>
                {options.map((item) => (
                  <div
                    key={item.id}
                    role="option"
                    aria-selected={false}
                    tabIndex={0}
                    onClick={() => void handlePick(item.id)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        void handlePick(item.id)
                      }
                    }}
                    className="flex cursor-pointer items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-xs outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50"
                  >
                    <span className="truncate">{item.label}</span>
                    {pendingId === item.id ? (
                      <Loader2 className="size-3.5 shrink-0 animate-spin" aria-hidden="true" />
                    ) : null}
                  </div>
                ))}
                {query.hasNextPage ? (
                  <button
                    type="button"
                    onClick={() => void query.fetchNextPage()}
                    disabled={query.isFetchingNextPage}
                    className="w-full py-1.5 text-center text-xs font-medium text-primary hover:underline disabled:opacity-50"
                  >
                    {query.isFetchingNextPage ? (
                      <Loader2 className="mx-auto size-3.5 animate-spin" aria-hidden="true" />
                    ) : (
                      loadMoreLabel
                    )}
                  </button>
                ) : null}
              </>
            )}
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )

  return (
    <div className={cn('flex flex-col gap-1', className)}>
      <span className="text-xs font-medium text-muted-foreground">{fieldLabel}</span>
      <RewardChipList items={items} action={action} />
      {disabled ? (
        <p id={hintId} className="text-xs text-muted-foreground">
          {disabledHint}
        </p>
      ) : null}
    </div>
  )
}
