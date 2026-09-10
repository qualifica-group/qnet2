import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { formatDateTimeOptionalTime } from '@/features/table/cell-renderers'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { ProductLineRow } from '@/features/product-lines/types'

interface RequestCreateSummaryProps {
  control: Control<RequestCreateFormValues>
  /** The buffered client card, when the "new client" branch is the one being filled (D-2). */
  identity: PersonalDataDraft
  usingExistingRegistry: boolean
}

/**
 * The create form's side-column recap, in the SAME card and the SAME `label /
 * value` rows as the work panel's `RequestWorkSummary` (user directive
 * 2026-07-31) — so the two screens have the same shape, not just the same
 * sections.
 *
 * What it lists is necessarily different: the panel summarises the commercial
 * context a saved record already has (referente, commerciale, valore stimato,
 * chiusura prevista), none of which exists before the first save. This one
 * summarises what the form is ABOUT to create, live — who the client is, how
 * many product lines were picked, when the callback is planned. Same purpose (read-only orientation while filling the long
 * column on the left), same chrome, honest content.
 */
export function RequestCreateSummary({
  control,
  identity,
  usingExistingRegistry,
}: RequestCreateSummaryProps) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })
  const nextCallbackAt = useWatch({ control, name: 'next_callback_at' })

  const completeLines = productLines.filter(
    (row: ProductLineRow) => row.business_function_id !== null && row.product_category_id !== null,
  ).length

  return (
    <FormSection
      icon={Info}
      title={t('requestManagement.workPanel.summary.title', { defaultValue: 'Request summary' })}
      description={t('requestManagement.form.create.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('requestManagement.workPanel.summary.registry', { defaultValue: 'Client' })}>
          {describeClient(identity, usingExistingRegistry, t)}
        </SummaryRow>
        <SummaryRow label={t('requestManagement.workPanel.productLines.title')}>
          {completeLines > 0 ? completeLines : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('requestManagement.workPanel.header.nextCallback', { defaultValue: 'Next callback' })}>
          {formatDateTimeOptionalTime(nextCallbackAt) || EMPTY_VALUE}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}

/**
 * The client as the form currently knows them: on the existing-registry branch
 * the picker's own trigger already names them, so this row only says WHICH
 * branch is in play; on the new-client branch it composes the name being typed.
 */
function describeClient(
  identity: PersonalDataDraft,
  usingExistingRegistry: boolean,
  t: (key: string) => string,
): string {
  if (usingExistingRegistry) {
    return t('requestManagement.form.create.summary.existingRegistry')
  }

  const composed =
    identity.type === 'company'
      ? (identity.company_name ?? '')
      : [identity.first_name, identity.last_name].filter(Boolean).join(' ')

  return composed.trim() || EMPTY_VALUE
}
