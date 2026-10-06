import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { AttributeLayoutView } from '@/features/attributes/attribute-layout-view'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

interface WorkOrderDetailAttributesSectionProps {
  attributes: ApplicableAttributeSummary[]
  values: Record<string, unknown>
  /** `WorkOrderResource.attribute_layout` (FormMode::View); `null` -> flat list. */
  layout: LayoutBlob | null
  className?: string
}

/**
 * Read-only "Informazioni aggiuntive" on the work order record (spec 0098,
 * AC-023), laid out like the form: the configured sections, columns and
 * widths through `AttributeLayoutView`, every applicable Attribute shown (an
 * unset one with the empty placeholder).
 *
 * Absent entirely when the work order's own quote lines resolve no
 * applicable Attribute (D-1: no line linked, or those categories configure
 * none). Unlike the form, there is no empty-state card here: on a read-only
 * view an empty section carries no information the reader can act on.
 */
export function WorkOrderDetailAttributesSection({
  attributes,
  values,
  layout,
  className,
}: WorkOrderDetailAttributesSectionProps) {
  const { t } = useTranslation()

  if (attributes.length === 0) {
    return null
  }

  return (
    <RecordSection title={t('workOrders.detail.additionalInformation')} icon={<ClipboardList />} className={className}>
      <AttributeLayoutView layout={layout} attributes={attributes} values={values} />
    </RecordSection>
  )
}
