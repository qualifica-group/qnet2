import * as React from "react"
import { Slider as SliderPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

type SliderProps = React.ComponentProps<typeof SliderPrimitive.Root> & {
  /**
   * Per-thumb accessible names, used when the slider renders more than one
   * handle (e.g. a two-handle range). Ignored for the single-thumb case,
   * which keeps using `aria-label`/`aria-labelledby` on the root.
   */
  thumbLabels?: string[]
}

/**
 * Compact range slider on the Radix Slider primitive (accessible: keyboard,
 * ARIA, focus-visible ring). Value/onValueChange are the Radix `number[]`
 * contract; single-thumb callers pass `[value]` and read `values[0]`.
 *
 * Renders one `Thumb` per entry of `value`/`defaultValue` (minimum one), so
 * multi-handle ranges (e.g. a two-handle coverage filter) get one accessible
 * `role="slider"` node per handle instead of Radix's implicit single thumb.
 */
function Slider({
  className,
  "aria-label": ariaLabel,
  "aria-labelledby": ariaLabelledby,
  thumbLabels,
  ...props
}: SliderProps) {
  const values = props.value ?? props.defaultValue ?? [0]
  const thumbCount = Math.max(values.length, 1)
  const isMultiThumb = thumbCount > 1

  return (
    <SliderPrimitive.Root
      data-slot="slider"
      className={cn(
        "relative flex w-full touch-none select-none items-center data-[disabled]:opacity-50",
        className,
      )}
      {...props}
    >
      <SliderPrimitive.Track className="relative h-1.5 w-full grow overflow-hidden rounded-full bg-muted">
        <SliderPrimitive.Range className="absolute h-full bg-primary" />
      </SliderPrimitive.Track>
      {/* Forward the accessible name to each thumb (the `role="slider"` node),
          not only the root, so screen readers announce it. Single-thumb
          callers keep the exact previous DOM: one Thumb named via
          aria-label/aria-labelledby from the root's props. */}
      {Array.from({ length: thumbCount }, (_, index) => (
        <SliderPrimitive.Thumb
          key={index}
          aria-label={isMultiThumb ? thumbLabels?.[index] : ariaLabel}
          aria-labelledby={isMultiThumb ? undefined : ariaLabelledby}
          className="block size-4 rounded-full border border-primary/60 bg-background shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none"
        />
      ))}
    </SliderPrimitive.Root>
  )
}

export { Slider }
