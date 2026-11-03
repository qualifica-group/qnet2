import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { createRevenueType, updateRevenueType } from '@/features/chart-of-accounts/api'
import { buildRevenueTypePayload, buildUpdateRevenueTypePayload } from '@/features/chart-of-accounts/chart-account-payload'
import { buildRevenueTypeSchema, type RevenueTypeFormValues } from '@/features/chart-of-accounts/chart-account-schema'
import { EntrySelectField, EntrySwitchField, EntryTextField } from '@/features/chart-of-accounts/entry-fields'
import { EntrySheet } from '@/features/chart-of-accounts/entry-sheet'
import type { SubAccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import { accountOptions } from '@/features/chart-of-accounts/tree-model'
import type { ChartGroup } from '@/features/chart-of-accounts/types'
import { useEntrySubmit } from '@/features/chart-of-accounts/use-entry-submit'

const FORM_ID = 'revenue-sub-account-form'
const ERROR_FIELDS = ['code', 'name', 'description', 'chart_account_id', 'is_active'] as const

interface RevenueSubAccountSheetProps {
  state: SubAccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

function defaultsOf(state: SubAccountSheetState): Partial<RevenueTypeFormValues> {
  if (state.type === 'sub-create') {
    return { code: '', name: '', description: null, is_active: true, chart_account_id: state.account.id }
  }
  const { code, name, description, is_active } = state.sub
  return { code: code ?? '', name, description, is_active, chart_account_id: state.account?.id }
}

/** Create / edit form of a revenue sub-account (spec 0348 D-3). */
export function RevenueSubAccountSheet({ state, groups, onClose }: RevenueSubAccountSheetProps) {
  const { t } = useTranslation()
  const isEdit = state.type === 'sub-edit'
  const schema = useMemo(() => buildRevenueTypeSchema(t), [t])
  const form = useForm<RevenueTypeFormValues>({ resolver: zodResolver(schema), defaultValues: defaultsOf(state) })

  const save = (values: RevenueTypeFormValues) => {
    if (state.type === 'sub-edit') {
      const original = { ...state.sub, code: state.sub.code ?? '', chart_account_id: state.account?.id }
      return updateRevenueType(state.sub.id, buildUpdateRevenueTypePayload(values, original))
    }
    return createRevenueType(buildRevenueTypePayload(values))
  }

  const { onSubmit, isPending } = useEntrySubmit({
    form,
    errorFields: [...ERROR_FIELDS],
    save,
    successMessage: t(isEdit ? 'chartOfAccounts.form.updated' : 'chartOfAccounts.form.created'),
    onSaved: onClose,
  })

  return (
    <EntrySheet
      title={t(`chartOfAccounts.form.revenueSub.${isEdit ? 'editTitle' : 'createTitle'}`)}
      description={t('chartOfAccounts.form.revenueSub.description')}
      formId={FORM_ID}
      isPending={isPending}
      onClose={onClose}
    >
      <Form {...form}>
        <form id={FORM_ID} onSubmit={onSubmit} noValidate className="flex flex-col gap-3">
          <EntrySelectField<RevenueTypeFormValues>
            name="chart_account_id"
            label={t('chartOfAccounts.form.parentAccount')}
            options={accountOptions(groups, 'revenue')}
            parse={Number}
            required
          />
          <EntryTextField<RevenueTypeFormValues> name="code" label={t('chartOfAccounts.form.code')} required />
          <EntryTextField<RevenueTypeFormValues> name="name" label={t('chartOfAccounts.form.name')} required />
          <EntryTextField<RevenueTypeFormValues> name="description" label={t('chartOfAccounts.form.description')} multiline />
          <EntrySwitchField<RevenueTypeFormValues> name="is_active" label={t('chartOfAccounts.form.isActive')} />
        </form>
      </Form>
    </EntrySheet>
  )
}
