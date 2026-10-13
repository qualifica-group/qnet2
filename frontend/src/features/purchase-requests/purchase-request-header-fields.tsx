import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { PurchaseRequestApproverField } from '@/features/purchase-requests/purchase-request-approver-field'
import {
  PrioritySelectField,
  PurchaseRelationField,
  PurchaseTextField,
} from '@/features/purchase-requests/purchase-request-fields'
import type { RequestRefs } from '@/features/purchase-requests/purchase-request-refs'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

interface PurchaseRequestHeaderFieldsProps {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  refs: RequestRefs
  companyId: number | null
  createdByName: string
}

/** Header of the RDA: subject, dates, people, organization and customer (the supplier lives on the lines). */
export function PurchaseRequestHeaderFields({
  control,
  setValue,
  refs,
  companyId,
  createdByName,
}: PurchaseRequestHeaderFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <PurchaseTextField control={control} name="subject" required className="sm:col-span-2" />
      <PurchaseTextField control={control} name="requested_at" type="date" required />
      <PrioritySelectField control={control} />
      <PurchaseRelationField
        control={control}
        name="requester_id"
        resource={USERS_FOR_SELECT_RESOURCE}
        selected={refs.requester_id}
        required
      />
      <div className="grid gap-1.5">
        <Label htmlFor="purchase-request-created-by">{t('purchaseRequests.fields.created_by')}</Label>
        <Input id="purchase-request-created-by" className="h-8 text-sm" value={createdByName} readOnly disabled />
      </div>
      <PurchaseRelationField
        control={control}
        name="company_id"
        resource={COMPANIES_FOR_SELECT_RESOURCE}
        selected={refs.company_id}
        required
        onValueChange={() => setValue('company_site_id', null, { shouldDirty: true })}
      />
      <PurchaseRelationField
        control={control}
        name="company_site_id"
        resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
        selected={refs.company_site_id}
        params={companyId === null ? undefined : { company_id: companyId }}
        forceDisabled={companyId === null}
        required
      />
      <PurchaseRelationField
        control={control}
        name="operational_site_id"
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        selected={refs.operational_site_id}
        required
      />
      <PurchaseRelationField
        control={control}
        name="business_function_id"
        resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
        selected={refs.business_function_id}
        required
      />
      <PurchaseRequestApproverField control={control} setValue={setValue} refs={refs} />
      <PurchaseRelationField
        control={control}
        name="customer_id"
        resource={REGISTRIES_FOR_SELECT_RESOURCE}
        selected={refs.customer_id}
      />
      <PurchaseRelationField
        control={control}
        name="work_order_id"
        resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
        selected={refs.work_order_id}
      />
    </div>
  )
}
