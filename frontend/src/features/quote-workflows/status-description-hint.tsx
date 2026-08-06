import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

interface StatusDescriptionHintProps {
  description: string | null | undefined
  className?: string
}

/**
 * The "(i)" marker sitting next to a working-status badge (spec 0047
 * amendment): the single place a status' `description` is offered on demand,
 * so the table cell and the opportunity detail expose it identically.
 * Renders nothing when the status carries no description — the badge then
 * stays exactly as before.
 */
export function StatusDescriptionHint({ description, className }: StatusDescriptionHintProps) {
  const { t } = useTranslation()

  if (typeof description !== 'string' || description === '') {
    return null
  }

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            aria-label={t('quoteWorkflows.form.statuses.descriptionHint')}
            className={cn(
              'inline-flex shrink-0 cursor-help items-center text-muted-foreground/70 transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none',
              className,
            )}
          >
            <Info aria-hidden="true" className="size-3.5" />
          </button>
        </TooltipTrigger>
        <TooltipContent className="max-w-64">{description}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
