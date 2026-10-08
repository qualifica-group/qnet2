import { useRef, useState, type FocusEvent, type KeyboardEvent, type MouseEvent, type PointerEvent, type ReactNode } from 'react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

/** A hint's body: a short title and the lines that explain it (formula, rule). */
export interface HintContent {
  title: string
  lines: string[]
}

const MOUSE_POINTER = 'mouse'

/** True when the browser would draw a focus ring: keyboard focus, not a tap or a click. */
function isKeyboardFocus(element: HTMLElement): boolean {
  try {
    return element.matches(':focus-visible')
  } catch {
    return true
  }
}

interface CalculationHintProps extends HintContent {
  children: ReactNode
  className?: string
}

/**
 * A value with its explanation. Opens on mouse hover, on keyboard focus and on
 * tap/click/Enter: it is a Popover (not a Tooltip) because a Radix Tooltip does
 * not open on touch. The trigger is a real button, so it is focusable; the
 * dashed underline tells the user the value can be explained.
 */
export function CalculationHint({ title, lines, children, className }: CalculationHintProps) {
  const [open, setOpen] = useState(false)
  const pointerType = useRef<string | null>(null)

  const handlePointerDown = (event: PointerEvent<HTMLButtonElement>) => {
    pointerType.current = event.pointerType
  }
  const handlePointerEnter = (event: PointerEvent<HTMLButtonElement>) => {
    if (event.pointerType === MOUSE_POINTER) setOpen(true)
  }
  const handlePointerLeave = (event: PointerEvent<HTMLButtonElement>) => {
    if (event.pointerType === MOUSE_POINTER) setOpen(false)
  }
  const handleFocus = (event: FocusEvent<HTMLButtonElement>) => {
    if (isKeyboardFocus(event.currentTarget)) setOpen(true)
  }
  const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key !== 'Escape') pointerType.current = null
  }
  // A click that follows a hover must not toggle the already-open hint shut.
  const handleClick = (event: MouseEvent<HTMLButtonElement>) => {
    if (pointerType.current === MOUSE_POINTER) {
      event.preventDefault()
      setOpen(true)
    }
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        type="button"
        className={cn(
          'cursor-help rounded-sm underline decoration-muted-foreground/60 decoration-dashed underline-offset-4 outline-none focus-visible:ring-2 focus-visible:ring-ring',
          className,
        )}
        onPointerDown={handlePointerDown}
        onPointerEnter={handlePointerEnter}
        onPointerLeave={handlePointerLeave}
        onFocus={handleFocus}
        onBlur={() => setOpen(false)}
        onKeyDown={handleKeyDown}
        onClick={handleClick}
      >
        {children}
      </PopoverTrigger>
      <PopoverContent
        side="top"
        collisionPadding={8}
        className="flex w-auto max-w-72 flex-col gap-1 p-2 text-xs"
        onOpenAutoFocus={(event) => event.preventDefault()}
      >
        <p className="font-medium">{title}</p>
        {lines.map((line) => (
          <p key={line} className="whitespace-pre-line text-muted-foreground tabular-nums">
            {line}
          </p>
        ))}
      </PopoverContent>
    </Popover>
  )
}
