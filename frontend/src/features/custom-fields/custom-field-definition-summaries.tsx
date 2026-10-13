import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { FieldDefinitionConfigBag } from '@/features/custom-fields/field-definition-form-values'

/**
 * The closed (read-only) face of the detail's block rows (spec 0231): what
 * the type-specific editors would show, as values. Each reads the form
 * values, which mirror the saved definition while no editor is open.
 */

interface SummaryProps {
  values: CustomFieldDefinitionFormValues
}

const CONFIG_LABEL_KEYS: Record<keyof FieldDefinitionConfigBag, string> = {
  minLength: 'configMinLength',
  maxLength: 'configMaxLength',
  regex: 'configRegex',
  transform: 'configTransform',
  rows: 'configRows',
  min: 'configMin',
  max: 'configMax',
  step: 'configStep',
  decimals: 'configDecimals',
  display: 'configDisplay',
}

const CONFIG_KEYS = Object.keys(CONFIG_LABEL_KEYS) as (keyof FieldDefinitionConfigBag)[]

/** `transform`/`display` values have their own labels (`configTransformUpper`, `configDisplaySwitch`). */
function configValueLabel(t: TFunction, key: keyof FieldDefinitionConfigBag, value: string | number): string {
  if (key !== 'transform' && key !== 'display') {
    return String(value)
  }
  const suffix = String(value).charAt(0).toUpperCase() + String(value).slice(1)
  return t(`customFields.form.${CONFIG_LABEL_KEYS[key]}${suffix}`, { defaultValue: String(value) })
}

export function PresentationSummary({ values }: SummaryProps) {
  const { t } = useTranslation()
  const rows = [
    ['description', values.description],
    ['helpText', values.help_text],
    ['placeholder', values.placeholder],
    ['icon', values.icon],
  ].filter(([, value]) => value !== '')

  if (rows.length === 0) {
    return <DetailEmpty />
  }
  return (
    <RecordFieldList>
      {rows.map(([key, value]) => (
        <RecordField key={key} label={t(`customFields.form.${key}`)}>
          {value}
        </RecordField>
      ))}
    </RecordFieldList>
  )
}

export function ConfigSummary({ values }: SummaryProps) {
  const { t } = useTranslation()
  const entries = CONFIG_KEYS.flatMap((key) => {
    const value = values.config[key]
    return value === null || value === '' ? [] : [[key, value] as const]
  })

  if (entries.length === 0) {
    return <DetailEmpty />
  }
  return (
    <RecordFieldList>
      {entries.map(([key, value]) => (
        <RecordField key={key} label={t(`customFields.form.${CONFIG_LABEL_KEYS[key]}`)}>
          {configValueLabel(t, key, value)}
        </RecordField>
      ))}
    </RecordFieldList>
  )
}

export function OptionsSummary({ values }: SummaryProps) {
  const { t } = useTranslation()

  if (values.options.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('customFields.form.optionsEmpty')}</p>
  }
  return (
    <ul className="flex flex-col gap-1.5">
      {values.options.map((option) => (
        <li key={option.value} className="flex items-center gap-2 text-sm">
          <Badge variant="outline" className="font-mono text-xs">
            {option.value}
          </Badge>
          <span className="text-foreground">{option.label}</span>
        </li>
      ))}
    </ul>
  )
}

export function RelationSummary({ values }: SummaryProps) {
  const { t } = useTranslation()
  const target = values.relation_target

  if (target.entity_type === '') {
    return <DetailEmpty />
  }
  return (
    <RecordFieldList>
      <RecordField label={t('customFields.form.relationEntityType')}>
        {t(`customFields.entities.${target.entity_type}`, { defaultValue: target.entity_type })}
      </RecordField>
      <RecordField label={t('customFields.form.relationCardinality')}>
        {target.cardinality === 'many'
          ? t('customFields.form.relationCardinalityMany')
          : t('customFields.form.relationCardinalityOne')}
      </RecordField>
      {target.for_select_resource !== '' ? (
        <RecordField label={t('customFields.form.relationForSelectResource')}>{target.for_select_resource}</RecordField>
      ) : null}
    </RecordFieldList>
  )
}

export function TableSummary({ values }: SummaryProps) {
  const { t } = useTranslation()
  const { columns, min_rows: minRows, max_rows: maxRows } = values.table

  if (columns.length === 0) {
    return <DetailEmpty />
  }
  return (
    <RecordFieldList>
      <RecordField label={t('customFields.tableEditor.title')}>
        <ul className="flex flex-col gap-1">
          {columns.map((column) => (
            <li key={column.key} className="flex flex-wrap items-center gap-2">
              <span>{column.label}</span>
              <Badge variant="outline" className="font-mono text-xs">
                {column.key}
              </Badge>
              <Badge variant="secondary">{t(`customFields.types.${column.type}`)}</Badge>
              {column.required ? <Badge variant="outline">{t('customFields.tableEditor.columnRequired')}</Badge> : null}
            </li>
          ))}
        </ul>
      </RecordField>
      {minRows !== null ? <RecordField label={t('customFields.tableEditor.minRows')}>{minRows}</RecordField> : null}
      {maxRows !== null ? <RecordField label={t('customFields.tableEditor.maxRows')}>{maxRows}</RecordField> : null}
    </RecordFieldList>
  )
}

const VALIDATION_FLAGS = [
  ['required', 'validationRequired'],
  ['unique', 'validationUnique'],
  ['email', 'validationEmail'],
  ['url', 'validationUrl'],
  ['exists', 'validationExists'],
  ['distinct', 'validationDistinct'],
] as const

export function ValidationSummary({ values }: SummaryProps) {
  const { t } = useTranslation()
  const { validation } = values
  const flags = VALIDATION_FLAGS.filter(([key]) => validation[key])
  const hasValue = validation.min !== null || validation.max !== null || validation.regex !== ''

  if (flags.length === 0 && !hasValue) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-col gap-2">
      {flags.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {flags.map(([key, labelKey]) => (
            <Badge key={key} variant="outline">
              {t(`customFields.form.${labelKey}`)}
            </Badge>
          ))}
        </div>
      ) : null}
      {hasValue ? (
        <RecordFieldList>
          {validation.min !== null ? (
            <RecordField label={t('customFields.form.validationMin')}>{validation.min}</RecordField>
          ) : null}
          {validation.max !== null ? (
            <RecordField label={t('customFields.form.validationMax')}>{validation.max}</RecordField>
          ) : null}
          {validation.regex !== '' ? (
            <RecordField label={t('customFields.form.validationRegex')}>
              <span className="font-mono">{validation.regex}</span>
            </RecordField>
          ) : null}
        </RecordFieldList>
      ) : null}
    </div>
  )
}
