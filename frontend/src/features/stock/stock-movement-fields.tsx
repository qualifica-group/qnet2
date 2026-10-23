import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { ArrowLeftRight, Package } from 'lucide-react'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import { StockPicker } from '@/features/stock/stock-movement-pickers'
import { requiresNote, requiresUser, type StockMovementFormValues } from '@/features/stock/stock-movement-schema'
import { STOCK_MOVEMENT_TYPES } from '@/features/stock/types'

function TypeField() {
  const { t } = useTranslation()
  const { control } = useFormContext<StockMovementFormValues>()

  return (
    <FormField
      control={control}
      name="type"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('stock.form.type')}</FormLabel>
          <Select value={field.value} onValueChange={field.onChange}>
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {STOCK_MOVEMENT_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`enums.stock_movement_type.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

interface TextFieldProps {
  name: 'quantity' | 'counted_quantity' | 'moved_on' | 'note'
  label: string
  required?: boolean
}

function TextField({ name, label, required = false }: TextFieldProps) {
  const { control } = useFormContext<StockMovementFormValues>()

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={required}>{label}</FormLabel>
          <FormControl>
            {name === 'note' ? (
              <Textarea rows={3} {...field} />
            ) : name === 'moved_on' ? (
              <Input type="date" max={dayOf(new Date())} {...field} />
            ) : (
              <Input inputMode="decimal" autoComplete="off" {...field} />
            )}
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

/** The movement form in two record sections: what moves and where, then how much, when and why. Fields change with the type (spec 0307 D-11/D-19). */
export function StockMovementFields() {
  const { t } = useTranslation()
  const { control } = useFormContext<StockMovementFormValues>()
  const type = useWatch({ control, name: 'type' })

  const userKey = requiresUser(type) ? (type === 'ASSIGN' ? 'recipient' : 'holder') : 'optionalHolder'

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('stock.form.sections.what')} icon={<Package />}>
        <TypeField />
        <StockPicker name="product_id" kind="product" label={t('stock.form.product')} required />
        <StockPicker
          name="operational_site_id"
          kind="site"
          label={t(type === 'TRANSFER' ? 'stock.form.fromSite' : 'stock.form.site')}
          required
        />
        {type === 'TRANSFER' ? (
          <StockPicker name="to_operational_site_id" kind="site" label={t('stock.form.toSite')} required />
        ) : null}
        {type !== 'LOAD' ? (
          <StockPicker name="user_id" kind="user" label={t(`stock.form.${userKey}`)} required={requiresUser(type)} />
        ) : null}
      </RecordSection>
      <RecordSection title={t('stock.form.sections.how')} icon={<ArrowLeftRight />}>
        <div className="grid gap-3 sm:grid-cols-2">
          {type === 'ADJUSTMENT' ? (
            <TextField name="counted_quantity" label={t('stock.form.countedQuantity')} required />
          ) : (
            <TextField name="quantity" label={t('stock.form.quantity')} required />
          )}
          <TextField name="moved_on" label={t('stock.form.movedOn')} />
        </div>
        <TextField name="note" label={t('stock.form.note')} required={requiresNote(type)} />
      </RecordSection>
    </RecordSectionsGrid>
  )
}
