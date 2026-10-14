import { useTranslation } from 'react-i18next'
import { Building2, ClipboardList, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { QUOTES_FOR_SELECT_RESOURCE } from '@/features/work-orders/work-order-relation-fields'
import { formatDate } from '@/lib/formatting/date-display'
import {
  OrderInternalUserField,
  OrderRelationField,
  OrderSectorsField,
  OrderTextField,
} from '@/features/purchase-orders/purchase-order-fields'
import { orderRefs, type OrderRelationFieldName } from '@/features/purchase-orders/purchase-order-refs'
import { OrderSupplierField } from '@/features/purchase-orders/purchase-order-supplier-field'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import type { PurchaseOrderDetailEditor } from '@/features/purchase-orders/use-purchase-order-inline-edit'

interface SectionProps {
  order: PurchaseOrder
  editor: PurchaseOrderDetailEditor
}

/** The optional link pickers of "Organizzazione e collegamenti", in display order. */
const LINK_FIELDS: readonly { name: OrderRelationFieldName; resource: string; domain?: string }[] = [
  { name: 'operational_site_id', resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE },
  { name: 'customer_id', resource: REGISTRIES_FOR_SELECT_RESOURCE, domain: 'registries' },
  { name: 'work_order_id', resource: WORK_ORDERS_FOR_SELECT_RESOURCE, domain: 'work-orders' },
  { name: 'quote_id', resource: QUOTES_FOR_SELECT_RESOURCE, domain: 'quotes' },
  { name: 'opportunity_id', resource: OPPORTUNITIES_FOR_SELECT_RESOURCE, domain: 'opportunities' },
]

function TextValue({ value }: { value: string | null | undefined }) {
  return value && value.trim() !== '' ? (
    <span className="break-words whitespace-pre-wrap">{value}</span>
  ) : (
    <DetailEmpty />
  )
}

/** "Ordine": number, supplier, date, description, payment, internal user and author. */
function OrderSection({ order, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline, supplierId, supplierQualified } = editor
  const { control, setValue } = form
  const refs = orderRefs(order)
  const label = (field: string) => t(`purchaseOrderForm.fields.${field}`)

  return (
    <RecordSection title={t('purchaseOrderForm.sections.header')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordField label={label('code')}>
          <span>{order.code}</span>
        </RecordField>
        <RecordInlineField
          field="supplier_id"
          label={label('supplier_id')}
          inline={inline}
          editor={
            <OrderSupplierField
              control={control}
              setValue={setValue}
              selected={refs.supplier_id}
              supplierId={supplierId}
              qualified={supplierQualified}
            />
          }
        >
          <span className="flex flex-col gap-0.5">
            <RecordLink domain="registries" id={order.supplier.id}>
              {order.supplier.name}
            </RecordLink>
            {order.supplier.is_qualified_supplier ? null : (
              <span className="text-xs text-destructive">{t('purchaseOrderForm.supplier.unqualifiedWarning')}</span>
            )}
          </span>
        </RecordInlineField>
        <RecordInlineField
          field="order_date"
          label={label('order_date')}
          inline={inline}
          editor={<OrderTextField control={control} name="order_date" type="date" required />}
        >
          <span>{formatDate(order.order_date) || '—'}</span>
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={label('description')}
          inline={inline}
          editor={<OrderTextField control={control} name="description" required />}
        >
          <TextValue value={order.description} />
        </RecordInlineField>
        <RecordInlineField
          field="payment_method_id"
          label={label('payment_method_id')}
          inline={inline}
          editor={
            <OrderRelationField control={control} name="payment_method_id" resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE} selected={refs.payment_method_id} />
          }
        >
          <TextValue value={order.payment_method?.name} />
        </RecordInlineField>
        <RecordInlineField
          field="is_internal_user"
          label={label('is_internal_user')}
          inline={inline}
          editor={<OrderInternalUserField control={control} />}
        >
          <span>{t(order.is_internal_user ? 'common.yes' : 'common.no')}</span>
        </RecordInlineField>
        <RecordField label={t('purchaseOrderForm.fields.created_by')}>
          <span>{order.created_by.name}</span>
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Organizzazione e collegamenti": the company (locked once created) and its site, then the optional links. */
function OrganizationSection({ order, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form
  const refs = orderRefs(order)
  const label = (field: string) => t(`purchaseOrderForm.fields.${field}`)

  return (
    <RecordSection title={t('purchaseOrderForm.sections.organization')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordField label={label('company_id')}>
          <span>{order.company.name}</span>
        </RecordField>
        <RecordInlineField
          field="company_site_id"
          label={label('company_site_id')}
          inline={inline}
          editor={
            <OrderRelationField
              control={control}
              name="company_site_id"
              resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
              selected={refs.company_site_id}
              params={{ company_id: order.company.id }}
              required
            />
          }
        >
          <span>{order.company_site.name}</span>
        </RecordInlineField>
        {LINK_FIELDS.map(({ name, resource, domain }) => {
          const ref = refs[name]
          return (
            <RecordInlineField
              key={name}
              field={name}
              label={label(name)}
              inline={inline}
              editor={<OrderRelationField control={control} name={name} resource={resource} selected={ref} />}
            >
              {ref === null ? (
                <DetailEmpty />
              ) : domain ? (
                <RecordLink domain={domain} id={ref.id}>
                  {ref.name}
                </RecordLink>
              ) : (
                <span>{ref.name}</span>
              )}
            </RecordInlineField>
          )
        })}
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Tag EA e note": sectors, their specification and the general notes. */
function NotesSection({ order, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form
  const label = (field: string) => t(`purchaseOrderForm.fields.${field}`)

  return (
    <RecordSection title={t('purchaseOrderForm.sections.notes')} icon={<Tags />} full>
      <RecordFieldList>
        <RecordInlineField
          field="sector_ids"
          label={label('sector_ids')}
          inline={inline}
          editor={<OrderSectorsField control={control} selected={order.sectors} />}
        >
          {order.sectors.length > 0 ? (
            <span className="flex flex-wrap gap-1">
              {order.sectors.map((sector) => (
                <Badge key={sector.id} variant="secondary">
                  {sector.name}
                </Badge>
              ))}
            </span>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="sector_notes"
          label={label('sector_notes')}
          inline={inline}
          editor={<OrderTextField control={control} name="sector_notes" />}
        >
          <TextValue value={order.sector_notes} />
        </RecordInlineField>
        <RecordInlineField
          field="general_notes"
          label={label('general_notes')}
          inline={inline}
          editor={<OrderTextField control={control} name="general_notes" multiline />}
        >
          <TextValue value={order.general_notes} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The ODA detail's header, every user-written field editable in place (spec 0257 D-6). */
export function PurchaseOrderRecordSections(props: SectionProps) {
  return (
    <>
      <OrderSection {...props} />
      <OrganizationSection {...props} />
      <NotesSection {...props} />
    </>
  )
}
