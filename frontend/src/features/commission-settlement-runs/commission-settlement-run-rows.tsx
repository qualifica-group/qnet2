import { useMemo } from 'react'
import { CalendarRange, Filter } from 'lucide-react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Checkbox } from '@/components/ui/checkbox'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { formatEuro } from '@/features/invoices/invoice-format'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import { ForSelectFormField, ForSelectMultiFormField } from '@/features/commission-settlements/for-select-form-field'
import type {
  CommissionSettlementRunFormValues,
  CommissionSettlementRunSchemaOutput,
} from '@/features/commission-settlement-runs/commission-settlement-run-schema'
import { COMMISSION_RECIPIENT_ROLES } from '@/features/commission-settlement-runs/types'

const NO_IDS: number[] = []

function useRunForm() {
  return useFormContext<CommissionSettlementRunFormValues, unknown, CommissionSettlementRunSchemaOutput>()
}

/** The picked ids by name, through the for-select hydration (names not loaded yet are left out). */
function usePickedNames(resource: string, ids: number[]): string[] {
  const labels = useForSelectLabels({ resource, ids: ids.length === 0 ? NO_IDS : ids })
  return useMemo(() => ids.flatMap((id) => labels.get(id)?.label ?? []), [ids, labels])
}

interface RowProps {
  inline: InlineEdit
}

/** One date input of the period, its own label visible inside the grouped row. */
function PeriodInput({ name, label }: { name: 'period_from' | 'period_to'; label: string }) {
  const { control } = useRunForm()
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required className="text-xs">
            {label}
          </FormLabel>
          <FormControl>
            <Input type="date" className="h-8 text-sm" {...field} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** Start and end are ONE row: the end may not precede the start. */
function PeriodRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control, formState } = useRunForm()
  const [from, to] = useWatch({ control, name: ['period_from', 'period_to'] })
  const endError = formState.errors.period_to?.message

  return (
    <RecordInlineField
      field="period_from"
      label={t('commissionSettlementRuns.rows.period')}
      inline={inline}
      editor={
        <div className="flex flex-col gap-2">
          <div className="grid gap-3 sm:grid-cols-2">
            <PeriodInput name="period_from" label={t('commissionSettlementRuns.form.periodFrom')} />
            <PeriodInput name="period_to" label={t('commissionSettlementRuns.form.periodTo')} />
          </div>
          <p className="text-xs text-muted-foreground">{t('commissionSettlementRuns.form.periodHint')}</p>
        </div>
      }
    >
      {t('commissionSettlementRuns.detail.periodRange', { from: formatDate(from), to: formatDate(to) })}
      {endError ? (
        <p role="alert" className="mt-1 text-xs font-medium text-destructive">
          {endError}
        </p>
      ) : null}
    </RecordInlineField>
  )
}

function CompanyRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useRunForm()
  const companyId = useWatch({ control, name: 'company_id' })
  const ids = useMemo(() => (companyId === null ? NO_IDS : [companyId]), [companyId])
  const [name] = usePickedNames(COMPANIES_FOR_SELECT_RESOURCE, ids)
  const label = t('commissionSettlementRuns.form.company')

  return (
    <RecordInlineField
      field="company_id"
      label={label}
      inline={inline}
      editor={
        <ForSelectFormField
          control={control}
          name="company_id"
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          label={label}
          placeholder={t('commissionSettlementRuns.form.companyPlaceholder')}
          hideLabel
        />
      }
    >
      {name ?? <span className="text-muted-foreground">{t('commissionSettlementRuns.form.companyPlaceholder')}</span>}
    </RecordInlineField>
  )
}

function ReferentsRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useRunForm()
  const referentIds = useWatch({ control, name: 'referent_ids' })
  const names = usePickedNames(REFERENTS_FOR_SELECT_RESOURCE, referentIds)
  const label = t('commissionSettlementRuns.form.referents')

  return (
    <RecordInlineField
      field="referent_ids"
      label={label}
      inline={inline}
      editor={
        <ForSelectMultiFormField
          control={control}
          name="referent_ids"
          resource={REFERENTS_FOR_SELECT_RESOURCE}
          label={label}
          placeholder={t('commissionSettlementRuns.form.referentsPlaceholder')}
          hideLabel
        />
      }
    >
      {names.length > 0 ? (
        names.join(', ')
      ) : (
        <span className="text-muted-foreground">{t('commissionSettlementRuns.form.referentsPlaceholder')}</span>
      )}
    </RecordInlineField>
  )
}

function RolesRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useRunForm()
  const roles = useWatch({ control, name: 'recipient_roles' })
  const label = t('commissionSettlementRuns.form.roles')

  return (
    <RecordInlineField
      field="recipient_roles"
      label={label}
      inline={inline}
      editor={
        <FormField
          control={control}
          name="recipient_roles"
          render={({ field }) => (
            <FormItem className="gap-1">
              <fieldset className="flex flex-wrap gap-x-4 gap-y-1.5">
                <legend className="sr-only">{label}</legend>
                {COMMISSION_RECIPIENT_ROLES.map((role) => (
                  <label key={role} className="flex items-center gap-1.5 text-xs">
                    <Checkbox
                      checked={field.value.includes(role)}
                      onCheckedChange={(checked) =>
                        field.onChange(checked ? [...field.value, role] : field.value.filter((r) => r !== role))
                      }
                    />
                    {t(`enums.commission_recipient_role.${role}`)}
                  </label>
                ))}
              </fieldset>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      }
    >
      {roles.length > 0 ? (
        roles.map((role) => t(`enums.commission_recipient_role.${role}`)).join(', ')
      ) : (
        <span className="text-muted-foreground">{t('commissionSettlementRuns.rows.allRoles')}</span>
      )}
    </RecordInlineField>
  )
}

function MinAmountRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useRunForm()
  const minAmount = useWatch({ control, name: 'min_amount' })
  const label = t('commissionSettlementRuns.form.minAmount')

  return (
    <RecordInlineField
      field="min_amount"
      label={label}
      inline={inline}
      editor={
        <FormField
          control={control}
          name="min_amount"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="sr-only">{label}</FormLabel>
              <FormControl>
                <Input type="number" step="0.01" min={0} inputMode="decimal" className="h-8 text-sm" {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      }
    >
      {minAmount.trim() !== '' && Number.isFinite(Number(minAmount)) ? (
        <span className="tabular-nums">{formatEuro(Number(minAmount))}</span>
      ) : (
        <span className="text-muted-foreground">{t('commissionSettlementRuns.rows.defaultThreshold')}</span>
      )}
    </RecordInlineField>
  )
}

/**
 * The new run as a record draft (spec 0265 D-4): the period, then the
 * optional filters, each row closed until opened. An empty filter means "all".
 */
export function CommissionSettlementRunSections({ inline }: RowProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('commissionSettlementRuns.rows.periodSection')} icon={<CalendarRange />}>
        <RecordFieldList>
          <PeriodRow inline={inline} />
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t('commissionSettlementRuns.rows.filtersSection')} icon={<Filter />}>
        <RecordFieldList>
          <CompanyRow inline={inline} />
          <ReferentsRow inline={inline} />
          <RolesRow inline={inline} />
          <MinAmountRow inline={inline} />
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
