import { useTranslation } from 'react-i18next'
import { Lock } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { formatDate } from '@/lib/formatting/date-display'
import {
  WorkOrderCallbackDateField,
  WorkOrderCodeField,
  WorkOrderStartDateField,
  WorkOrderTitleField,
  WorkOrderTypeField,
} from '@/features/work-orders/work-order-identity-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderType } from '@/features/work-orders/types'

/**
 * One row per work order field, each editing in place on its own control
 * (spec 0195 applied to Commesse). Shared by the create form's "Dati" section
 * and the detail, where the rows are spread over "Dati contrattuali
 * commessa" and "Descrizione commessa" (user directives 2026-11-16).
 */

interface RowProps {
  form: WorkOrderFormState
  inline: InlineEdit
}

/** "Titolo". */
export function WorkOrderTitleRow({ form, inline, title }: RowProps & { title: string }) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="title"
      label={t('workOrders.form.title')}
      inline={inline}
      editor={<WorkOrderTitleField control={form.form.control} />}
    >
      {title.trim() !== '' ? title : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** "Data inizio". */
export function WorkOrderStartDateRow({ form, inline, startDate }: RowProps & { startDate: string | null }) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="start_date"
      label={t('workOrders.form.startDate')}
      inline={inline}
      editor={<WorkOrderStartDateField control={form.form.control} />}
    >
      {formatDate(startDate) || <DetailEmpty />}
    </RecordInlineField>
  )
}

/** "Motivo chiusura": only while the closure is forced and a reason was written (the actions menu's "Chiusura forzata"), read-only here. */
export function WorkOrderForceCloseReasonRow({ reason }: { reason: string | null }) {
  const { t } = useTranslation()

  if (reason === null) {
    return null
  }

  return (
    <RecordField label={t('workOrders.detail.forceCloseReason')} icon={<Lock />}>
      <span className="whitespace-pre-wrap">{reason}</span>
    </RecordField>
  )
}

/** "Commessa n.", edited in place where its field permission allows it (written at creation on the detail). */
export function WorkOrderCodeRow({ form, inline, code }: RowProps & { code: string }) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="code"
      label={t('workOrders.form.code')}
      inline={inline}
      editor={<WorkOrderCodeField control={form.form.control} />}
    >
      {code.trim() !== '' ? code : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** "Tipo commessa" (Lavorazione / Progetto). */
export function WorkOrderTypeRow({ form, inline, type }: RowProps & { type: WorkOrderType }) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="type"
      label={t('workOrders.form.type')}
      inline={inline}
      editor={<WorkOrderTypeField control={form.form.control} />}
    >
      {t(`workOrders.options.type.${type}`)}
    </RecordInlineField>
  )
}

/** "Data richiamo". */
export function WorkOrderCallbackDateRow({ form, inline, callbackDate }: RowProps & { callbackDate: string | null }) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="callback_date"
      label={t('workOrders.form.callbackDate')}
      inline={inline}
      editor={<WorkOrderCallbackDateField control={form.form.control} />}
    >
      {formatDate(callbackDate) || <DetailEmpty />}
    </RecordInlineField>
  )
}

