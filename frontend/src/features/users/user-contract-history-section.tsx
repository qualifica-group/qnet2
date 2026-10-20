import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { History, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAbilities } from '@/features/auth/use-abilities'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ContractConditionDialog } from '@/features/users/contract-condition-dialog'
import { classifyContractError, type ContractErrorKind } from '@/features/users/contract-history-errors'
import {
  CONTRACTS_FIELD,
  MANAGE_COMPENSATION_PERMISSION,
  type EmploymentContract,
} from '@/features/users/contract-history-types'
import { useDeleteEmploymentContract, useEmploymentContracts } from '@/features/users/use-contract-history'
import { formatDate } from '@/lib/formatting/date-display'

/** i18n key (under `users.detail.employment.contractHistory`) telling why a delete was refused. */
const DELETE_ERROR_KEYS: Record<ContractErrorKind, string> = {
  conflict: 'notLast',
  forbidden: 'forbidden',
  validation: 'genericError',
  unknown: 'genericError',
}

function ContractRow({
  contract,
  onDelete,
}: {
  contract: EmploymentContract
  /** Present only on the latest condition, for an actor allowed to write. */
  onDelete?: () => void
}) {
  const { t } = useTranslation()
  const hasSalary = contract.salary_amount != null && contract.salary_period != null
  const details = [
    contract.relationship_type ? t(`enums.relationship_type.${contract.relationship_type}`) : null,
    contract.company?.label ?? null,
    contract.collective_agreement_level?.label ?? null,
    hasSalary ? `${contract.salary_amount} / ${t(`users.detail.employment.contractHistory.periods.${contract.salary_period}`)}` : null,
  ].filter((part): part is string => part !== null)

  return (
    <li className="flex flex-wrap items-start gap-2 rounded-lg border bg-card p-3">
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex flex-wrap items-center gap-1.5 text-sm font-medium">
          {t('users.detail.employment.contractHistory.period', {
            from: formatDate(contract.starts_on),
            to: contract.ends_on ? formatDate(contract.ends_on) : t('users.detail.employment.contractHistory.ongoing'),
          })}
          {contract.is_current ? <Badge variant="secondary">{t('users.detail.employment.contractHistory.current')}</Badge> : null}
        </span>
        <span className="text-xs text-muted-foreground">{details.join(' · ')}</span>
        {contract.change_reason ? (
          <span className="text-xs text-muted-foreground">
            {t('users.detail.employment.contractHistory.reason')}: {contract.change_reason}
          </span>
        ) : null}
      </div>
      {onDelete ? (
        <Button type="button" variant="ghost" size="icon-sm" aria-label={t('users.detail.employment.contractHistory.delete')} onClick={onDelete}>
          <Trash2 aria-hidden="true" />
        </Button>
      ) : null}
    </li>
  )
}

/**
 * "Storico contrattuale" (spec 0272 D-1): the person's conditions, newest
 * first. Append-only: "Nuova condizione" adds one, only the LAST can be removed
 * (to correct it). Actions need the `employment.contracts` field editable
 * (spec 0283: `users.update` and the role's field matrix); the salary needs
 * `users.manageCompensation` (the server omits it otherwise).
 */
export function UserContractHistorySection({ userId }: { userId: number }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { can } = useAbilities()
  const { field } = useResourcePermissions()
  const [dialogOpen, setDialogOpen] = useState(false)
  const contracts = useEmploymentContracts(userId)
  const remove = useDeleteEmploymentContract(userId)
  const canWrite = field(CONTRACTS_FIELD).editable
  const items = contracts.data ?? []

  const handleDelete = async (contract: EmploymentContract) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('users.detail.employment.contractHistory.delete'),
      description: t('users.detail.employment.contractHistory.deleteConfirm', { date: formatDate(contract.starts_on) }),
      confirmLabel: t('users.detail.employment.contractHistory.delete'),
    })
    if (!confirmed) {
      return
    }
    try {
      await remove.mutateAsync(contract.id)
      toast.success(t('users.detail.employment.contractHistory.deleted'))
    } catch (error) {
      toast.error(t(`users.detail.employment.contractHistory.${DELETE_ERROR_KEYS[classifyContractError(error).kind]}`))
    }
  }

  let body
  if (contracts.isLoading) {
    body = <Skeleton className="h-16 w-full" />
  } else if (contracts.isError) {
    body = (
      <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
        {t('users.detail.employment.contractHistory.loadError')}
        <Button type="button" variant="outline" size="sm" onClick={() => void contracts.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  } else if (items.length === 0) {
    body = <DetailEmpty />
  } else {
    body = (
      <ul className="flex flex-col gap-2">
        {items.map((contract, index) => (
          <ContractRow
            key={contract.id}
            contract={contract}
            onDelete={canWrite && index === 0 ? () => void handleDelete(contract) : undefined}
          />
        ))}
      </ul>
    )
  }

  return (
    <RecordSection
      title={t('users.form.sections.contractHistory.title')}
      icon={<History />}
      full
      action={
        canWrite ? (
          <Button type="button" variant="secondary" size="sm" onClick={() => setDialogOpen(true)}>
            <Plus aria-hidden="true" />
            {t('users.detail.employment.contractHistory.add')}
          </Button>
        ) : null
      }
    >
      {body}
      {canWrite ? (
        <ContractConditionDialog
          userId={userId}
          open={dialogOpen}
          onOpenChange={setDialogOpen}
          canManageCompensation={can(MANAGE_COMPENSATION_PERMISSION)}
        />
      ) : null}
    </RecordSection>
  )
}

/** On create the person does not exist yet: the history fills in after the first save. */
export function UserContractHistoryCreateNote() {
  const { t } = useTranslation()
  return (
    <RecordSection title={t('users.form.sections.contractHistory.title')} icon={<History />}>
      <p className="text-sm text-muted-foreground">{t('users.detail.employment.contractHistory.createNote')}</p>
    </RecordSection>
  )
}
