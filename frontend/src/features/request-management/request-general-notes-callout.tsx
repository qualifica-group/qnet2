import { useTranslation } from 'react-i18next'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'

interface RequestGeneralNotesCalloutProps {
  /** The opportunity's "Note generali", read-only in this module. */
  notes: string | null
}

/**
 * The opportunity's "Note generali" highlighted at the top of the work panel's
 * side column (user directive 2026-07-27): operators act on this text, so it is
 * deliberately louder than the neutral summary rows below it.
 *
 * Chrome and behaviour live in the shared `GeneralNotesCallout`; this module
 * only supplies its own heading. READ-ONLY by design: the panel never writes
 * the field (the opportunities form owns it), mirroring how spec 0049 D-5 keeps
 * the sales dimensions out of the panel.
 */
export function RequestGeneralNotesCallout({ notes }: RequestGeneralNotesCalloutProps) {
  const { t } = useTranslation()

  return (
    <GeneralNotesCallout title={t('requestManagement.workPanel.generalNotes.title')} notes={notes} />
  )
}
