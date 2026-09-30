import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react'
import type { ChangeEvent, ClipboardEvent, KeyboardEvent } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  availableSuggestions,
  isValidEmail,
  splitTokens,
  type EmailRecipientSuggestion,
} from '@/components/ui/email-recipients-input-utils'

export type { EmailRecipientSuggestion } from '@/components/ui/email-recipients-input-utils'

interface EmailRecipientsInputProps {
  value: string[]
  onChange: (value: string[]) => void
  /** Suggestion pool (already excludes nothing — this component excludes what's in `value` itself). */
  suggestions?: EmailRecipientSuggestion[]
  id?: string
  'aria-invalid'?: boolean
  'aria-describedby'?: string
  placeholder?: string
  disabled?: boolean
  /** Caps the number of chips this instance will ever commit (D-5: 50 total across to/cc/bcc). */
  maxItems?: number
  /** Aria-label prefix for a chip's remove button (the address is appended). */
  removeLabel: string
  /** Fired for each token rejected as an invalid address (not added), e.g. to surface a form-level message. */
  onInvalidInput?: (value: string) => void
  className?: string
}

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const NO_SUGGESTIONS: EmailRecipientSuggestion[] = []

/**
 * Reusable chip input for email addresses (spec 0175 D-5: the composer's
 * A/CC/CCN fields). Free-typed addresses are validated client-side
 * (`EMAIL_PATTERN`, a hint only — D-5's real gate is server-side `email:rfc`);
 * a suggestion pool (registry/referent contacts, work order team) narrows as
 * an editable combobox (WAI-ARIA: `aria-activedescendant`, not focusable
 * options — focus never leaves the text field).
 */
