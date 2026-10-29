import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Upper bound given to every native date-family input that sets no `max` of
 * its own. Without it the browser accepts years up to 275760, so the year
 * segment takes 5-6 typed digits; a 4-digit-year ceiling makes the year
 * segment stop at 4 digits.
 */
const FOUR_DIGIT_YEAR_MAX: Partial<Record<string, string>> = {
  date: "9999-12-31",
  "datetime-local": "9999-12-31T23:59",
  month: "9999-12",
}

function Input({ className, type, max, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      max={max ?? (type ? FOUR_DIGIT_YEAR_MAX[type] : undefined)}
      data-slot="input"
      className={cn(
        "h-9 w-full min-w-0 rounded-md border border-field-border bg-field px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
        className
      )}
      {...props}
    />
  )
}

export { Input }
