import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import { ENASARCO_MANDATE_TYPES } from '@/features/agent-fiscal-profiles/types'

interface TaxFieldsProps {
  control: Control<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>
}

type PercentFieldName = 'withholding_rate' | 'withholding_base_percent'

function PercentField({ control, name, label }: TaxFieldsProps & { name: PercentFieldName; label: string }) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            inputMode="decimal"
            step="0.01"
            min={0}
            max={100}
            disabled={disabled}
            readOnly={readOnly}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** Withholding switch plus its two percentages, shown only while the withholding is active. */
export function WithholdingFields({ control }: TaxFieldsProps) {
  const { t } = useTranslation()
  const enabled = useWatch({ control, name: 'withholding_enabled' })

  return (
    <>
      <MetaField
        control={control}
        name="withholding_enabled"
        metaKey="withholding_enabled"
        label={t('agentFiscalProfiles.form.withholdingEnabled')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>
      {enabled ? (
        <div className="grid gap-3 sm:grid-cols-2">
          <PercentField control={control} name="withholding_rate" label={t('agentFiscalProfiles.form.withholdingRate')} />
          <PercentField
            control={control}
            name="withholding_base_percent"
            label={t('agentFiscalProfiles.form.withholdingBasePercent')}
          />
        </div>
      ) : null}
    </>
  )
}

/** ENASARCO switch plus the mandate type, shown only while ENASARCO is active. */
export function EnasarcoFields({ control }: TaxFieldsProps) {
  const { t } = useTranslation()
  const enabled = useWatch({ control, name: 'enasarco_enabled' })

  return (
    <>
      <MetaField
        control={control}
        name="enasarco_enabled"
        metaKey="enasarco_enabled"
        label={t('agentFiscalProfiles.form.enasarcoEnabled')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>
      {enabled ? (
        <MetaField
          control={control}
          name="enasarco_mandate_type"
          metaKey="enasarco_mandate_type"
          label={t('agentFiscalProfiles.form.mandateType')}
        >
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('agentFiscalProfiles.form.mandateTypePlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {ENASARCO_MANDATE_TYPES.map((value) => (
                  <SelectItem key={value} value={value}>
                    {t(`enums.enasarco_mandate_type.${value}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      ) : null}
    </>
  )
}
