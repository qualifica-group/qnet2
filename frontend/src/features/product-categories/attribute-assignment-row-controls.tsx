import { Info } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { CustomFieldType } from '@/features/custom-fields/types'

/**
 * Small, keyboard-reachable presentational pieces shared by every
 * attribute-assignment section (task #19: every non-obvious control on a row
 * is self-explanatory). Split out of the editor so each context's section
 * stays small (engineering.md §6).
 */

interface InfoTooltipProps {
  /** Accessible name of the trigger AND the tooltip's own content (mirrors `AvatarWithTooltip`). */
  label: string
}

/**
 * A tiny, keyboard-reachable info affordance explaining a control next to it.
 * Module-scoped (not nested in a render function) so it keeps a stable
 * component identity across re-renders.
 */
export function InfoTooltip({ label }: InfoTooltipProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          tabIndex={0}
          aria-label={label}
          className="inline-flex shrink-0 rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        >
          <Info className="size-3.5" aria-hidden="true" />
        </span>
      </TooltipTrigger>
      <TooltipContent side="top" variant="light" className="max-w-56">
        {label}
      </TooltipContent>
    </Tooltip>
  )
}

interface DataTypeBadgeProps {
  type: CustomFieldType
  label: string
  description: string
}

/** The attribute's type badge (glyph + label, shared with the custom fields catalogue), with a tooltip describing what that type means. */
export function DataTypeBadge({ type, label, description }: DataTypeBadgeProps) {
  const Icon = FIELD_TYPE_ICONS[type]
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Badge variant="secondary" className="cursor-default gap-1 text-xs" tabIndex={0}>
          <Icon className="size-3.5" aria-hidden="true" />
          {label}
        </Badge>
      </TooltipTrigger>
      <TooltipContent side="top" variant="light" className="max-w-56">
        {description}
      </TooltipContent>
    </Tooltip>
  )
}
