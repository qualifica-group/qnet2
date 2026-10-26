import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OrderRelationFieldName } from '@/features/purchase-orders/purchase-order-refs'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import type { QuickCreatePresets } from '@/features/quick-create/types'
import { SECTORS_FOR_SELECT_RESOURCE } from '@/features/sectors/for-select-api'
import { MAX_COPIES, MIN_COPIES } from '@/features/purchase-orders/types'

type Control_ = Control<PurchaseOrderFormValues>

type TextFieldName = 'order_date' | 'description' | 'general_notes' | 'sector_notes'

interface TextFieldProps {
  control: Control_
  name: TextFieldName
  type?: 'text' | 'date'
  multiline?: boolean
  required?: boolean
  className?: string
}

/** Metadata-aware text/date/textarea field of the header; its label is `purchaseOrderForm.fields.<name>`. */
export function OrderTextField({ control, name, type = 'text', multiline = false, required, className }: TextFieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      label={t(`purchaseOrderForm.fields.${name}`)}
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

/** Copies of the monthly series; shown by the caller in creation only (D-16). */
export function OrderCopiesField({ control }: { control: Control_ }) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="copies"
      metaKey="copies"
      label={t('purchaseOrderForm.fields.copies')}
      hint={t('purchaseOrderForm.copiesHint')}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Input
            type="number"
            min={MIN_COPIES}
            max={MAX_COPIES}
            step={1}
            className="h-8 text-sm"
            disabled={disabled}
            {...field}
            value={Number.isNaN(field.value) ? '' : field.value}
            onChange={(event) => field.onChange(event.target.value === '' ? Number.NaN : Number(event.target.value))}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Fruitore interno" switch. */
export function OrderInternalUserField({ control }: { control: Control_ }) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="is_internal_user"
      metaKey="is_internal_user"
      label={t('purchaseOrderForm.fields.is_internal_user')}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface SectorsFieldProps {
  control: Control_
  /** Saved sectors of the ODA, so the badges show their names without a lookup. */
  selected: RelationFieldRef[]
}

/** EA tags: multi-select of sectors. */
export function OrderSectorsField({ control, selected }: SectorsFieldProps) {
  const { t } = useTranslation()
  const label = t('purchaseOrderForm.fields.sector_ids')
  const { quickCreated, renderAction } = useQuickCreateAction(SECTORS_FOR_SELECT_RESOURCE)
  const selectedItems: ForSelectItem[] = [...selected, ...quickCreated].map((sector) => ({ id: sector.id, label: sector.name }))
  return (
    <MetaField control={control} name="sector_ids" metaKey="sector_ids" label={label}>
      {({ field, disabled }) => (
        <FormControl>
          <AsyncPaginatedMultiSelect
            resource={SECTORS_FOR_SELECT_RESOURCE}
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedItems}
            disabled={disabled}
            labels={{
              placeholder: t('purchaseOrderForm.select.placeholder'),
              searchPlaceholder: t('purchaseOrderForm.select.search'),
              empty: t('purchaseOrderForm.select.empty'),
              error: t('purchaseOrderForm.select.error'),
              removeLabel: t('purchaseOrderForm.select.removeLabel'),
              triggerLabel: label,
              retry: t('common.retry'),
            }}
            action={renderAction((ref) => {
              if (!field.value.includes(ref.id)) {
                field.onChange([...field.value, ref.id])
              }
            }, disabled)}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface RelationFieldProps {
  control: Control_
  name: OrderRelationFieldName
  resource: string
  selected: RelationFieldRef | null
  required?: boolean
  params?: Record<string, string | number>
  forceDisabled?: boolean
  quickCreatePresets?: QuickCreatePresets
  onValueChange?: (next: number | null) => void
  onItemChange?: (item: ForSelectItem | null) => void
}

/** Metadata-aware relation picker bound to one id of the form; labels from `purchaseOrderForm.fields/select`. */
export function OrderRelationField({ name, ...rest }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      {...rest}
      name={name}
      metaKey={name}
      label={t(`purchaseOrderForm.fields.${name}`)}
      placeholder={t('purchaseOrderForm.select.placeholder')}
      searchPlaceholder={t('purchaseOrderForm.select.search')}
      emptyLabel={t('purchaseOrderForm.select.empty')}
      errorLabel={t('purchaseOrderForm.select.error')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
