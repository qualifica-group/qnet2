import type { ReactNode } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'

/** Brand-tinted header strip with the icon chip. */
const HEADER_BAND_CLASS =
  'flex items-start gap-3 border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pr-12 pb-3.5'
const HEADER_ICON_CLASS =
  'flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15'
const FOOTER_CLASS =
  'flex flex-wrap items-center gap-2 border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3'

/** Scrollable rung-2 body the caller's `<form>` takes, under the header band and above the footer. */
export const FILTERS_SHEET_BODY_CLASS = 'flex flex-1 flex-col gap-4 overflow-y-auto bg-surface p-4'

export interface FiltersSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: string
  defaultWidth: number
  /** Persists the user-resized width, one key per screen. */
  storageKey: string
  /** The body `<form>` followed by a `FiltersSheetFooter`. */
  children: ReactNode
}

/**
 * The shared filter surface (Gestione Richieste dashboard, commessa task
 * board): the screen only shows what is applied, this sheet holds every
 * control. Only the frame lives here; the draft state and its fields stay
 * with the caller.
 */
export function FiltersSheet({
  open,
  onOpenChange,
  title,
  description,
  defaultWidth,
  storageKey,
  children,
}: FiltersSheetProps) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" defaultWidth={defaultWidth} storageKey={storageKey}>
        <div className={HEADER_BAND_CLASS}>
          <span aria-hidden="true" className={HEADER_ICON_CLASS}>
            <SlidersHorizontal className="size-4.5" />
          </span>
          <SheetHeader className="flex-1 gap-1 p-0">
            <SheetTitle className="text-sm">{title}</SheetTitle>
            <SheetDescription className="text-xs">{description}</SheetDescription>
          </SheetHeader>
        </div>
        {children}
      </SheetContent>
    </Sheet>
  )
}

export interface FiltersSheetFooterLabels {
  reset: string
  cancel: string
  apply: string
}

export interface FiltersSheetFooterProps {
  /** Id of the body `<form>` the apply button submits. */
  formId: string
  labels: FiltersSheetFooterLabels
  /** Brings the DRAFT back to the defaults; nothing is applied until "apply". */
  onReset: () => void
  onCancel: () => void
  disabled?: boolean
}

export function FiltersSheetFooter({ formId, labels, onReset, onCancel, disabled = false }: FiltersSheetFooterProps) {
  return (
    <div className={FOOTER_CLASS}>
      <Button type="button" size="sm" variant="ghost" className="mr-auto" onClick={onReset} disabled={disabled}>
        {labels.reset}
      </Button>
      <Button type="button" size="sm" variant="outline" className="bg-card" onClick={onCancel}>
        {labels.cancel}
      </Button>
      <Button
        type="submit"
        form={formId}
        size="sm"
        disabled={disabled}
        className="min-w-24 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px"
      >
        {labels.apply}
      </Button>
    </div>
  )
}
