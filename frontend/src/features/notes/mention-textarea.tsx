import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
  type KeyboardEvent,
  type SyntheticEvent,
} from 'react'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages } from '@/features/for-select/use-for-select'
import { useMentionableUsers } from '@/features/notes/use-mentionable-users'
import { MentionPickerPanel } from '@/features/notes/mention-picker-panel'
import {
  extractMentionIds,
  parseMentionRefs,
  toDisplayText,
  toWireBody,
} from '@/features/notes/mention-tokens'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Matches an active `@query` right before the caret: an `@` at the start of
 * the text or preceded by whitespace, followed by a run of non-space
 * characters up to the caret. No match = no picker (closed on space/newline
 * or once the caret leaves the token).
 */
const MENTION_TRIGGER_PATTERN = /(?:^|\s)@([^\s@]*)$/

export interface MentionTextareaProps {
  value: string
  /** Emits the updated body and the FULL set of mentioned ids currently present in the body. */
  onChange: (value: string, mentions: number[]) => void
  /**
   * The candidate just inserted. The body tokens keep only `{name, id}`, so
   * this is the composer's only chance to learn the picked user's avatar and
   * render the same one the rest of the app shows.
   */
  onMentionPicked?: (item: ForSelectItem) => void
  entityType: string
  entityId: number
  placeholder?: string
  disabled?: boolean
  id?: string
  rows?: number
  autoFocus?: boolean
  'aria-invalid'?: boolean
  'aria-describedby'?: string
}

/**
 * `Textarea` with an inline `@mention` autocomplete (AC-072), built on the
 * popup-less pattern of `searchable-select.tsx` / `async-paginated-select.tsx`
 * (no `popover`/`cmdk` dependency): a `role="combobox"` textarea drives a
 * `role="listbox"` panel positioned under it via `aria-activedescendant`,
 * rather than a Radix Popover, so arrow keys never leave the field.
 *
 * The candidate list comes from `useMentionableUsers` (D-10, contextual to
 * `entityType`/`entityId`), never `users/for-select`. Selecting a candidate
 * inserts the frozen token format (D-12) and `onChange` always re-derives
 * `mentions` from the tokens actually present in the body, so a token deleted
 * by hand drops out of the array on its own.
 */