export function EmailRecipientsInput({
  value,
  onChange,
  suggestions = NO_SUGGESTIONS,
  id,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
  placeholder,
  disabled = false,
  maxItems,
  removeLabel,
  onInvalidInput,
  className,
}: EmailRecipientsInputProps) {
  const [inputValue, setInputValue] = useState('')
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(0)
  const [internalInvalid, setInternalInvalid] = useState(false)
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const anchorRef = useRef<HTMLDivElement | null>(null)
  const listboxId = useId()

  const pool = useMemo(() => availableSuggestions(suggestions, value), [suggestions, value])
  const term = inputValue.trim().toLowerCase()
  const filteredSuggestions = useMemo(
    () =>
      term === ''
        ? pool
        : pool.filter(
            (suggestion) =>
              suggestion.label.toLowerCase().includes(term) ||
              suggestion.email.toLowerCase().includes(term),
          ),
    [pool, term],
  )
  const popoverOpen = open && filteredSuggestions.length > 0

  useEffect(() => {
    setActiveIndex(0)
  }, [filteredSuggestions.length, term])

  // Portal the suggestion list back into an ancestor sheet/dialog (same reason
  // as SearchableSelect/AsyncPaginatedMultiSelect): wheel/touch scroll must
  // stay inside the modal's own allowed scroll tree.
  const setContainer = useCallback((node: HTMLDivElement | null) => {
    anchorRef.current = node
    if (!node) {
      setPortalContainer(null)
      return
    }
    const modalContent = node.closest('[data-slot="sheet-content"], [data-slot="dialog-content"]')
    setPortalContainer(modalContent instanceof HTMLElement ? modalContent : null)
  }, [])

  /** Adds every valid, not-yet-present token up to `maxItems`; reports the rest as invalid (unless kept as a work-in-progress fragment). */
  const commitTokens = useCallback(
    (tokens: string[], { keepLastInvalid = false } = {}): boolean => {
      const next = [...value]
      const seen = new Set(next)
      let hadInvalid = false

      tokens.forEach((token, index) => {
        if (maxItems !== undefined && next.length >= maxItems) {
          return
        }
        if (!isValidEmail(token)) {
          if (keepLastInvalid && index === tokens.length - 1) {
            return
          }
          hadInvalid = true
          onInvalidInput?.(token)
          return
        }
        if (seen.has(token)) {
          return
        }
        seen.add(token)
        next.push(token)
      })

      if (next.length !== value.length) {
        onChange(next)
      }
      if (hadInvalid) {
        setInternalInvalid(true)
      }
      return hadInvalid
    },
    [value, maxItems, onChange, onInvalidInput],
  )

  // The field is the popover's Anchor, not a Trigger, so Radix treats any focus
  // or pointerdown on it as "outside" -- including the very focusin that opens
  // the list, which would dismiss it immediately.
  const keepOpenOnFieldInteraction = (event: Event) => {
    if (event.target instanceof Node && anchorRef.current?.contains(event.target)) {
      event.preventDefault()
    }
  }

  const remove = (email: string) => {
    onChange(value.filter((current) => current !== email))
  }

  const selectSuggestion = (suggestion: EmailRecipientSuggestion) => {
    commitTokens([suggestion.email])
    setInputValue('')
    setOpen(false)
  }

  /** Enter/comma/blur: commits whatever is currently typed (split into tokens so a pasted-then-edited list still works). */
  const commitTyped = () => {
    const tokens = splitTokens(inputValue)
    if (tokens.length === 0) {
      return
    }
    const hadInvalid = commitTokens(tokens)
    if (!hadInvalid) {
      setInputValue('')
    }
  }

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    const raw = event.target.value
    setInternalInvalid(false)
    if (raw.endsWith(',')) {
      const hadInvalid = commitTokens(splitTokens(raw))
      setInputValue(hadInvalid ? raw.slice(0, -1) : '')
      return
    }
    setInputValue(raw)
    setOpen(true)
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (popoverOpen && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
      event.preventDefault()
      const count = filteredSuggestions.length
      setActiveIndex((index) => (event.key === 'ArrowDown' ? (index + 1) % count : (index - 1 + count) % count))
      return
    }
    if (event.key === 'Enter') {
      event.preventDefault()
      if (popoverOpen && filteredSuggestions[activeIndex]) {
        selectSuggestion(filteredSuggestions[activeIndex])
        return
      }
      commitTyped()
      return
    }
    if (event.key === 'Escape' && open) {
      setOpen(false)
      return
    }
    if (event.key === 'Backspace' && inputValue === '' && value.length > 0) {
      remove(value[value.length - 1])
    }
  }

  const handlePaste = (event: ClipboardEvent<HTMLInputElement>) => {
    const pasted = event.clipboardData.getData('text')
    const tokens = splitTokens(inputValue + pasted)
    if (tokens.length === 0) {
      return
    }
    event.preventDefault()
    commitTokens(tokens, { keepLastInvalid: true })
    const lastToken = tokens[tokens.length - 1]
    setInputValue(isValidEmail(lastToken) || value.includes(lastToken) ? '' : lastToken)
  }

  const activeOptionId = popoverOpen && filteredSuggestions[activeIndex] ? `${listboxId}-${activeIndex}` : undefined

  return (
    <PopoverPrimitive.Root open={popoverOpen} onOpenChange={setOpen}>
      <PopoverPrimitive.Anchor asChild>
        <div
          ref={setContainer}
          className={cn(
            'flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-field-border bg-field px-2 py-1 text-sm shadow-xs transition-[color,box-shadow]',
            'has-[[aria-invalid=true]]:border-destructive has-[[aria-invalid=true]]:ring-destructive/20',
            'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
            disabled && 'pointer-events-none opacity-50',
            className,
          )}
          onClick={() => inputRef.current?.focus()}
        >
          {value.map((email) => (
            <Badge key={email} variant="secondary" className="gap-1 pl-1.5 text-xs">
              {email}
              <span
                role="button"
                tabIndex={0}
                aria-label={`${removeLabel} ${email}`}
                className="rounded-sm outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
                onClick={(event) => {
                  event.stopPropagation()
                  remove(email)
                }}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    event.stopPropagation()
                    remove(email)
                  }
                }}
              >
                <X className="size-3" aria-hidden="true" />
              </span>
            </Badge>
          ))}
          <input
            ref={inputRef}
            id={id}
            type="text"
            role="combobox"
            aria-expanded={popoverOpen}
            aria-controls={listboxId}
            aria-autocomplete="list"
            aria-activedescendant={activeOptionId}
            aria-invalid={ariaInvalid || internalInvalid}
            aria-describedby={ariaDescribedBy}
            disabled={disabled}
            value={inputValue}
            onChange={handleChange}
            onKeyDown={handleKeyDown}
            onPaste={handlePaste}
            onFocus={() => setOpen(true)}
            onBlur={commitTyped}
            placeholder={value.length === 0 ? placeholder : undefined}
            className="min-w-[8ch] flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed"
          />
        </div>
      </PopoverPrimitive.Anchor>

      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={4}
          onOpenAutoFocus={(event) => event.preventDefault()}
          onInteractOutside={keepOpenOnFieldInteraction}
          className="z-50 w-(--radix-popover-trigger-width) rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
        >
          <div id={listboxId} role="listbox" className="max-h-48 overflow-y-auto">
            {filteredSuggestions.map((suggestion, index) => (
              <div
                key={suggestion.email}
                id={`${listboxId}-${index}`}
                role="option"
                aria-selected={index === activeIndex}
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => selectSuggestion(suggestion)}
                className={cn(
                  'flex cursor-pointer flex-col gap-0.5 rounded-sm px-2 py-1.5 text-xs',
                  index === activeIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent',
                )}
              >
                <span className="truncate font-medium">{suggestion.label}</span>
                <span className="truncate text-muted-foreground">{suggestion.email}</span>
              </div>
            ))}
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}
