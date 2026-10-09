import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuickCreatePresets } from '@/features/quick-create/types'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import { PRIORITIES } from '@/features/purchase-requests/types'

type Control_ = Control<PurchaseRequestFormValues>

export type RelationFieldName =
  | 'requester_id'
  | 'function_manager_id'
  | 'customer_id'
  | 'supplier_id'
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

/** Priority select (closed set, translated labels). */
export function PrioritySelectField({ control }: { control: Control_ }) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="priority" metaKey="priority" label={t('purchaseRequests.fields.priority')} required>
      {({ field, disabled }) => (
        <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="h-8 w-full text-sm">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {PRIORITIES.map((priority) => (
              <SelectItem key={priority} value={priority}>
                {t(`purchaseRequests.priorities.${priority}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
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
  quickCreatePresets?: QuickCreatePresets
  onValueChange?: (next: number | null) => void
  onItemChange?: (item: ForSelectItem | null) => void
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
