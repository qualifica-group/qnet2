import { Check, Loader2, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

/** The open line reads as "being edited", like an open field row. */
export const EDITING_LINE_CLASS = 'border-ring/50 ring-[3px] ring-ring/15'

/**
 * Annulla/Salva under a line opened in place on a record detail (RDA spec
 * 0256, ODA spec 0257), with the refusal no field message can carry.
 */
export function LineEditBar({ inline }: { inline: InlineEdit }) {
  return (
    <div className="flex flex-col gap-1.5 pt-1">
      {inline.error ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {inline.error}
        </p>
      ) : null}
      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="ghost" size="xs" onClick={inline.cancel} disabled={inline.isSaving}>
          <X aria-hidden="true" />
          {inline.cancelLabel}
        </Button>
        <Button type="button" size="xs" onClick={inline.save} disabled={inline.isSaving}>
          {inline.isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Check aria-hidden="true" />}
          {inline.confirmLabel}
        </Button>
      </div>
    </div>
  )
}
