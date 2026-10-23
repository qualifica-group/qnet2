import { useId, useState, type ReactNode } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

interface QuoteSummaryDisclosureProps {
  showLabel: string
  hideLabel: string
  children: ReactNode
}

/**
 * A collapsed block of the quote summary, opened by its toggle under the
 * cards (user directive 2026-10-07 for the advanced data, 2026-10-23 for the
 * commission details): the cards stay the headline.
 */
export function QuoteSummaryDisclosure({ showLabel, hideLabel, children }: QuoteSummaryDisclosureProps) {
  const contentId = useId()
  const [open, setOpen] = useState(false)

  return (
    <div className="flex flex-col gap-3">
      <Button
        type="button"
        variant="outline"
        size="sm"
        aria-expanded={open}
        aria-controls={contentId}
        onClick={() => setOpen((current) => !current)}
        className="h-7 self-start bg-card text-xs"
      >
        <ChevronDown aria-hidden="true" className={cn('size-3.5 transition-transform', open && 'rotate-180')} />
        {open ? hideLabel : showLabel}
      </Button>
      {open ? <div id={contentId}>{children}</div> : null}
    </div>
  )
}
