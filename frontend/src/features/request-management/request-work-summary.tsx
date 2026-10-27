import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import type { RequestWorkPanel } from '@/features/request-management/types'

/**
 * Read-only commercial context of the record (spec 0049), rendered as the side
 * column of the work panel. Never renders an edit control.
 *
 * The funzione/categoria rows left this summary when they became editable
 * (user directive 2026-07-31), and so did the forecast trio (spec 0313):
 * showing the persisted value next to the field that edits it would
 * contradict it until the next save.
 */
export function RequestWorkSummary({ panel }: { panel: RequestWorkPanel }) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Info}
      title={t('requestManagement.workPanel.summary.title', { defaultValue: 'Request summary' })}
      description={t('requestManagement.workPanel.summary.description', {
        defaultValue: 'Read-only commercial context.',
      })}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('requestManagement.workPanel.summary.registry', { defaultValue: 'Client' })}>
          {panel.registry?.name ?? EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('requestManagement.workPanel.summary.referent', { defaultValue: 'Contact' })}>
          {panel.referent?.name ?? EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('requestManagement.workPanel.summary.commercial', { defaultValue: 'Sales rep' })}>
          {panel.commercial?.name ?? EMPTY_VALUE}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}
