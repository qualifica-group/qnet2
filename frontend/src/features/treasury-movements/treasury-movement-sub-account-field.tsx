import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { subAccountValue, type TreasuryMovementFormValues } from '@/features/treasury-movements/treasury-movement-schema'
import type { TreasurySubAccountRef } from '@/features/treasury-movements/types'
import { useSubAccountGroups } from '@/features/treasury-movements/use-sub-account-options'

interface SubAccountFieldProps {
  /** Sub-account already saved on the movement being edited (kept selectable even if deactivated). */
  saved: TreasurySubAccountRef | null
}

/** Mandatory sub-account select: only the kinds the movement type allows, grouped by kind. */
export function TreasurySubAccountField({ saved }: SubAccountFieldProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<TreasuryMovementFormValues>()
  const type = useWatch({ control, name: 'type' })
  const groups = useSubAccountGroups(type, saved)

  return (
    <FormField
      control={control}
      name="sub_account"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('treasuryMovements.form.subAccount')}</FormLabel>
          <Select value={field.value} onValueChange={field.onChange}>
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t('treasuryMovements.form.pick')} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {groups.map((group) => (
                <SelectGroup key={group.kind}>
                  <SelectLabel>{t(`treasuryMovements.subAccountKind.${group.kind}`)}</SelectLabel>
                  {group.options.map((option) => (
                    <SelectItem key={option.id} value={subAccountValue(option.kind, option.id)}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
