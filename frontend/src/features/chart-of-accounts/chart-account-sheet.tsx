import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { createChartAccount, updateChartAccount } from '@/features/chart-of-accounts/api'
import {
  buildCreateChartAccountPayload,
  buildUpdateChartAccountPayload,
} from '@/features/chart-of-accounts/chart-account-payload'
import { buildChartAccountSchema, type ChartAccountFormValues } from '@/features/chart-of-accounts/chart-account-schema'
import { EntrySheet } from '@/features/chart-of-accounts/entry-sheet'
import { EntrySelectField, EntrySwitchField, EntryTextField } from '@/features/chart-of-accounts/entry-fields'
import type { AccountSheetState } from '@/features/chart-of-accounts/sheet-state'
import type { ChartAccountKind, ChartAccountLevel, ChartGroup } from '@/features/chart-of-accounts/types'
import { useEntrySubmit } from '@/features/chart-of-accounts/use-entry-submit'

const FORM_ID = 'chart-account-form'
const ERROR_FIELDS = ['code', 'name', 'description', 'kind', 'parent_id', 'is_active'] as const
const KINDS: readonly ChartAccountKind[] = ['cost', 'revenue']

interface ChartAccountSheetProps {
  state: AccountSheetState
  groups: ChartGroup[]
  onClose: () => void
}

function levelOf(state: AccountSheetState): ChartAccountLevel {
  return state.type === 'group-create' || state.type === 'group-edit' ? 'group' : 'account'
}

function defaultsOf(state: AccountSheetState): ChartAccountFormValues {
  switch (state.type) {
    case 'group-create':
      return { code: '', name: '', description: null, is_active: true }
    case 'group-edit':
      return { ...pick(state.group), kind: state.group.kind }
    case 'account-create':
      return { code: '', name: '', description: null, is_active: true, parent_id: state.group.id }
    case 'account-edit':
      return { ...pick(state.account), parent_id: state.group.id }
  }
}

function pick(entry: { code: string; name: string; description: string | null; is_active: boolean }) {
  return { code: entry.code, name: entry.name, description: entry.description, is_active: entry.is_active }
}

/** Create / edit form of a mastro (level `group`) or a conto (level `account`), in a Sheet. */
export function ChartAccountSheet({ state, groups, onClose }: ChartAccountSheetProps) {
  const { t } = useTranslation()
  const level = levelOf(state)
  const isEdit = state.type === 'group-edit' || state.type === 'account-edit'
  const schema = useMemo(() => buildChartAccountSchema(t, level), [t, level])

  const form = useForm<ChartAccountFormValues>({ resolver: zodResolver(schema), defaultValues: defaultsOf(state) })

  const save = (values: ChartAccountFormValues) => {
    if (state.type === 'group-edit' || state.type === 'account-edit') {
      const original = state.type === 'group-edit' ? { ...state.group } : { ...state.account, parent_id: state.group.id }
      const id = state.type === 'group-edit' ? state.group.id : state.account.id
      return updateChartAccount(id, buildUpdateChartAccountPayload(level, values, original))
    }
    return createChartAccount(buildCreateChartAccountPayload(level, values))
  }

  const { onSubmit, isPending } = useEntrySubmit({
    form,
    errorFields: [...ERROR_FIELDS],
    save,
    successMessage: t(isEdit ? 'chartOfAccounts.form.updated' : 'chartOfAccounts.form.created'),
    onSaved: onClose,
  })

  // A conto can only live under a mastro of its own kind (spec 0348 D-9).
  const parentKind = state.type === 'account-create' || state.type === 'account-edit' ? state.group.kind : null
  const parentOptions = groups
    .filter((group) => group.kind === parentKind)
    .map((group) => ({ value: String(group.id), label: `${group.code} ${group.name}` }))
  const kindLocked = state.type === 'group-edit' && state.group.accounts.length > 0

  return (
    <EntrySheet
      title={t(`chartOfAccounts.form.${level}.${isEdit ? 'editTitle' : 'createTitle'}`)}
      description={t(`chartOfAccounts.form.${level}.description`)}
      formId={FORM_ID}
      isPending={isPending}
      onClose={onClose}
    >
      <Form {...form}>
        <form id={FORM_ID} onSubmit={onSubmit} noValidate className="flex flex-col gap-3">
          {level === 'group' ? (
            <EntrySelectField<ChartAccountFormValues>
              name="kind"
              label={t('chartOfAccounts.form.kind')}
              options={KINDS.map((kind) => ({ value: kind, label: t(`chartOfAccounts.kind.${kind}`) }))}
              parse={(value) => value}
              required
              disabled={kindLocked}
            />
          ) : (
            <EntrySelectField<ChartAccountFormValues>
              name="parent_id"
              label={t('chartOfAccounts.form.parentGroup')}
              options={parentOptions}
              parse={Number}
              required
            />
          )}
          <EntryTextField<ChartAccountFormValues> name="code" label={t('chartOfAccounts.form.code')} required />
          <EntryTextField<ChartAccountFormValues> name="name" label={t('chartOfAccounts.form.name')} required />
          <EntryTextField<ChartAccountFormValues> name="description" label={t('chartOfAccounts.form.description')} multiline />
          <EntrySwitchField<ChartAccountFormValues> name="is_active" label={t('chartOfAccounts.form.isActive')} />
        </form>
      </Form>
    </EntrySheet>
  )
}
