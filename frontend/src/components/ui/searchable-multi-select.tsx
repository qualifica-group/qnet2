import { useCallback, useId, useMemo, useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { ChevronDown, Search, X } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'

/** One selectable entry: an opaque value plus the label rendered verbatim. */
export interface SearchableMultiSelectOption {
  value: string
  label: string
  /**
   * The parent option's value in a hierarchical list: the option is indented
   * under it. Clicking a parent cycles: the parent alone, then the parent
   * with its whole subtree, then nothing. Absent/null = a root (or a flat
   * list). Options are expected in tree order.
   */
  parentValue?: string | null
}

/** Indent added per nesting level, on top of the row's own padding. */
const INDENT_REM_PER_DEPTH = 0.875

export interface SearchableMultiSelectLabels {
  /** Trigger text when nothing is selected. */
  placeholder: string
  /** Trigger pill when every option is selected. */
  allSelected: string
  /** Placeholder (and accessible name) of the in-popover search input. */
  searchPlaceholder: string
  /** Tri-state control over the options currently listed (the search matches). */
  selectAll: string
  /** Shown when the search term matches no option. */
  noMatch: string
  /** Footer action that empties the selection. */
  clear: string
  /** Footer counter, e.g. "3 of 12 selected". */
  count: (selected: number, total: number) => string
  /** Tooltip of a parent picked without its whole subtree: a second click adds it. */
  includeChildrenHint?: string
}

interface SearchableMultiSelectProps {
  options: SearchableMultiSelectOption[]
  value: string[]
  onChange: (next: string[]) => void
  labels: SearchableMultiSelectLabels
  disabled?: boolean
  className?: string
  /**
   * Forwarded to the trigger so `FormControl` (Radix `Slot`) can wire the
   * label association and the accessible error triad onto a real DOM node.
   */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/** Picked labels shown as pills in the trigger before the rest collapses into "+N". */
const TRIGGER_PILLS_MAX = 2

const TRIGGER_CLASS =
  'group flex min-h-8 w-full items-center gap-1.5 rounded-lg border border-field-border bg-field px-2 py-1 text-xs shadow-xs transition-[color,box-shadow,border-color] outline-none hover:border-ring/60 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=open]:border-ring data-[state=open]:ring-[3px] data-[state=open]:ring-ring/30 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40'
const PILL_CLASS =
  'inline-flex max-w-40 min-w-0 items-center rounded-md bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary ring-1 ring-inset ring-primary/15'
const PILL_MUTED_CLASS =
  'inline-flex shrink-0 items-center rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground'
const CONTENT_CLASS =
  'z-50 flex w-(--radix-popover-trigger-width) min-w-64 flex-col overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95'
const SEARCH_ROW_CLASS = 'flex items-center gap-2 border-b px-2.5'
const SEARCH_INPUT_CLASS =
  'h-9 w-full min-w-0 bg-transparent text-xs outline-none placeholder:text-muted-foreground'
const LIST_CLASS = 'flex max-h-64 flex-col gap-px overflow-y-auto p-1'
const ROW_CLASS =
  'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-accent has-[:focus-visible]:bg-accent'
const FOOTER_CLASS = 'flex items-center justify-between gap-2 border-t bg-surface px-2.5 py-1.5'

interface OptionTree {
  depthOf: Map<string, number>
  descendantsOf: Map<string, string[]>
}

/** Depth and full subtree of every option, from the `parentValue` links. */
function buildOptionTree(options: SearchableMultiSelectOption[]): OptionTree {
  const known = new Set(options.map((option) => option.value))
  const childrenOf = new Map<string, string[]>()
  for (const option of options) {
    if (option.parentValue != null && known.has(option.parentValue)) {
      childrenOf.set(option.parentValue, [...(childrenOf.get(option.parentValue) ?? []), option.value])
    }
  }

  const depthOf = new Map<string, number>()
  const descendantsOf = new Map<string, string[]>()
  const walk = (value: string, depth: number, seen: Set<string>): string[] => {
    depthOf.set(value, depth)
    const below: string[] = []
    for (const child of childrenOf.get(value) ?? []) {
      if (!seen.has(child)) {
        seen.add(child)
        below.push(child, ...walk(child, depth + 1, seen))
      }
    }
    descendantsOf.set(value, below)
    return below
  }
  for (const option of options) {
    if (option.parentValue == null || !known.has(option.parentValue)) {
      walk(option.value, 0, new Set([option.value]))
    }
  }

  return { depthOf, descendantsOf }
}

/** Tri-state of the "select all" control over the listed options. */
function selectAllState(selected: Set<string>, listed: string[]): boolean | 'indeterminate' {
  const pickedCount = listed.filter((value) => selected.has(value)).length
  if (listed.length === 0 || pickedCount === 0) return false
  return pickedCount === listed.length ? true : 'indeterminate'
}

interface TriggerSummaryProps {
  options: SearchableMultiSelectOption[]
  selected: Set<string>
  labels: SearchableMultiSelectLabels
}

/** Nothing picked -> placeholder; everything -> one pill; otherwise the first picks as pills plus "+N". */
function TriggerSummary({ options, selected, labels }: TriggerSummaryProps) {
  const picked = options.filter((option) => selected.has(option.value))

  if (picked.length === 0) {
    return <span className="truncate text-muted-foreground">{labels.placeholder}</span>
  }

  if (picked.length === options.length) {
    return <span className={PILL_CLASS}>{labels.allSelected}</span>
  }

  const rest = picked.length - TRIGGER_PILLS_MAX

  return (
    <>
      {picked.slice(0, TRIGGER_PILLS_MAX).map((option) => (
        <span key={option.value} className={PILL_CLASS} title={option.label}>
          <span className="truncate">{option.label}</span>
        </span>
      ))}
      {rest > 0 ? <span className={PILL_MUTED_CLASS}>+{rest}</span> : null}
    </>
  )
}

/**
 * Client-side searchable multi-select: the many-values sibling of
 * `SearchableSelect`. The trigger shows the picks as pills; the popover holds
 * a search field, a tri-state "select all", one checkbox row per option and a
 * footer with the counter and a clear action. It stays open while toggling so
 * several values can be picked at once.
 *
 * "Select all" acts on the options the search currently lists, so typing a
 * term and ticking it picks exactly the matches. The emitted value keeps the
 * options' declared order. Domain-agnostic: labels arrive translated and
 * option labels are rendered as-is.
 */
export function SearchableMultiSelect({
  options,
  value,
  onChange,
  labels,
  disabled,
  className,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: SearchableMultiSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const listId = useId()
  const selected = useMemo(() => new Set(value), [value])

  const listedOptions = useMemo(() => {
    const term = search.trim().toLowerCase()
    return term === '' ? options : options.filter((option) => option.label.toLowerCase().includes(term))
  }, [options, search])
  const listedValues = listedOptions.map((option) => option.value)
  const allState = selectAllState(selected, listedValues)
  const pickedCount = options.filter((option) => selected.has(option.value)).length
  const tree = useMemo(() => buildOptionTree(options), [options])

  const emit = (next: Set<string>) => {
    onChange(options.map((option) => option.value).filter((optionValue) => next.has(optionValue)))
  }

  // A leaf toggles. A parent cycles on each click: itself only, then its
  // whole subtree too, then nothing (user directive 2026-09-18).
  const toggle = (optionValue: string) => {
    const descendants = tree.descendantsOf.get(optionValue) ?? []
    const next = new Set(selected)

    if (!selected.has(optionValue)) {
      next.add(optionValue)
    } else if (descendants.some((descendant) => !selected.has(descendant))) {
      descendants.forEach((descendant) => next.add(descendant))
    } else {
      next.delete(optionValue)
      descendants.forEach((descendant) => next.delete(descendant))
    }

    emit(next)
  }

  /**
   * A parent picked WITHOUT its whole subtree shows "–" (a second click adds
   * the subtree, user directive 2026-09-18); the tick means the parent and
   * every descendant.
   */
  const isPartialParent = (optionValue: string): boolean =>
    selected.has(optionValue) &&
    (tree.descendantsOf.get(optionValue) ?? []).some((descendant) => !selected.has(descendant))

  const rowState = (optionValue: string): boolean | 'indeterminate' => {
    if (!selected.has(optionValue)) return false
    return isPartialParent(optionValue) ? 'indeterminate' : true
  }

  const toggleListed = () => {
    const next = new Set(selected)
    for (const listedValue of listedValues) {
      if (allState === true) next.delete(listedValue)
      else next.add(listedValue)
    }
    emit(next)
  }

  // The search resets on close, so the popover always reopens on the full list.
  const handleOpenChange = useCallback((next: boolean) => {
    setOpen(next)
    if (!next) setSearch('')
  }, [])

  // Inside a modal sheet/dialog the popup is portalled back into its content
  // node, so scrolling the list is not blocked as "outside" the modal.
  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    const modalContent = node?.closest('[data-slot="sheet-content"], [data-slot="dialog-content"]')
    setPortalContainer(modalContent instanceof HTMLElement ? modalContent : null)
  }, [])

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={handleOpenChange}>
      <PopoverPrimitive.Trigger asChild>
        <button
          ref={setTrigger}
          type="button"
          id={id}
          disabled={disabled}
          aria-haspopup="dialog"
          aria-expanded={open}
          aria-controls={listId}
          aria-describedby={ariaDescribedBy}
          aria-invalid={ariaInvalid}
          className={cn(TRIGGER_CLASS, className)}
        >
          <span className="flex min-w-0 flex-1 items-center gap-1 overflow-hidden text-left">
            <TriggerSummary options={options} selected={selected} labels={labels} />
          </span>
          <ChevronDown
            className="size-3.5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
            aria-hidden="true"
          />
        </button>
      </PopoverPrimitive.Trigger>

      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={6}
          className={CONTENT_CLASS}
          onOpenAutoFocus={(event) => event.preventDefault()}
        >
          <div className={SEARCH_ROW_CLASS}>
            <Search className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={labels.searchPlaceholder}
              aria-label={labels.searchPlaceholder}
              aria-controls={listId}
              className={SEARCH_INPUT_CLASS}
            />
            {search !== '' ? (
              <button
                type="button"
                onClick={() => setSearch('')}
                aria-label={labels.clear}
                className="rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
              >
                <X className="size-3.5" aria-hidden="true" />
              </button>
            ) : null}
          </div>

          <div id={listId} className={LIST_CLASS}>
            {listedOptions.length === 0 ? (
              <p className="px-2 py-6 text-center text-xs text-muted-foreground">{labels.noMatch}</p>
            ) : (
              <>
                <label className={cn(ROW_CLASS, 'font-medium text-foreground')}>
                  <Checkbox checked={allState} onCheckedChange={toggleListed} />
                  {labels.selectAll}
                </label>
                <div role="separator" className="mx-2 my-0.5 h-px bg-border/60" />
                {listedOptions.map((option) => {
                  const isSelected = selected.has(option.value)
                  const depth = tree.depthOf.get(option.value) ?? 0
                  const descendants = tree.descendantsOf.get(option.value) ?? []

                  return (
                    <label
                      key={option.value}
                      className={cn(ROW_CLASS, isSelected ? 'text-foreground' : 'text-muted-foreground')}
                      style={depth > 0 ? { paddingLeft: `${0.5 + INDENT_REM_PER_DEPTH * depth}rem` } : undefined}
                      title={isPartialParent(option.value) ? labels.includeChildrenHint : undefined}
                    >
                      <Checkbox checked={rowState(option.value)} onCheckedChange={() => toggle(option.value)} />
                      <span className="min-w-0 flex-1 truncate">{option.label}</span>
                      {descendants.length > 0 ? (
                        <span aria-hidden="true" className="shrink-0 text-[11px] tabular-nums text-muted-foreground">
                          {descendants.filter((descendant) => selected.has(descendant)).length}/{descendants.length}
                        </span>
                      ) : null}
                    </label>
                  )
                })}
              </>
            )}
          </div>

          <div className={FOOTER_CLASS}>
            <span className="text-[11px] tabular-nums text-muted-foreground">
              {labels.count(pickedCount, options.length)}
            </span>
            <button
              type="button"
              onClick={() => onChange([])}
              disabled={pickedCount === 0}
              className="rounded-sm text-[11px] font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[2px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-40"
            >
              {labels.clear}
            </button>
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}
