import { Link2 } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

interface SyncedAttributeHintProps {
  /** The already translated explanation, which names the record the value is shared with. */
  hint: string
}

/**
 * The link icon beside the label of an Attribute shared between an offer and
 * its work orders (spec 0425): on hover or focus it says where else the value
 * shows up.
 */
export function SyncedAttributeHint({ hint }: SyncedAttributeHintProps) {
  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            aria-label={hint}
            className="inline-flex shrink-0 cursor-help items-center text-primary/80 transition-colors hover:text-primary focus-visible:text-primary focus-visible:outline-none"
          >
            <Link2 aria-hidden="true" className="size-3" />
          </button>
        </TooltipTrigger>
        <TooltipContent className="max-w-64">{hint}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
