import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Form, FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnasarcoParameterForm } from '@/features/enasarco-parameters/use-enasarco-parameter-form'
import type {
  EnasarcoParameterFormValues,
  EnasarcoParameterSchemaOutput,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'
import type { EnasarcoParameterDetail, EnasarcoParameterFormMode } from '@/features/enasarco-parameters/types'

type ParameterControl = Control<EnasarcoParameterFormValues, unknown, EnasarcoParameterSchemaOutput>
type DecimalFieldName = 'total_rate' | 'agent_share_percent' | 'single_firm_ceiling' | 'multi_firm_ceiling'

const PERCENT_FIELDS: DecimalFieldName[] = ['total_rate', 'agent_share_percent']
const CEILING_FIELDS: DecimalFieldName[] = ['single_firm_ceiling', 'multi_firm_ceiling']

function DecimalField({ control, name, percent }: { control: ParameterControl; name: DecimalFieldName; percent: boolean }) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name={name} metaKey={name} label={t(`enasarcoParameters.form.${name}`)} required>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            inputMode="decimal"
            step="0.01"
            min={0}
            max={percent ? 100 : undefined}
            disabled={disabled}
            readOnly={readOnly}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface EnasarcoParameterFormBodyProps {
  mode: EnasarcoParameterFormMode
  onSuccess: (parameter: EnasarcoParameterDetail) => void
  onCancel: () => void
}

/**
 * The ENASARCO parameters create/edit form UI. Every field is wrapped in
 * `MetaField`; all non-render logic lives in `useEnasarcoParameterForm`.
 */
export function EnasarcoParameterFormBody({ mode, onSuccess, onCancel }: EnasarcoParameterFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useEnasarcoParameterForm({ mode, onSuccess })
  const control = form.control

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <MetaField control={control} name="year" metaKey="year" label={t('enasarcoParameters.form.year')} required>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input type="number" inputMode="numeric" step="1" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>
          <div className="grid gap-3 sm:grid-cols-2">
            {PERCENT_FIELDS.map((name) => (
              <DecimalField key={name} control={control} name={name} percent />
            ))}
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {CEILING_FIELDS.map((name) => (
              <DecimalField key={name} control={control} name={name} percent={false} />
            ))}
          </div>
          <MetaField control={control} name="notes" metaKey="notes" label={t('enasarcoParameters.form.notes')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Textarea disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('enasarcoParameters.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('enasarcoParameters.form.saving') : t('enasarcoParameters.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
