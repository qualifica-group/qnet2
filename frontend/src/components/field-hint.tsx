import { Info, type LucideIcon } from 'lucide-react'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

interface FieldHintProps {
  /** Explanatory text shown on hover/focus of the info glyph. */
  text: string
  /** Accessible name of the trigger button (e.g. "More info about Pattern"). */
  label: string
  /** The glyph of the trigger: the info "i" by default, a "?" where the text answers a question. */
  icon?: LucideIcon
  className?: string
}

/**
 * Small info glyph with an on-demand tooltip, sat next to a field label to
 * explain advanced/dense options without permanently spending vertical space
 * (project preference: compact UI). Primary fields get an always-visible inline
 * helper instead; this is for the secondary/advanced ones. Keyboard reachable
 * and screen-reader labelled.
 */
export function FieldHint({ text, label, icon: Icon = Info, className }: FieldHintProps) {
  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            aria-label={label}
            className={cn(
              'inline-flex size-4 shrink-0 items-center justify-center rounded-full text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50',
              className,
            )}
          >
            <Icon className="size-3.5" aria-hidden="true" />
          </button>
        </TooltipTrigger>
        <TooltipContent variant="light" className="max-w-64 text-pretty">
          {text}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
