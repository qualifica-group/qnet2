import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

interface LockedSourceHintProps {
  /** The name of the integration owning the field (e.g. the external system's name). */
  source: string
}

/**
 * The sync icon beside the label of a work order field an integration owns:
 * on hover or focus it names where the value comes from and why there is no pencil.
 */
export function LockedSourceHint({ source }: LockedSourceHintProps) {
  const { t } = useTranslation()
  const hint = t('workOrders.detail.lockedSource', { source })

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            aria-label={hint}
            className="inline-flex shrink-0 cursor-help items-center text-primary/80 transition-colors hover:text-primary focus-visible:text-primary focus-visible:outline-none"
          >
            <RefreshCw aria-hidden="true" className="size-3" />
          </button>
        </TooltipTrigger>
        <TooltipContent className="max-w-64">{hint}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
