import { useTranslation } from 'react-i18next'
import { CalendarClock, FileText } from 'lucide-react'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import {
  WorkOrderCallbackDateRow,
  WorkOrderCodeRow,
  WorkOrderDescriptionRow,
  WorkOrderTypeRow,
} from '@/features/work-orders/work-order-record-rows'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderType } from '@/features/work-orders/types'

interface WorkOrderDescriptionSectionsProps {
  form: WorkOrderFormState
  inline: InlineEdit
  values: { code: string; type: WorkOrderType; callback_date: string | null; description: string | null }
}

/**
 * The legacy right column under "Riferimenti Società" (user directive
 * 2026-11-16): "Data richiamo", then "Descrizione commessa" with Commessa n.,
 * Tipo commessa and the description. Every row edits in place.
 */
export function WorkOrderDescriptionSections({ values, form, inline }: WorkOrderDescriptionSectionsProps) {
  const { t } = useTranslation()

  return (
    <>
      <RecordSection title={t('workOrders.form.callbackDate')} icon={<CalendarClock />}>
        <RecordFieldList>
          <WorkOrderCallbackDateRow form={form} inline={inline} callbackDate={values.callback_date} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('workOrders.detail.sections.description')} icon={<FileText />}>
        <RecordFieldList>
          <WorkOrderCodeRow form={form} inline={inline} code={values.code} />
          <WorkOrderTypeRow form={form} inline={inline} type={values.type} />
          <WorkOrderDescriptionRow form={form} inline={inline} description={values.description} />
        </RecordFieldList>
      </RecordSection>
    </>
  )
}
