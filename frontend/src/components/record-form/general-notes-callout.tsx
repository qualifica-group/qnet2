import { useId } from 'react'
import { StickyNote } from 'lucide-react'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TITLE_CLASS,
} from '@/components/record-form/layout'
import { cn } from '@/lib/utils'

interface GeneralNotesCalloutProps {
  /** Section heading, owned by the caller: each module names the field in its own namespace. */
  title: string
  notes: string | null | undefined
  className?: string
}

/**
 * Read-only "Note generali", in the very callout the editable field wears in
 * the record forms (`OpportunityGeneralNotesSection`, `RequestCreateGeneralNotes`):
 * same amber box, same left rule, same micro-title. Neutral home next to the
 * classes it renders, so reading a note and writing it can never drift apart
 * (user directive 2026-08-06: "le voglio come le note generali del form, stesso
 * componente e colore").
 *
 * Amber, not the brand hue (user directive 2026-07-29): the whole surface scale
 * is blue-grey, so a `primary` wash separates by lightness only and reads as the
 * same plane. A warm hue separates by chroma instead, which survives both themes
 * — and matches the sticky-note metaphor of the icon.
 *
 * Renders nothing when there is no note, rather than an empty box.
 */
export function GeneralNotesCallout({ title, notes, className }: GeneralNotesCalloutProps) {
  const titleId = useId()

  if (!notes) {
    return null
  }

  return (
    <section aria-labelledby={titleId} className={cn(GENERAL_NOTES_CALLOUT_CLASS, className)}>
      <h3 id={titleId} className={GENERAL_NOTES_TITLE_CLASS}>
        <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
        {title}
      </h3>
      {/* `whitespace-pre-wrap`: operators paste multi-line notes; capped and
          scrollable so a long one never pushes the rest of the column away. */}
      <p className="mt-2 max-h-64 overflow-y-auto text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
        {notes}
      </p>
    </section>
  )
}
