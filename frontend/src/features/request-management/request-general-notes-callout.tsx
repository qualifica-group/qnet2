import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'

interface RequestGeneralNotesCalloutProps {
  /** The opportunity's "Note generali", read-only in this module. */
  notes: string | null
}

/**
 * The opportunity's "Note generali" highlighted at the top of the work
 * panel's side column (user directive 2026-07-27): operators act on this text,
 * so it is deliberately louder than the neutral summary rows below it — the
 * accent-tinted surface of `OpportunityFromLeadBanner`, one step stronger.
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
      className="rounded-lg border border-primary/30 border-l-4 border-l-primary bg-primary/5 p-3 shadow-sm"
    >
      <h3
        id="request-general-notes-title"
        className="flex items-center gap-1.5 text-xs font-semibold tracking-tight text-primary uppercase"
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
