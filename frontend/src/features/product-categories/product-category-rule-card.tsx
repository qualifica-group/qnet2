import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Lock } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

interface ProductCategoryRuleCardProps {
  /** Contextual glyph for the rule (lucide icon component). */
  icon: LucideIcon
  /** Whether the rule is currently ON — tints the glyph so an active rule reads at a glance. */
  active: boolean
  /** Set when the rule is inherited from a branch root: shows the lock chip naming it. */
  inheritedFrom?: string | null
  /** Accessible/visible text of the inherited chip, already interpolated by the caller. */
  inheritedLabel?: string
  children: ReactNode
  className?: string
}

/**
 * One rule of the "Regole di gestione" section: a bordered tile with a
 * contextual glyph, the field itself (label + info tooltip + description +
 * control, laid out by `MetaField layout="inline"`), and an optional
 * "inherited from X" chip.
 *
 * The glyph is the only state-carrying decoration: it fills with the primary
 * tint while the rule is active, so an operator scanning the section sees
 * which rules bite without reading every control. State is never signalled by
 * color alone — the control itself and its description always say it too
 * (ui-design.md §4).
 *
 * Presentation only: no form state, no authorization logic.
 */
export function ProductCategoryRuleCard({
  icon: Icon,
  active,
  inheritedFrom,
  inheritedLabel,
  children,
  className,
}: ProductCategoryRuleCardProps) {
  return (
    <div
      className={cn(
        'flex items-start gap-3 rounded-lg border bg-card p-3 transition-colors',
        'hover:border-primary/30 hover:shadow-sm focus-within:border-primary/40',
        className,
      )}
    >
      <span
        className={cn(
          'flex size-8 shrink-0 items-center justify-center rounded-lg border transition-colors',
          active
            ? 'border-primary/20 bg-primary/10 text-primary'
            : 'border-border bg-muted/40 text-muted-foreground',
        )}
      >
        <Icon className="size-4" aria-hidden="true" />
      </span>
      <div className="min-w-0 flex-1 space-y-2">
        {children}
        {inheritedFrom ? (
          <Badge variant="outline" className="gap-1 text-[11px] font-normal">
            <Lock className="size-3" aria-hidden="true" />
            {inheritedLabel}
          </Badge>
        ) : null}
      </div>
    </div>
  )
}
