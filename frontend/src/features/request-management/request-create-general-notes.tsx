import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'
import { useController, type Control } from 'react-hook-form'
import { Textarea } from '@/components/ui/textarea'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TITLE_CLASS,
} from '@/features/request-management/request-general-notes-callout'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateGeneralNotesProps {
  control: Control<RequestCreateFormValues>
}

/**
 * "Note generali" at creation (user directive 2026-07-31), written INSIDE the
 * very callout the work panel reads them from: same amber box, same left rule,
 * same micro-title, same first position in the side column. The operator who
 * opens the request types here what the operator working it will read there.
 *
 * The textarea is deliberately unstyled (`border-0 bg-transparent`, no focus
 * ring of its own): the callout IS the field's surface, and a second bordered
 * box inside it would stack two containers on the same plane (ui-design.md
 * §1-bis).
 *
 * Later edits stay the opportunities form's job (spec 0049 D-5 keeps the sales
 * dimensions out of the panel); nothing changes there.
 */
export function RequestCreateGeneralNotes({ control }: RequestCreateGeneralNotesProps) {
  const { t } = useTranslation()
  const titleId = useId()
  const { field } = useController({ control, name: 'general_notes' })

  return (
    <section aria-labelledby={titleId} className={GENERAL_NOTES_CALLOUT_CLASS}>
      <h3 id={titleId} className={GENERAL_NOTES_TITLE_CLASS}>
        <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
        {t('requestManagement.workPanel.generalNotes.title')}
      </h3>
      <Textarea
        {...field}
        aria-label={t('requestManagement.form.create.generalNotes.label')}
        rows={4}
        placeholder={t('requestManagement.form.create.generalNotes.placeholder')}
        className="mt-2 min-h-0 resize-y border-0 bg-transparent p-0 text-sm leading-relaxed shadow-none focus-visible:ring-0 dark:bg-transparent"
      />
    </section>
  )
}
