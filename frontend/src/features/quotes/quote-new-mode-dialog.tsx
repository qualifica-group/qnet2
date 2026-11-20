import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { FilePlus2, Link2 } from 'lucide-react'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'

interface QuoteNewModeDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The current form: the offer goes on an opportunity that already exists. */
  onExisting: () => void
  /** Spec 0396: the opportunity and its offer are created together. */
  onWithOpportunity: () => void
}

interface ChoiceProps {
  icon: ReactNode
  title: string
  description: string
  onClick: () => void
}

function Choice({ icon, title, description, onClick }: ChoiceProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex w-full items-start gap-3 rounded-lg border border-border bg-card p-3 text-left shadow-sm outline-none transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50"
    >
      <span className="mt-0.5 text-primary [&_svg]:size-4" aria-hidden="true">
        {icon}
      </span>
      <span className="flex min-w-0 flex-col gap-0.5">
        <span className="text-sm font-medium text-foreground">{title}</span>
        <span className="text-xs text-muted-foreground">{description}</span>
      </span>
    </button>
  )
}

/**
 * "Nuova offerta" asks how to start (spec 0396 D-1): on an existing
 * opportunity (the current form) or with a new opportunity created together
 * with the offer. Only offered to who may create both.
 */
export function QuoteNewModeDialog({ open, onOpenChange, onExisting, onWithOpportunity }: QuoteNewModeDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{t('quotes.withOpportunity.chooser.title')}</DialogTitle>
          <DialogDescription>{t('quotes.withOpportunity.chooser.description')}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-2">
          <Choice
            icon={<Link2 />}
            title={t('quotes.withOpportunity.chooser.existing.title')}
            description={t('quotes.withOpportunity.chooser.existing.description')}
            onClick={onExisting}
          />
          <Choice
            icon={<FilePlus2 />}
            title={t('quotes.withOpportunity.chooser.new.title')}
            description={t('quotes.withOpportunity.chooser.new.description')}
            onClick={onWithOpportunity}
          />
        </div>
      </DialogContent>
    </Dialog>
  )
}
