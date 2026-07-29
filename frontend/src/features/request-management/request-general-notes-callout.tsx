import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'

interface RequestGeneralNotesCalloutProps {
  /** The opportunity's "Note generali", read-only in this module. */
  notes: string | null
}

/**
 * The opportunity's "Note generali" highlighted at the top of the work
 * panel's side column (user directive 2026-07-27): operators act on this text,
 * so it is deliberately louder than the neutral summary rows below it.
 *
 * Amber, not the brand hue (user directive 2026-07-29): the whole surface scale
 * is blue-grey, so a `primary` wash on `bg-surface` separates by lightness only
 * and reads as the same plane. A warm hue separates by chroma instead, which
 * survives both themes — and matches the sticky-note metaphor of the icon.
 *
 * READ-ONLY by design: this module never writes the field (the opportunities
 * form owns it), mirroring how spec 0049 D-5 keeps the sales dimensions out of
 * the panel. Renders nothing when there are no notes rather than an empty box.
 */
export function RequestGeneralNotesCallout({ notes }: RequestGeneralNotesCalloutProps) {
  const { t } = useTranslation()

  if (!notes) {
    return null
  }

  return (
    <section
      aria-labelledby="request-general-notes-title"
      className="rounded-lg border border-amber-500/40 border-l-4 border-l-amber-500 bg-amber-100 p-3 shadow-sm dark:border-amber-400/30 dark:border-l-amber-400 dark:bg-amber-400/15"
    >
      <h3
        id="request-general-notes-title"
        className="flex items-center gap-1.5 text-xs font-semibold tracking-tight text-amber-800 uppercase dark:text-amber-300"
      >
        <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
        {t('requestManagement.workPanel.generalNotes.title')}
      </h3>
      {/* `whitespace-pre-wrap`: operators paste multi-line notes; capped and
          scrollable so a long note never pushes the summary out of view. */}
      <p className="mt-2 max-h-64 overflow-y-auto text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
        {notes}
      </p>
    </section>
  )
}