export function MentionTextarea({
  value,
  onChange,
  onMentionPicked,
  entityType,
  entityId,
  placeholder,
  disabled,
  id,
  rows,
  autoFocus,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
}: MentionTextareaProps) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [triggerStart, setTriggerStart] = useState<number | null>(null)
  const [activeIndex, setActiveIndex] = useState(0)
  const listboxId = useId()
  const textareaRef = useRef<HTMLTextAreaElement | null>(null)
  // Caret position to restore once a token insertion's `value` prop round-trips
  // back from the parent (the DOM textarea only reflects the new text on the
  // next render, so the caret can't be set synchronously in the handler).
  const pendingCaretRef = useRef<number | null>(null)

  const debouncedQuery = useDebouncedValue(query)

  const { data, isPending } = useMentionableUsers({
    entityType,
    entityId,
    search: debouncedQuery,
    enabled: open,
  })

  const options = useMemo(() => flattenForSelectPages(data?.pages), [data?.pages])
  const activeOption = options[activeIndex] as ForSelectItem | undefined

  // The field shows `@Name`; the wire format (D-12) never reaches the screen.
  const mentionRefs = useMemo(() => parseMentionRefs(value), [value])
  const displayValue = useMemo(() => toDisplayText(value), [value])

  /** Re-tokenizes an edited display text and pushes it up in the wire format. */
  function emitDisplayText(nextDisplay: string, refs = mentionRefs) {
    const nextValue = toWireBody(nextDisplay, refs)
    onChange(nextValue, extractMentionIds(nextValue))
  }

  useEffect(() => {
    if (pendingCaretRef.current === null) {
      return
    }
    const caret = pendingCaretRef.current
    pendingCaretRef.current = null
    const textarea = textareaRef.current
    textarea?.focus()
    textarea?.setSelectionRange(caret, caret)
  }, [value])

  function closePicker() {
    setOpen(false)
    setTriggerStart(null)
    setActiveIndex(0)
  }

  /** Recomputes the active `@query` (if any) from the text up to the caret. */
  function updateTrigger(text: string, caret: number | null) {
    if (caret === null) {
      closePicker()
      return
    }
    const match = MENTION_TRIGGER_PATTERN.exec(text.slice(0, caret))
    if (!match) {
      closePicker()
      return
    }
    const nextQuery = match[1]
    // A genuinely new search term restarts highlighting at the top option;
    // set here (an event handler, not an effect) so it lands in the same
    // render pass as the query/open update instead of cascading a second one.
    if (nextQuery !== query) {
      setActiveIndex(0)
    }
    setTriggerStart(caret - nextQuery.length - 1)
    setQuery(nextQuery)
    setOpen(true)
  }

  function handleChange(event: ChangeEvent<HTMLTextAreaElement>) {
    const nextDisplay = event.target.value
    emitDisplayText(nextDisplay)
    updateTrigger(nextDisplay, event.target.selectionStart)
  }

  // Covers caret moves that don't fire `onChange`: clicks and arrow-key
  // navigation outside the picker's own up/down handling below.
  function handleSelect(event: SyntheticEvent<HTMLTextAreaElement>) {
    updateTrigger(event.currentTarget.value, event.currentTarget.selectionStart)
  }

  function selectOption(item: ForSelectItem) {
    const textarea = textareaRef.current
    if (triggerStart === null || !textarea) {
      return
    }
    const caret = textarea.selectionStart ?? triggerStart
    const insertion = `@${item.label} `
    const nextDisplay = `${displayValue.slice(0, triggerStart)}${insertion}${displayValue.slice(caret)}`
    pendingCaretRef.current = triggerStart + insertion.length
    // The picked user is not among the body's previous refs yet, hence the extra entry.
    emitDisplayText(nextDisplay, [...mentionRefs, { id: item.id, name: item.label }])
    onMentionPicked?.(item)
    closePicker()
  }

  /** Inserts the currently highlighted candidate (the first one until the user moves). */
  function confirmActiveOption() {
    if (!activeOption) {
      return
    }
    selectOption(activeOption)
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (!open || options.length === 0) {
      return
    }
    // Tab confirms the highlighted candidate exactly like Enter (the first one
    // by default): with the picker open it completes the mention instead of
    // moving focus, the editor-style behaviour expected from an autocomplete.
    if (event.key === 'Enter' || event.key === 'Tab') {
      event.preventDefault()
      confirmActiveOption()
      return
    }
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        setActiveIndex((index) => (index + 1) % options.length)
        break
      case 'ArrowUp':
        event.preventDefault()
        setActiveIndex((index) => (index - 1 + options.length) % options.length)
        break
      case 'Escape':
        event.preventDefault()
        closePicker()
        break
      default:
        break
    }
  }

  const optionId = (userId: number) => `${listboxId}-option-${userId}`

  return (
    <div
      className={cn(
        // Neutral throughout: the focus state only deepens the same grey border
        // instead of switching to the primary blue, so the field, the picker and
        // the note bubbles read as one light surface family.
        'relative rounded-lg border border-muted-foreground/20 bg-white px-2 py-1.5 shadow-xs transition-colors dark:bg-muted/40',
        'focus-within:border-muted-foreground/35 focus-within:ring-2 focus-within:ring-muted-foreground/10',
        ariaInvalid && 'border-destructive/50 focus-within:ring-destructive/10',
        disabled && 'opacity-70',
      )}
    >
      <Textarea
        ref={textareaRef}
        id={id}
        value={displayValue}
        onChange={handleChange}
        onSelect={handleSelect}
        onKeyDown={handleKeyDown}
        onBlur={closePicker}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        autoFocus={autoFocus}
        // The wrapper above owns the border and the focus ring, so the field blends into it.
        className="resize-none border-0 bg-transparent px-1 py-0.5 shadow-none focus-visible:ring-0 dark:bg-transparent"
        aria-invalid={ariaInvalid}
        aria-describedby={ariaDescribedBy}
        role="combobox"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-activedescendant={open && activeOption ? optionId(activeOption.id) : undefined}
      />

      {open ? (
        <MentionPickerPanel
          listboxId={listboxId}
          options={options}
          isPending={isPending}
          activeIndex={activeIndex}
          onActiveIndexChange={setActiveIndex}
          onSelect={selectOption}
          optionId={optionId}
        />
      ) : null}
    </div>
  )
}
