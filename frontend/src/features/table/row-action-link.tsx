import { useRef, type ComponentProps, type MouseEvent } from 'react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { isModifiedClick } from '@/lib/modified-click'

interface RowActionButtonProps extends Omit<ComponentProps<typeof Button>, 'asChild' | 'onClick' | 'onSelect'> {
  /** The page the action leads to; omitted, the action renders as a plain button. */
  href?: string
  onSelect: () => void
}

/**
 * An inline row action. With an `href` it is a real link: a plain click runs
 * the action as before (modal or same-tab page, per the domain), while
 * Ctrl/Cmd+click, middle click and the browser's "open in new tab" open the
 * page in a new tab.
 */
export function RowActionButton({ href, onSelect, children, ...props }: RowActionButtonProps) {
  if (href === undefined) {
    return (
      <Button {...props} onClick={onSelect}>
        {children}
      </Button>
    )
  }

  const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
    if (isModifiedClick(event)) {
      return
    }
    event.preventDefault()
    onSelect()
  }

  return (
    <Button {...props} asChild>
      <Link to={href} onClick={handleClick}>
        {children}
      </Link>
    </Button>
  )
}

interface RowActionMenuItemProps extends Omit<ComponentProps<typeof DropdownMenuItem>, 'asChild' | 'onSelect'> {
  href?: string
  onSelect: () => void
}

/**
 * An overflow-menu row action, link-aware like `RowActionButton`. The menu
 * item ignores a click whose default is already prevented (it would neither
 * select nor close), so the click is only remembered here and the link's
 * navigation is cancelled from `onSelect`, after the menu has seen it.
 */
export function RowActionMenuItem({ href, onSelect, children, ...props }: RowActionMenuItemProps) {
  const clickRef = useRef<MouseEvent<HTMLAnchorElement> | null>(null)

  if (href === undefined) {
    return (
      <DropdownMenuItem {...props} onSelect={onSelect}>
        {children}
      </DropdownMenuItem>
    )
  }

  const handleSelect = () => {
    const click = clickRef.current
    clickRef.current = null
    if (click && isModifiedClick(click)) {
      return
    }
    click?.preventDefault()
    onSelect()
  }

  return (
    <DropdownMenuItem {...props} asChild onSelect={handleSelect}>
      <Link
        to={href}
        onClick={(event) => {
          clickRef.current = event
        }}
      >
        {children}
      </Link>
    </DropdownMenuItem>
  )
}
