import { Percent, Wallet } from 'lucide-react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordTextEditor } from '@/components/record-form/record-field-editors'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { formatEuro } from '@/features/invoices/invoice-format'
import type {
  EnasarcoParameterFormValues,
  EnasarcoParameterSchemaOutput,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'

type NumericFieldName = 'year' | 'total_rate' | 'agent_share_percent' | 'single_firm_ceiling' | 'multi_firm_ceiling'

/** How a numeric field reads closed and which input it opens. */
interface NumericFieldSpec {
  format: (value: string) => string
  step: string
  max?: number
}

const YEAR: NumericFieldSpec = { format: (value) => value, step: '1' }
const PERCENT: NumericFieldSpec = { format: (value) => `${value}%`, step: '0.01', max: 100 }
const AMOUNT: NumericFieldSpec = { format: (value) => formatEuro(value), step: '0.01' }

interface RowProps {
  inline: InlineEdit
  canEdit: boolean
}

function useParameterForm() {
  return useFormContext<EnasarcoParameterFormValues, unknown, EnasarcoParameterSchemaOutput>()
}

/** A decimal kept as the string the server returns (`"17.00"`): the schema validates it, the payload converts it. */
function NumericRow({ name, spec, inline, canEdit }: RowProps & { name: NumericFieldName; spec: NumericFieldSpec }) {
  const { t } = useTranslation()
  const { control } = useParameterForm()
  const value = useWatch({ control, name })
  const label = t(`enasarcoParameters.form.${name}`)

  return (
    <RecordInlineField
      field={name}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={name} label={label} required>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                inputMode={spec.step === '1' ? 'numeric' : 'decimal'}
                step={spec.step}
                min={0}
                max={spec.max}
                disabled={disabled}
                readOnly={readOnly}
                {...field}
              />
            </FormControl>
          )}
        </MetaField>
      }
    >
      {value.trim() !== '' ? <span className="tabular-nums">{spec.format(value)}</span> : <DetailEmpty />}
    </RecordInlineField>
  )
}

function NotesRow({ inline, canEdit }: RowProps) {
  const { t } = useTranslation()
  const { control } = useParameterForm()
  const notes = useWatch({ control, name: 'notes' })

  return (
    <RecordInlineField
      field="notes"
      label={t('enasarcoParameters.form.notes')}
      inline={inline}
      canEdit={canEdit}
      editor={<RecordTextEditor control={control} name="notes" label={t('enasarcoParameters.form.notes')} multiline />}
    >
      {notes.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{notes}</span> : <DetailEmpty />}
    </RecordInlineField>
  )
}

/**
 * The parameters of one year, shared by the detail (each row saved alone,
 * spec 0264 D-5) and the create draft (kept until Salva).
 */
export function EnasarcoParameterSections({ inline, canEdit }: RowProps) {
  const { t } = useTranslation()
  const rowProps = { inline, canEdit }

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('enasarcoParameters.detail.rates')} icon={<Percent />}>
        <RecordFieldList>
          <NumericRow {...rowProps} name="year" spec={YEAR} />
          <NumericRow {...rowProps} name="total_rate" spec={PERCENT} />
          <NumericRow {...rowProps} name="agent_share_percent" spec={PERCENT} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('enasarcoParameters.detail.ceilings')} icon={<Wallet />}>
        <RecordFieldList>
          <NumericRow {...rowProps} name="single_firm_ceiling" spec={AMOUNT} />
          <NumericRow {...rowProps} name="multi_firm_ceiling" spec={AMOUNT} />
          <NotesRow {...rowProps} />
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
