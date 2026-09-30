import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Settings2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem } from '@/components/ui/form'
import { HintLabel } from '@/features/custom-fields/components/definition-hint-label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import type { CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionTypeConfigFieldsProps<T extends FieldDefinitionFormValues> {
  control: Control<T>
  type: CustomFieldType
}

/**
 * Types with no `config` options, so the config panel renders nothing:
 * `relation` and `table` (their settings live in dedicated editors) and the string-backed
 * scalars (date/datetime/time/email/url/color), which are plain native inputs.
 */
const TYPES_WITHOUT_CONFIG: readonly CustomFieldType[] = [
  'relation',
  'date',
  'datetime',
  'time',
  'email',
  'url',
  'color',
  'table',
]

/** Formats a nullable numeric config value for a controlled `<input type="number">` (mirrors `ProductFormBody`'s pattern). */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * Per-`type` config controls (spec AC-025): text→minLength/maxLength/regex/
 * transform; textarea→rows/maxLength; integer/decimal→min/max/step/decimals;
 * boolean→display(checkbox|switch); enum→display(select|multiselect|radio|
 * badge). Renders nothing for the config-less types (relation + the
 * string-backed scalars — see TYPES_WITHOUT_CONFIG). Not metadata-gated: it is
 * a per-type sub-form of the `type` field itself, mirroring the ENUM options
 * editor's status.
 *
 * Generic over `T` (see `DefinitionTypePicker` for why `Control<T>` cannot be
 * fixed to `Control<FieldDefinitionFormValues>`).
 */
