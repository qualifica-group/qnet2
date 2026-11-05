import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { createTreasuryType, updateTreasuryType } from '@/features/chart-of-accounts/api'
import { buildTreasuryTypePayload, buildUpdateTreasuryTypePayload } from '@/features/chart-of-accounts/chart-account-payload'
import { buildTreasuryTypeSchema, type TreasuryTypeFormValues } from '@/features/chart-of-accounts/chart-account-schema'
import { EntrySelectField, EntrySwitchField, EntryTextField } from '@/features/chart-of-accounts/entry-fields'
import { EntrySheet } from '@/features/chart-of-accounts/entry-sheet'
import type { SubAccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import { accountOptions } from '@/features/chart-of-accounts/tree-model'
import type { ChartGroup } from '@/features/chart-of-accounts/types'
import { useEntrySubmit } from '@/features/chart-of-accounts/use-entry-submit'

const FORM_ID = 'treasury-sub-account-form'
const ERROR_FIELDS = ['code', 'name', 'description', 'chart_account_id', 'is_active'] as const

interface TreasurySubAccountSheetProps {
  state: SubAccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

function defaultsOf(state: SubAccountSheetState): Partial<TreasuryTypeFormValues> {
  if (state.type === 'sub-create') {
    return { code: '', name: '', description: null, is_active: true, chart_account_id: state.account.id }
  }
  const { code, name, description, is_active } = state.sub
  return { code: code ?? '', name, description, is_active, chart_account_id: state.account?.id }
}

/** Create / edit form of a financial sub-account (spec 0360 D-3). */
export function TreasurySubAccountSheet({ state, groups, onClose }: TreasurySubAccountSheetProps) {
  const { t } = useTranslation()
  const isEdit = state.type === 'sub-edit'
  const schema = useMemo(() => buildTreasuryTypeSchema(t), [t])
  const form = useForm<TreasuryTypeFormValues>({ resolver: zodResolver(schema), defaultValues: defaultsOf(state) })

  const save = (values: TreasuryTypeFormValues) => {
    if (state.type === 'sub-edit') {
      const original = { ...state.sub, code: state.sub.code ?? '', chart_account_id: state.account?.id }
      return updateTreasuryType(state.sub.id, buildUpdateTreasuryTypePayload(values, original))
    }
    return createTreasuryType(buildTreasuryTypePayload(values))
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
      title={t(`treasuryTypes.sub.${isEdit ? 'editTitle' : 'createTitle'}`)}
      description={t('treasuryTypes.sub.description')}
      formId={FORM_ID}
      isPending={isPending}
      onClose={onClose}
    >
      <Form {...form}>
        <form id={FORM_ID} onSubmit={onSubmit} noValidate className="flex flex-col gap-3">
          <EntrySelectField<TreasuryTypeFormValues>
            name="chart_account_id"
            label={t('chartOfAccounts.form.parentAccount')}
            options={accountOptions(groups, 'financial')}
            parse={Number}
            required
          />
          <EntryTextField<TreasuryTypeFormValues> name="code" label={t('chartOfAccounts.form.code')} required />
          <EntryTextField<TreasuryTypeFormValues> name="name" label={t('chartOfAccounts.form.name')} required />
          <EntryTextField<TreasuryTypeFormValues> name="description" label={t('chartOfAccounts.form.description')} multiline />
          <EntrySwitchField<TreasuryTypeFormValues> name="is_active" label={t('chartOfAccounts.form.isActive')} />
        </form>
      </Form>
    </EntrySheet>
  )
}
