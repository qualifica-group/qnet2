import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useEnumOptions } from '@/features/config/use-config'
import { CityPickerField } from '@/features/personal-data/city-picker-field'
import { resolveGate } from '@/features/personal-data/personal-data-field-gate'
import type { PersonalDataFormValues } from '@/features/personal-data/personal-data-schema'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

interface PersonalDataIndividualFieldsProps {
  control: Control<PersonalDataFormValues>
  /** The parent buffer, read only for the hydrated comune labels. */
  value: PersonalDataDraft
  fieldPermission?: PersonalDataFieldPermissionResolver
}

/**
 * The natural-person-only part of the card: date of birth, gender and the two
 * comuni (birth and residence). Rendered by `PersonalDataCardForm` solely for
 * an `individual` card — a company carries none of these, so the caller decides
 * whether to mount this block at all and this component only resolves the
 * per-field gating within it.
 */
export function PersonalDataIndividualFields({
  control,
  value,
  fieldPermission,
}: PersonalDataIndividualFieldsProps) {
  const { t } = useTranslation()
  const genderOptions = useEnumOptions('gender')

  const birthDateGate = resolveGate(fieldPermission, 'personal_data.birth_date', false)
  const genderGate = resolveGate(fieldPermission, 'personal_data.gender', false)
  const birthCityGate = resolveGate(fieldPermission, 'personal_data.birth_city_id', false)
  const residenceCityGate = resolveGate(fieldPermission, 'personal_data.residence_city_id', false)

  return (
    <>
      {(birthDateGate.visible || genderGate.visible) && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {birthDateGate.visible && (
            <FormField
              control={control}
              name="birth_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required={birthDateGate.required}>
                    {t('personalData.form.birthDate')}
                  </FormLabel>
                  <FormControl>
                    <Input
                      type="date"
                      disabled={birthDateGate.disabled}
                      readOnly={birthDateGate.readOnly}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
          {genderGate.visible && (
            <FormField
              control={control}
              name="gender"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required={genderGate.required}>
                    {t('personalData.form.gender')}
                  </FormLabel>
                  <Select
                    value={field.value}
                    onValueChange={field.onChange}
                    disabled={genderGate.disabled || genderGate.readOnly}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {genderOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
        </div>
      )}

      {(birthCityGate.visible || residenceCityGate.visible) && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {birthCityGate.visible && (
            <FormField
              control={control}
              name="birth_city_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required={birthCityGate.required}>
                    {t('personalData.form.birthCity')}
                  </FormLabel>
                  <FormControl>
                    <CityPickerField
                      value={field.value ?? null}
                      hydrated={value.birth_city}
                      onChange={field.onChange}
                      placeholder={t('personalData.form.birthCityPlaceholder')}
                      disabled={birthCityGate.disabled || birthCityGate.readOnly}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
          {residenceCityGate.visible && (
            <FormField
              control={control}
              name="residence_city_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required={residenceCityGate.required}>
                    {t('personalData.form.residenceCity')}
                  </FormLabel>
                  <FormControl>
                    <CityPickerField
                      value={field.value ?? null}
                      hydrated={value.residence_city}
                      onChange={field.onChange}
                      placeholder={t('personalData.form.residenceCityPlaceholder')}
                      disabled={residenceCityGate.disabled || residenceCityGate.readOnly}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
        </div>
      )}
    </>
  )
}
