import { useMemo } from 'react'
import { Building2, CalendarDays, FilePen, UserRound } from 'lucide-react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordTextEditor } from '@/components/record-form/record-field-editors'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { formatEuro } from '@/features/invoices/invoice-format'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import type { AdjustmentFormValues, AdjustmentSchemaOutput } from '@/features/commission-settlements/adjustment-schema'
import { ForSelectFormField } from '@/features/commission-settlements/for-select-form-field'
import type { AdjustmentMode } from '@/features/commission-settlements/types'

const NO_IDS: number[] = []

type PartyField = 'referent_id' | 'company_id'

function useAdjustmentForm() {
  return useFormContext<AdjustmentFormValues, unknown, AdjustmentSchemaOutput>()
}

/** The picked agent or company by name, through the for-select hydration. */
function usePickedName(resource: string, id: number | null): string | null {
  const ids = useMemo(() => (id === null ? NO_IDS : [id]), [id])
  const labels = useForSelectLabels({ resource, ids })
  return id === null ? null : (labels.get(id)?.label ?? null)
}

interface RowProps {
  inline: InlineEdit
}

const PARTY_RESOURCES: Record<PartyField, string> = {
  referent_id: REFERENTS_FOR_SELECT_RESOURCE,
  company_id: COMPANIES_FOR_SELECT_RESOURCE,
}

const PARTY_LABELS: Record<PartyField, { label: string; placeholder: string }> = {
  referent_id: { label: 'commissionSettlements.adjustment.referent', placeholder: 'commissionSettlements.adjustment.referentPlaceholder' },
  company_id: { label: 'commissionSettlements.adjustment.company', placeholder: 'commissionSettlements.adjustment.companyPlaceholder' },
}

/** Agent or company picked by the user (standalone adjustment). */
function PartyRow({ inline, name }: RowProps & { name: PartyField }) {
  const { t } = useTranslation()
  const { control } = useAdjustmentForm()
  const id = useWatch({ control, name })
  const picked = usePickedName(PARTY_RESOURCES[name], id)
  const label = t(PARTY_LABELS[name].label)

  return (
    <RecordInlineField
      field={name}
      label={label}
      icon={name === 'referent_id' ? <UserRound /> : <Building2 />}
      inline={inline}
      editor={
        <ForSelectFormField
          control={control}
          name={name}
          resource={PARTY_RESOURCES[name]}
          label={label}
          placeholder={t(PARTY_LABELS[name].placeholder)}
          required
          hideLabel
          quickCreate
        />
      }
    >
      {picked ?? <DetailEmpty />}
    </RecordInlineField>
  )
}

/** The signed amount: its sign says whether it raises or lowers what is due to the agent. */
function AmountRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useAdjustmentForm()
  const amount = useWatch({ control, name: 'amount' })
  const label = t('commissionSettlements.adjustment.amount')

  return (
    <RecordInlineField
      field="amount"
      label={label}
      inline={inline}
      editor={
        <FormField
          control={control}
          name="amount"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="sr-only">
                {label}
              </FormLabel>
              <FormControl>
                <Input type="number" step="0.01" inputMode="decimal" className="h-8 text-sm" {...field} />
              </FormControl>
              <p className="text-xs text-muted-foreground">{t('commissionSettlements.adjustment.amountHint')}</p>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      }
    >
      {amount.trim() !== '' && Number.isFinite(Number(amount)) ? (
        <span className="tabular-nums">{formatEuro(Number(amount))}</span>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

function DateRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useAdjustmentForm()
  const accruedOn = useWatch({ control, name: 'accrued_on' })

  return (
    <RecordInlineField
      field="accrued_on"
      label={t('commissionSettlements.adjustment.accruedOn')}
      icon={<CalendarDays />}
      inline={inline}
      editor={
        <RecordTextEditor control={control} name="accrued_on" label={t('commissionSettlements.adjustment.accruedOn')} type="date" required />
      }
    >
      {accruedOn !== '' ? formatDate(accruedOn) : <DetailEmpty />}
    </RecordInlineField>
  )
}

function NotesRow({ inline }: RowProps) {
  const { t } = useTranslation()
  const { control } = useAdjustmentForm()
  const notes = useWatch({ control, name: 'notes' })

  return (
    <RecordInlineField
      field="notes"
      label={t('commissionSettlements.adjustment.notes')}
      inline={inline}
      editor={
        <RecordTextEditor control={control} name="notes" label={t('commissionSettlements.adjustment.notes')} multiline required />
      }
    >
      {notes.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{notes}</span> : <DetailEmpty />}
    </RecordInlineField>
  )
}

interface CommissionAdjustmentSectionsProps extends RowProps {
  mode: AdjustmentMode
}

/**
 * The adjustment as a record draft (spec 0265 D-2): who it is for, then the
 * correction itself. Inside a draft statement the agent and company are the
 * statement's, shown and not editable; an edit only changes the correction.
 */
export function CommissionAdjustmentSections({ inline, mode }: CommissionAdjustmentSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      {mode.type !== 'edit' ? (
        <RecordSection title={t('commissionSettlements.adjustment.partiesSection')} icon={<UserRound />}>
          <RecordFieldList>
            {mode.type === 'standalone' ? (
              <>
                <PartyRow inline={inline} name="referent_id" />
                <PartyRow inline={inline} name="company_id" />
              </>
            ) : (
              <>
                <RecordField label={t('commissionSettlements.adjustment.referent')}>{mode.referent.name}</RecordField>
                <RecordField label={t('commissionSettlements.adjustment.company')}>{mode.company.name}</RecordField>
              </>
            )}
          </RecordFieldList>
        </RecordSection>
      ) : null}
      <RecordSection title={t('commissionSettlements.adjustment.correctionSection')} icon={<FilePen />}>
        <RecordFieldList>
          <AmountRow inline={inline} />
          <DateRow inline={inline} />
          <NotesRow inline={inline} />
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
