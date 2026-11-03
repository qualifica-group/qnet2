import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { EntrySelectField, EntrySwitchField, EntryTextField } from '@/features/chart-of-accounts/entry-fields'
import { EntrySheet } from '@/features/chart-of-accounts/entry-sheet'
import type { SubAccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import { accountOptions } from '@/features/chart-of-accounts/tree-model'
import type { ChartGroup } from '@/features/chart-of-accounts/types'
import { useEntrySubmit } from '@/features/chart-of-accounts/use-entry-submit'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { createCostType, updateCostType } from '@/features/cost-types/api'
import { buildLedgerCreatePayload, buildLedgerUpdatePayload } from '@/features/cost-types/cost-type-form-payload'
import { buildCostTypeLedgerSchema, type CostTypeLedgerFormValues } from '@/features/cost-types/cost-type-schema'
import { COST_TYPE_NATURES, type CostTypeNature } from '@/features/cost-types/types'

const FORM_ID = 'cost-sub-account-form'
const ERROR_FIELDS = ['code', 'name', 'description', 'nature', 'color', 'chart_account_id', 'is_active'] as const
const DEFAULT_NATURE: CostTypeNature = 'direct'
const DEFAULT_COLOR = 'slate'

interface CostSubAccountSheetProps {
  state: SubAccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

function defaultsOf(state: SubAccountSheetState): Partial<CostTypeLedgerFormValues> {
  if (state.type === 'sub-create') {
    return {
      code: '',
      name: '',
      description: null,
      nature: DEFAULT_NATURE,
      color: DEFAULT_COLOR,
      is_active: true,
      chart_account_id: state.account.id,
    }
  }
  const { sub, account } = state
  return {
    code: sub.code ?? '',
    name: sub.name,
    description: sub.description,
    nature: sub.nature ?? DEFAULT_NATURE,
    color: sub.color ?? DEFAULT_COLOR,
    is_active: sub.is_active,
    chart_account_id: account?.id,
  }
}

function titleKeyOf(state: SubAccountSheetState): 'createTitle' | 'editTitle' | 'placeTitle' {
  if (state.type === 'sub-create') return 'createTitle'
  return state.account === null ? 'placeTitle' : 'editTitle'
}

/**
 * The cost type form reused as a "cost sub-account" (spec 0348): the page's
 * fields plus the parent account and a mandatory code. Also places an
 * unplaced cost type (edit with no account yet).
 */
export function CostSubAccountSheet({ state, groups, onClose }: CostSubAccountSheetProps) {
  const { t } = useTranslation()
  const isPlace = state.type === 'sub-edit' && state.account === null
  const schema = useMemo(() => buildCostTypeLedgerSchema(t), [t])
  const form = useForm<CostTypeLedgerFormValues>({ resolver: zodResolver(schema), defaultValues: defaultsOf(state) })

  const save = (values: CostTypeLedgerFormValues) => {
    if (state.type === 'sub-edit') {
      const { sub, account } = state
      const original = {
        name: sub.name,
        description: sub.description,
        nature: sub.nature ?? DEFAULT_NATURE,
        color: sub.color ?? DEFAULT_COLOR,
        is_active: sub.is_active,
        code: sub.code,
        chart_account_id: account?.id ?? null,
      }
      return updateCostType(sub.id, buildLedgerUpdatePayload(values, original))
    }
    return createCostType(buildLedgerCreatePayload(values))
  }

  const { onSubmit, isPending } = useEntrySubmit({
    form,
    errorFields: [...ERROR_FIELDS],
    save,
    successMessage: t(state.type === 'sub-edit' ? 'costTypes.form.updated' : 'costTypes.form.created'),
    onSaved: onClose,
  })

  return (
    <EntrySheet
      title={t(`chartOfAccounts.form.costSub.${titleKeyOf(state)}`)}
      description={t(`chartOfAccounts.form.costSub.${isPlace ? 'placeDescription' : 'description'}`)}
      formId={FORM_ID}
      isPending={isPending}
      onClose={onClose}
    >
      <Form {...form}>
        <form id={FORM_ID} onSubmit={onSubmit} noValidate className="flex flex-col gap-3">
          <EntrySelectField<CostTypeLedgerFormValues>
            name="chart_account_id"
            label={t('costTypes.form.chartAccount')}
            options={accountOptions(groups, 'cost')}
            parse={Number}
            required
          />
          <EntryTextField<CostTypeLedgerFormValues> name="code" label={t('costTypes.form.code')} required />
          <EntryTextField<CostTypeLedgerFormValues> name="name" label={t('costTypes.form.name')} required />
          <EntryTextField<CostTypeLedgerFormValues>
            name="description"
            label={t('costTypes.form.description')}
            multiline
          />
          <EntrySelectField<CostTypeLedgerFormValues>
            name="nature"
            label={t('costTypes.form.nature')}
            options={COST_TYPE_NATURES.map((nature) => ({ value: nature, label: t(`costTypes.nature.${nature}`) }))}
            parse={(value) => value}
            required
          />
          <FormField
            control={form.control}
            name="color"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('costTypes.form.color')}</FormLabel>
                <FormControl>
                  <ColorTokenPicker value={field.value} onChange={field.onChange} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <EntrySwitchField<CostTypeLedgerFormValues> name="is_active" label={t('costTypes.form.isActive')} />
        </form>
      </Form>
    </EntrySheet>
  )
}
