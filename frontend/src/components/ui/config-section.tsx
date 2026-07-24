import type { ReactNode } from 'react'
import { Rows3 } from 'lucide-react'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'
import { FormSection } from '@/components/form-section'

/**
 * Visual weight a `ConfigSection` carries. Frozen prop (spec 0062
 * `layout-contract`, `App\Enums\LayoutSectionVariant`): the attribute-layout
 * configurator and the agnostic renderer both consume this literal union
 * verbatim, so it must not drift from `attribute-layout-types.ts`'s
 * `LayoutSectionVariant`.
 */
export type ConfigSectionVariant = 'default' | 'highlighted' | 'informative' | 'secondary'

/**
 * Container classes per variant, built ONLY from `index.css` surface tokens
 * (ui-design.md §1-bis). `default` leaves `FormSection`'s own `border bg-card`
 * untouched. `highlighted` washes the primary tint already used for the
 * header icon chip. `informative` is the documented "subtle block inside a
 * card" muted tint. `ConfigSection` renders in TWO structurally different
 * hosts (spec 0062): directly on the page (`product`/`request` dynamic
 * fields, ui-design.md §1-bis rung 1) and nested inside a `bg-card` FormSection
 * (the layout configurator's live preview, rung 3 — no rung above `--card`
 * exists to climb to). A rung-based variant would be correct in one host and
 * a surface repeat in the other, so `secondary` is host-agnostic by
 * construction: no fill of its own (`bg-transparent`, letting whatever hosts
 * it show through) plus a hairline and a flatter shadow, reading as the
 * lowest-emphasis variant purely through tint/border, never through a rung.
 * Passed as `FormSection`'s `className`, resolved by `cn()` (tailwind-merge)
 * against its base classes — later wins on the same utility group.
 */
const configSectionVariants = cva('', {
  variants: {
    variant: {
      default: '',
      highlighted: 'border-primary/30 bg-primary/5',
      informative: 'border-border bg-muted/40',
      secondary: 'border-border bg-transparent shadow-none',
    },
  },
  defaultVariants: { variant: 'default' },
})

export interface ConfigSectionProps {
  title: string
  description?: string | null
  variant: ConfigSectionVariant
  /** When true, the header becomes a collapse toggle (built on `FormSection`, a11y/focus preserved). */
  collapsible?: boolean
  /** Uncontrolled initial collapsed state; only read when `collapsible` is true. */
  defaultCollapsed?: boolean
  children: ReactNode
}

/**
 * Reusable section shell for the attribute-layout configurator/renderer
 * (spec 0062 MT-2.2): a thin `FormSection` wrapper adding the frozen
 * `variant` styling on top. Presentation only — no form state, no layout
 * logic; collapse behaviour (and its a11y: `aria-expanded`/focus handling)
 * is entirely `FormSection`'s `Collapsible`, not reimplemented here.
 */
export function ConfigSection({
  title,
  description,
  variant,
  collapsible = false,
  defaultCollapsed = false,
  children,
}: ConfigSectionProps) {
  return (
    <FormSection
      icon={Rows3}
      title={title}
      description={description}
      collapsible={collapsible}
      defaultOpen={!defaultCollapsed}
      className={cn(configSectionVariants({ variant }))}
    >
      {children}
    </FormSection>
  )
}