export function DefinitionTypeConfigFields<T extends FieldDefinitionFormValues>({
  control,
  type,
}: DefinitionTypeConfigFieldsProps<T>) {
  const { t } = useTranslation()

  if (TYPES_WITHOUT_CONFIG.includes(type)) {
    return null
  }

  // See `DefinitionTypePicker`: `T` only ever adds fields on top of
  // `FieldDefinitionFormValues`, so every `config.*` path here is guaranteed
  // present; the narrowing happens once, here, not per field.
  const baseControl = control as unknown as Control<FieldDefinitionFormValues>

  return (
    <FormSection
      icon={Settings2}
      title={t('customFields.form.sections.config.title')}
      description={t('customFields.form.sections.config.description')}
    >
      {(type === 'text' || type === 'textarea') && (
        <FormField
          control={baseControl}
          name="config.maxLength"
          render={({ field }) => (
            <FormItem>
              <HintLabel
                hint={t('customFields.form.configMaxLengthHint')}
                hintLabel={t('customFields.form.configMaxLength')}
              >
                {t('customFields.form.configMaxLength')}
              </HintLabel>
              <FormControl>
                <Input
                  type="number"
                  min={0}
                  value={numberInputValue(field.value)}
                  onChange={(event) =>
                    field.onChange(event.target.value === '' ? null : Number(event.target.value))
                  }
                />
              </FormControl>
            </FormItem>
          )}
        />
      )}

      {type === 'text' && (
        <>
          <FormField
            control={baseControl}
            name="config.minLength"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configMinLengthHint')}
                  hintLabel={t('customFields.form.configMinLength')}
                >
                  {t('customFields.form.configMinLength')}
                </HintLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={0}
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={baseControl}
            name="config.regex"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configRegexHint')}
                  hintLabel={t('customFields.form.configRegex')}
                >
                  {t('customFields.form.configRegex')}
                </HintLabel>
                <FormControl>
                  <Input autoComplete="off" {...field} />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={baseControl}
            name="config.transform"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configTransformHint')}
                  hintLabel={t('customFields.form.configTransform')}
                >
                  {t('customFields.form.configTransform')}
                </HintLabel>
                <Select value={field.value || 'none'} onValueChange={(next) => field.onChange(next === 'none' ? '' : next)}>
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="none">{t('customFields.form.configTransformNone')}</SelectItem>
                    <SelectItem value="upper">{t('customFields.form.configTransformUpper')}</SelectItem>
                    <SelectItem value="lower">{t('customFields.form.configTransformLower')}</SelectItem>
                    <SelectItem value="capitalize">{t('customFields.form.configTransformCapitalize')}</SelectItem>
                  </SelectContent>
                </Select>
              </FormItem>
            )}
          />
        </>
      )}

      {type === 'textarea' && (
        <FormField
          control={baseControl}
          name="config.rows"
          render={({ field }) => (
            <FormItem>
              <HintLabel
                hint={t('customFields.form.configRowsHint')}
                hintLabel={t('customFields.form.configRows')}
              >
                {t('customFields.form.configRows')}
              </HintLabel>
              <FormControl>
                <Input
                  type="number"
                  min={1}
                  value={numberInputValue(field.value)}
                  onChange={(event) =>
                    field.onChange(event.target.value === '' ? null : Number(event.target.value))
                  }
                />
              </FormControl>
            </FormItem>
          )}
        />
      )}

      {(type === 'integer' || type === 'decimal') && (
        <>
          <FormField
            control={baseControl}
            name="config.min"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configMinHint')}
                  hintLabel={t('customFields.form.configMin')}
                >
                  {t('customFields.form.configMin')}
                </HintLabel>
                <FormControl>
                  <Input
                    type="number"
                    step={type === 'decimal' ? '0.01' : '1'}
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={baseControl}
            name="config.max"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configMaxHint')}
                  hintLabel={t('customFields.form.configMax')}
                >
                  {t('customFields.form.configMax')}
                </HintLabel>
                <FormControl>
                  <Input
                    type="number"
                    step={type === 'decimal' ? '0.01' : '1'}
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={baseControl}
            name="config.step"
            render={({ field }) => (
              <FormItem>
                <HintLabel
                  hint={t('customFields.form.configStepHint')}
                  hintLabel={t('customFields.form.configStep')}
                >
                  {t('customFields.form.configStep')}
                </HintLabel>
                <FormControl>
                  <Input
                    type="number"
                    step="0.01"
                    min={0}
                    value={numberInputValue(field.value)}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          {type === 'decimal' && (
            <FormField
              control={baseControl}
              name="config.decimals"
              render={({ field }) => (
                <FormItem>
                  <HintLabel
                    hint={t('customFields.form.configDecimalsHint')}
                    hintLabel={t('customFields.form.configDecimals')}
                  >
                    {t('customFields.form.configDecimals')}
                  </HintLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min={0}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? null : Number(event.target.value))
                      }
                    />
                  </FormControl>
                </FormItem>
              )}
            />
          )}
        </>
      )}

      {type === 'boolean' && (
        <FormField
          control={baseControl}
          name="config.display"
          render={({ field }) => (
            <FormItem>
              <HintLabel
                hint={t('customFields.form.configDisplayHint')}
                hintLabel={t('customFields.form.configDisplay')}
              >
                {t('customFields.form.configDisplay')}
              </HintLabel>
              <Select value={field.value || 'checkbox'} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="checkbox">{t('customFields.form.configDisplayCheckbox')}</SelectItem>
                  <SelectItem value="switch">{t('customFields.form.configDisplaySwitch')}</SelectItem>
                </SelectContent>
              </Select>
            </FormItem>
          )}
        />
      )}

      {type === 'enum' && (
        <FormField
          control={baseControl}
          name="config.display"
          render={({ field }) => (
            <FormItem>
              <HintLabel
                hint={t('customFields.form.configDisplayHint')}
                hintLabel={t('customFields.form.configDisplay')}
              >
                {t('customFields.form.configDisplay')}
              </HintLabel>
              <Select value={field.value || 'select'} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="select">{t('customFields.form.configDisplaySelect')}</SelectItem>
                  <SelectItem value="multiselect">{t('customFields.form.configDisplayMultiselect')}</SelectItem>
                  <SelectItem value="radio">{t('customFields.form.configDisplayRadio')}</SelectItem>
                  <SelectItem value="badge">{t('customFields.form.configDisplayBadge')}</SelectItem>
                </SelectContent>
              </Select>
            </FormItem>
          )}
        />
      )}
    </FormSection>
  )
}
