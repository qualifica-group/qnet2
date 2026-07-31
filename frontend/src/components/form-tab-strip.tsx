import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { TabsList } from '@/components/ui/tabs'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

/**
 * Shared look and behaviour for the app's module tab strips (form and detail
 * tabs): a compact pill strip built on top of the design-system
 * `components/ui/tabs` base — same styling in every module so the tabbed layout
 * reads consistently. Presentation only; the modules own their tab items,
 * visibility and content.
 */

/** Tab-strip container: compact pill trough on the base list surface. */
export const FORM_TAB_LIST_CLASS = 'gap-1 rounded-lg p-1 shadow-sm'

/** Tab trigger: pill, muted until active, white (card surface) chip when active. */
export const FORM_TAB_TRIGGER_CLASS =
  'gap-1.5 rounded-md px-2.5 py-1 text-xs text-muted-foreground transition-all duration-200 ' +
  'hover:bg-card/60 hover:text-foreground ' +
  'data-[state=active]:bg-card data-[state=active]:text-primary ' +
  'data-[state=active]:shadow-sm data-[state=active]:ring-1 data-[state=active]:ring-primary/15 ' +
  '[&_svg]:size-3.5 [&_svg]:transition-transform [&_svg]:duration-200 data-[state=active]:[&_svg]:scale-110'

/** Sub-pixel slack, so a strip that fits exactly is not read as overflowing. */
const OVERFLOW_TOLERANCE_PX = 1

/** Small dot marking a tab whose grouped sections carry a validation error. */
export function TabErrorDot({ label }: { label: string }) {
  return (
    <span
      className="size-1.5 shrink-0 rounded-full bg-destructive shadow-[0_0_0_2px] shadow-destructive/15"
      role="img"
      aria-label={label}
    />
  )
}

interface FormTabStripProps {
  /** Active tab value: the strip is controlled, so the select fallback can drive it. */
  value: string
  onValueChange: (value: string) => void
  className?: string
  'aria-label'?: string
  /** `TabsTrigger` elements; their `value`/`children` also feed the select fallback. */
  children: React.ReactNode
}

interface TabTriggerProps {
  value: string
  children?: React.ReactNode
}

/**
 * Module tab strip that degrades to a select when the tabs no longer fit the
 * available width: past that point a horizontal strip either clips or pushes
 * the page sideways, so the same tabs are offered as a full-width dropdown.
 *
 * Must be rendered inside a controlled `<Tabs value onValueChange>` — the
 * select cannot drive an uncontrolled Radix root.
 */
export function FormTabStrip({
  value,
  onValueChange,
  className,
  'aria-label': ariaLabel,
  children,
}: FormTabStripProps) {
  const { t } = useTranslation()
  const containerRef = React.useRef<HTMLDivElement>(null)
  const listRef = React.useRef<HTMLDivElement>(null)
  const overflows = useStripOverflow(containerRef, listRef, React.Children.count(children))

  return (
    // The strip must follow the width its column already has and contribute
    // nothing to that column's own intrinsic width — otherwise a long strip
    // widens every ancestor with no definite width (the app shell's `main` is
    // one) and pushes the whole page sideways instead of collapsing.
    // `w-0 min-w-full` keeps the box off the parent's max-content width, and
    // `overflow-x-clip` zeroes its automatic minimum size (`clip`, not
    // `hidden`, so focus rings and shadows still show vertically).
    <div ref={containerRef} className={cn('relative w-0 min-w-full overflow-x-clip', className)}>
      {overflows ? (
        <Select value={value} onValueChange={onValueChange}>
          <SelectTrigger
            size="sm"
            className="w-full text-xs"
            aria-label={ariaLabel ?? t('common.tabsSelectLabel')}
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {tabItemsOf(children).map((item) => (
              <SelectItem key={item.value} value={item.value} className="text-xs">
                {item.children}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : null}
      <TabsList
        ref={listRef}
        aria-label={ariaLabel}
        className={cn(
          FORM_TAB_LIST_CLASS,
          // `overflow-hidden` keeps a strip that outgrows its container from
          // widening the page in the frame before the measurement below runs.
          'flex-nowrap overflow-hidden',
          // Kept mounted while collapsed (at its natural width, out of flow and
          // out of the accessibility tree) so it stays measurable and the strip
          // can come back as soon as the container is wide enough again.
          overflows && 'invisible absolute top-0 left-0 w-max',
        )}
      >
        {children}
      </TabsList>
    </div>
  )
}

/** Reads the select fallback's options off the `TabsTrigger` children. */
function tabItemsOf(children: React.ReactNode) {
  return React.Children.toArray(children)
    .filter((child): child is React.ReactElement<TabTriggerProps> => React.isValidElement(child))
    .map((child) => ({ value: child.props.value, children: child.props.children }))
}

/**
 * The space the strip really has on screen. A container can still end up wider
 * than the viewport (a sibling that is itself oversized, an ancestor sized by
 * its content), and tabs running past the screen edge is exactly what the strip
 * has to avoid — so the narrower of the two wins.
 */
function availableWidth(container: HTMLElement) {
  const { left } = container.getBoundingClientRect()
  // `clientWidth` excludes the vertical scrollbar, which `innerWidth` does not;
  // the fallback is for environments that do not lay the document out (jsdom).
  const viewportWidth = document.documentElement.clientWidth || window.innerWidth

  return Math.min(container.clientWidth, viewportWidth - left)
}

/**
 * True while the tab strip is wider than the space it has. The list is measured
 * by its content width (`scrollWidth`) in both modes — clipped while inline,
 * natural while collapsed — so the two states cannot flip-flop.
 */
function useStripOverflow(
  containerRef: React.RefObject<HTMLDivElement | null>,
  listRef: React.RefObject<HTMLDivElement | null>,
  tabCount: number,
) {
  const [overflows, setOverflows] = React.useState(false)

  React.useEffect(() => {
    const container = containerRef.current
    const list = listRef.current
    if (!container || !list) return

    const measure = () => {
      setOverflows(list.scrollWidth > availableWidth(container) + OVERFLOW_TOLERANCE_PX)
    }

    const observer = new ResizeObserver(measure)
    observer.observe(container)
    observer.observe(list)
    // A window resize can shrink the viewport without resizing a
    // content-sized container, which the observer alone would not catch.
    window.addEventListener('resize', measure)

    return () => {
      observer.disconnect()
      window.removeEventListener('resize', measure)
    }
    // `tabCount` re-measures when tabs are added or removed: the collapsed list
    // changes box (observed), the inline one does not.
  }, [containerRef, listRef, tabCount])

  return overflows
}
