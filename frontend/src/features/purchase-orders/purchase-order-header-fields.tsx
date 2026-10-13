import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import {
  OrderCopiesField,
  OrderInternalUserField,
  OrderRelationField,
  OrderSectorsField,
  OrderTextField,
} from '@/features/purchase-orders/purchase-order-fields'
import type { OrderRefs } from '@/features/purchase-orders/purchase-order-refs'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { OrderSupplierField } from '@/features/purchase-orders/purchase-order-supplier-field'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import { QUOTES_FOR_SELECT_RESOURCE } from '@/features/work-orders/work-order-relation-fields'

const NO_SECTORS: PurchaseOrder['sectors'] = []

interface PurchaseOrderHeaderFieldsProps {
  control: Control<PurchaseOrderFormValues>
  setValue: UseFormSetValue<PurchaseOrderFormValues>
  refs: OrderRefs
  /** The loaded ODA (edit): number and status are shown read-only and the copies field is hidden. */
  order?: PurchaseOrder
  companyId: number | null
  supplierId: number | null
  supplierQualified: boolean
  /** A new header work order is proposed to the new lines without one. */
  onWorkOrderChange: (workOrderId: number | null) => void
}

/** Header of the ODA: supplier, organization, dates, payment, links, EA tags and notes. */
export function PurchaseOrderHeaderFields({
  control,
  setValue,
  refs,
  order,
  companyId,
  supplierId,
  supplierQualified,
  onWorkOrderChange,
}: PurchaseOrderHeaderFieldsProps) {
  const { t } = useTranslation()
  const { nameOf } = usePurchaseOrderStatusCatalog()

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {order ? (
        <>
          <div className="grid gap-1.5">
            <Label htmlFor="purchase-order-code">{t('purchaseOrderForm.fields.code')}</Label>
            <Input id="purchase-order-code" className="h-8 text-sm" value={order.code} readOnly disabled />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="purchase-order-status">{t('purchaseOrderForm.fields.status')}</Label>
            <Input
              id="purchase-order-status"
              className="h-8 text-sm"
              value={nameOf(order.status)}
              readOnly
              disabled
            />
          </div>
        </>
      ) : null}
      <OrderSupplierField
        control={control}
        setValue={setValue}
        selected={refs.supplier_id}
        supplierId={supplierId}
        qualified={supplierQualified}
      />
      <OrderRelationField
        control={control}
        name="company_id"
        resource={COMPANIES_FOR_SELECT_RESOURCE}
        selected={refs.company_id}
        required
        onValueChange={() => setValue('company_site_id', null, { shouldDirty: true })}
      />
      <OrderRelationField
        control={control}
        name="company_site_id"
        resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
        selected={refs.company_site_id}
        params={companyId === null ? undefined : { company_id: companyId }}
        forceDisabled={companyId === null}
        required
      />
      <OrderRelationField
        control={control}
        name="operational_site_id"
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        selected={refs.operational_site_id}
      />
      <OrderTextField control={control} name="order_date" type="date" required />
      {order ? null : <OrderCopiesField control={control} />}
      <OrderTextField control={control} name="description" required className="sm:col-span-2" />
      <OrderRelationField
        control={control}
        name="payment_method_id"
        resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
        selected={refs.payment_method_id}
      />
      <OrderRelationField
        control={control}
        name="customer_id"
        resource={REGISTRIES_FOR_SELECT_RESOURCE}
        selected={refs.customer_id}
      />
      <OrderRelationField
        control={control}
        name="work_order_id"
        resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
        selected={refs.work_order_id}
        onValueChange={onWorkOrderChange}
      />
      <OrderRelationField
        control={control}
        name="quote_id"
        resource={QUOTES_FOR_SELECT_RESOURCE}
        selected={refs.quote_id}
      />
      <OrderRelationField
        control={control}
        name="opportunity_id"
        resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
        selected={refs.opportunity_id}
      />
      <OrderSectorsField control={control} selected={order?.sectors ?? NO_SECTORS} />
      <OrderTextField control={control} name="sector_notes" />
      <OrderInternalUserField control={control} />
      <OrderTextField control={control} name="general_notes" multiline className="sm:col-span-2 lg:col-span-3" />
    </div>
  )
}
