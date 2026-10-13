import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE } from '@/features/purchase-request-priorities/for-select-api'
import { taskLookupOptionRenderer } from '@/features/tasks/task-lookup-option-renderer'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

type Control_ = Control<PurchaseRequestFormValues>

export type RelationFieldName =
  | 'purchase_request_priority_id'
  | 'requester_id'
  | 'function_manager_id'
  | 'customer_id'
  | 'work_order_id'
  | 'company_id'
  | 'company_site_id'
  | 'operational_site_id'
  | 'business_function_id'

export type TextFieldName =
  | 'subject'
  | 'requested_at'
  | 'notes'
  | 'delivery_terms'
  | 'procurement_plan'
  | 'technical_requirements'
  | 'special_conditions'

interface TextFieldProps {
  control: Control_
  name: TextFieldName
  type?: 'text' | 'date'
  multiline?: boolean
  required?: boolean
  className?: string
}

/** Metadata-aware text/date/textarea field of the header and footer; its label is `purchaseRequests.fields.<name>`. */
export function PurchaseTextField({ control, name, type = 'text', multiline = false, required, className }: TextFieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      label={t(`purchaseRequests.fields.${name}`)}
      required={required}
      className={className}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          {multiline ? (
            <Textarea className="min-h-16 text-sm" disabled={disabled} readOnly={readOnly} {...field} />
          ) : (
            <Input type={type} className="h-8 text-sm" disabled={disabled} readOnly={readOnly} {...field} />
          )}
        </FormControl>
      )}
    </MetaField>
  )
}

interface RelationFieldProps {
  control: Control_
  name: RelationFieldName
  resource: string
  selected: RelationFieldRef | null
  required?: boolean
  params?: Record<string, string | number>
  forceDisabled?: boolean
  onValueChange?: (next: number | null) => void
  onItemChange?: (item: ForSelectItem | null) => void
  renderItem?: (item: ForSelectItem) => ReactNode
}

/** Metadata-aware relation picker bound to one id of the form; labels from `purchaseRequests.fields/select`. */
export function PurchaseRelationField({ name, ...rest }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      {...rest}
      name={name}
      metaKey={name}
      label={t(`purchaseRequests.fields.${name}`)}
      placeholder={t('purchaseRequests.select.placeholder')}
      searchPlaceholder={t('purchaseRequests.select.search')}
      emptyLabel={t('purchaseRequests.select.empty')}
      errorLabel={t('purchaseRequests.select.error')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** Priority picker over the configurable catalog, each option drawn as the same colored badge as the grid. */
export function PrioritySelectField({ control, selected }: { control: Control_; selected: RelationFieldRef | null }) {
  return (
    <PurchaseRelationField
      control={control}
      name="purchase_request_priority_id"
      resource={PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE}
      selected={selected}
      required
      renderItem={taskLookupOptionRenderer}
    />
  )
}
