import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Contract bound (spec 0272): decimal(10,2) cap on the hourly cost. */
const MAX_HOURLY_COST = 9999.99

/** "Costo orario" (spec 0272): the person's gross company cost in euro/hour, two decimals. */
export function UserHourlyCostField({ control, label }: { control: Control<UserFormValues>; label: string }) {
  const { t } = useTranslation()
  const unitId = useId()
  return (
    <MetaField control={control} name="employment.hourly_cost" metaKey="employment.hourly_cost" label={label}>
      {({ field, disabled, readOnly }) => (
        <div className="flex items-center gap-2">
          <FormControl>
            <Input
              type="number"
              inputMode="decimal"
              step="0.01"
              min={0}
              max={MAX_HOURLY_COST}
              className="max-w-32"
              aria-describedby={unitId}
              disabled={disabled}
              readOnly={readOnly}
              value={field.value === null ? '' : String(field.value)}
              onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
          <span id={unitId} className="text-xs text-muted-foreground">{t('users.form.employment.hourlyCostUnit')}</span>
        </div>
      )}
    </MetaField>
  )
}
