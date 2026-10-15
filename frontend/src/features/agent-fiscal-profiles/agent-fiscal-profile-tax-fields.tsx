import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
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
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
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

type ProfileFieldName = keyof AgentFiscalProfileFormValues

/** The refusal of a field a grouped row does not render while closed: it must still show on its row. */
export function SecondaryFieldError({ names }: { names: ProfileFieldName[] }) {
  const { formState } = useFormContext<AgentFiscalProfileFormValues>()
  const message = names.map((name) => formState.errors[name]?.message).find(Boolean)

  return message ? (
    <p role="alert" className="mt-1 text-xs font-medium text-destructive">
      {message}
    </p>
  ) : null
}

interface TaxRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** Switch, rate and base are ONE row (spec 0264 D-4): the server requires or refuses the rates with the switch. */
export function WithholdingRow({ inline, canEdit }: TaxRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>()
  const { field } = useResourcePermissions()
  const [enabled, rate, base] = useWatch({
    control,
    name: ['withholding_enabled', 'withholding_rate', 'withholding_base_percent'],
  })
  const ratesVisible = field('withholding_rate').visible && field('withholding_base_percent').visible

  return (
    <RecordInlineField
      field="withholding_enabled"
      label={t('agentFiscalProfiles.detail.withholdingEnabled')}
      inline={inline}
      canEdit={canEdit}
      editor={
        // The row's label names the group: each sub-field keeps its own visible label.
        <MetaFieldRowContext.Provider value={null}>
          <div className="flex flex-col gap-3">
            <WithholdingFields control={control} />
          </div>
        </MetaFieldRowContext.Provider>
      }
    >
      {enabled
        ? ratesVisible && rate !== '' && base !== ''
          ? t('agentFiscalProfiles.detail.withholdingSummary', { rate, base })
          : t('common.yes')
        : t('agentFiscalProfiles.detail.withholdingOff')}
      <SecondaryFieldError names={['withholding_rate', 'withholding_base_percent']} />
    </RecordInlineField>
  )
}

/** Switch and mandate type are ONE row (spec 0264 D-4): the server requires the mandate only with ENASARCO on. */
export function EnasarcoRow({ inline, canEdit }: TaxRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>()
  const [enabled, mandateType] = useWatch({ control, name: ['enasarco_enabled', 'enasarco_mandate_type'] })

  return (
    <RecordInlineField
      field="enasarco_enabled"
      label={t('agentFiscalProfiles.detail.enasarcoEnabled')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaFieldRowContext.Provider value={null}>
          <div className="flex flex-col gap-3">
            <EnasarcoFields control={control} />
          </div>
        </MetaFieldRowContext.Provider>
      }
    >
      {enabled ? (
        mandateType !== '' ? (
          t('agentFiscalProfiles.detail.enasarcoMandate', { mandate: t(`enums.enasarco_mandate_type.${mandateType}`) })
        ) : (
          <DetailEmpty />
        )
      ) : (
        t('agentFiscalProfiles.detail.enasarcoOff')
      )}
      <SecondaryFieldError names={['enasarco_mandate_type']} />
    </RecordInlineField>
  )
}
