import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Switch } from '@/components/ui/switch'
import { formatFieldValue } from '@/features/record-merge/merge-format'
import { COMBINE_CHOICE, effectiveChoice, fieldDiffers, type FieldOverrides } from '@/features/record-merge/merge-selection'
import type { MergeChoice, MergeField, MergeRecord } from '@/features/record-merge/types'

interface MergeFieldsTableProps {
  records: MergeRecord[]
  fields: MergeField[]
  survivorId: number
  overrides: FieldOverrides
  showEqual: boolean
  onShowEqualChange: (value: boolean) => void
  onChoose: (key: string, choice: MergeChoice) => void
}

const LABEL_COLUMN = 'minmax(7rem,9rem)'
const RECORD_COLUMN = 'minmax(9rem,1fr)'

/** One radio per record for every field: rows are radio groups laid out on a shared grid. */
export function MergeFieldsTable({
  records,
  fields,
  survivorId,
  overrides,
  showEqual,
  onShowEqualChange,
  onChoose,
}: MergeFieldsTableProps) {
  const { t, i18n } = useTranslation()
  const visibleFields = showEqual ? fields : fields.filter(fieldDiffers)
  const hasCombine = visibleFields.some((field) => field.combinable)
  const columns = `${LABEL_COLUMN} repeat(${records.length}, ${RECORD_COLUMN})${hasCombine ? ' minmax(6rem,8rem)' : ''}`
  const minWidth = `${9 + records.length * 9 + (hasCombine ? 6 : 0)}rem`

  return (
    <section className="flex flex-col gap-2" aria-labelledby="merge-fields-title">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 id="merge-fields-title" className="text-sm font-semibold">
          {t('recordMerge.fields.title')}
        </h3>
        <div className="flex items-center gap-2">
          <Switch id="merge-show-equal" checked={showEqual} onCheckedChange={onShowEqualChange} />
          <Label htmlFor="merge-show-equal" className="text-xs">
            {t('recordMerge.fields.showEqual')}
          </Label>
        </div>
      </div>

      {visibleFields.length === 0 ? (
        <p className="rounded-lg border bg-card p-3 text-xs text-muted-foreground">{t('recordMerge.fields.noDifferences')}</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-card">
          <div style={{ minWidth }} className="divide-y text-xs">
            <div className="grid gap-2 bg-surface px-3 py-1.5 font-medium text-muted-foreground" style={{ gridTemplateColumns: columns }}>
              <span>{t('recordMerge.fields.field')}</span>
              {records.map((record) => (
                <span key={record.id} className="truncate" title={record.name}>
                  {record.name}
                </span>
              ))}
              {hasCombine ? <span /> : null}
            </div>
            {visibleFields.map((field) => (
              <FieldRow
                key={field.key}
                field={field}
                records={records}
                columns={columns}
                hasCombine={hasCombine}
                choice={effectiveChoice(field, survivorId, overrides)}
                onChoose={onChoose}
                language={i18n.language}
              />
            ))}
          </div>
        </div>
      )}
    </section>
  )
}

interface FieldRowProps {
  field: MergeField
  records: MergeRecord[]
  columns: string
  hasCombine: boolean
  choice: MergeChoice
  onChoose: (key: string, choice: MergeChoice) => void
  language: string
}

function FieldRow({ field, records, columns, hasCombine, choice, onChoose, language }: FieldRowProps) {
  const { t } = useTranslation()
  const label = t(`recordMerge.fields.labels.${field.key}`, { defaultValue: field.key })

  return (
    <RadioGroup
      aria-label={label}
      value={String(choice)}
      onValueChange={(value) => onChoose(field.key, value === COMBINE_CHOICE ? COMBINE_CHOICE : Number(value))}
      className="grid items-start gap-2 px-3 py-1.5"
      style={{ gridTemplateColumns: columns }}
    >
      <span className="font-medium">{label}</span>
      {records.map((record) => {
        const inputId = `merge-field-${field.key}-${record.id}`
        const text = formatFieldValue(field, field.values.find((entry) => entry.record_id === record.id), t, language)
        return (
          <div key={record.id} className="flex min-w-0 items-start gap-1.5">
            <RadioGroupItem id={inputId} value={String(record.id)} aria-labelledby={`${inputId}-label`} className="mt-0.5 size-3.5" />
            <Label id={`${inputId}-label`} htmlFor={inputId} className="min-w-0 text-xs font-normal">
              <span className="line-clamp-3 break-words whitespace-pre-wrap" title={text}>
                {text}
              </span>
            </Label>
          </div>
        )
      })}
      {hasCombine ? (
        field.combinable ? (
          <div className="flex items-start gap-1.5">
            <RadioGroupItem
              id={`merge-field-${field.key}-combine`}
              value={COMBINE_CHOICE}
              aria-labelledby={`merge-field-${field.key}-combine-label`}
              className="mt-0.5 size-3.5"
            />
            <Label id={`merge-field-${field.key}-combine-label`} htmlFor={`merge-field-${field.key}-combine`} className="text-xs font-normal">
              {t('recordMerge.fields.combine')}
            </Label>
          </div>
        ) : (
          <span />
        )
      ) : null}
    </RadioGroup>
  )
}
