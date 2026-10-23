import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

/** A label truncated to its column; the tooltip spells it out in full (user directive 2026-10-23). */
export function TruncatedLabel({ label, className }: { label: string; className?: string }) {
  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <div
            tabIndex={0}
            className={cn(
              'cursor-default truncate rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
              className,
            )}
          >
            {label}
          </div>
        </TooltipTrigger>
        <TooltipContent side="top" align="start" className="max-w-96 text-xs">
          {label}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
