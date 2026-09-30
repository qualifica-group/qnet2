import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

/** Band shared by header and footer of every email popup: one rung above the dialog body (ui-design.md §1-bis). */
const EMAIL_DIALOG_BAND_CLASS = 'border-border bg-surface px-4 py-3 sm:px-5'

interface WorkOrderEmailDialogHeaderProps {
  icon: LucideIcon
  title: string
  description?: string
  /** Right-aligned slot (e.g. the status badge); kept clear of the dialog's close button. */
  trailing?: ReactNode
}

/**
 * Header band of the email popups (composer, detail, documents picker): icon
 * chip + title/subtitle on the surface rung, so the three dialogs read as one
 * family instead of three flat forms.
 */
export function WorkOrderEmailDialogHeader({ icon: Icon, title, description, trailing }: WorkOrderEmailDialogHeaderProps) {
  return (
    <DialogHeader className={cn(EMAIL_DIALOG_BAND_CLASS, 'flex-row items-center gap-3 rounded-t-lg border-b pr-12 text-left sm:pr-12')}>
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="size-4.5" aria-hidden="true" />
      </span>
      <div className="min-w-0 flex-1">
        <DialogTitle className="truncate">{title}</DialogTitle>
        {description ? <DialogDescription className="truncate text-xs">{description}</DialogDescription> : null}
      </div>
      {trailing ? <div className="shrink-0">{trailing}</div> : null}
    </DialogHeader>
  )
}

/** Footer band twin of the header: actions stay pinned under the scrolling body, in one compact row at every width. */
export function WorkOrderEmailDialogFooter({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <DialogFooter
      className={cn(EMAIL_DIALOG_BAND_CLASS, 'flex-row flex-wrap items-center justify-end rounded-b-lg border-t', className)}
    >
      {children}
    </DialogFooter>
  )
}
