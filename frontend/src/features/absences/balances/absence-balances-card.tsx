import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { useSelectLabels } from '@/features/absences/balances/use-select-labels'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { AllowanceDialog } from '@/features/absences/balances/allowance-dialog'
import { BalanceRow } from '@/features/absences/balances/balance-row'
import { useAbsenceBalances } from '@/features/absences/balances/use-absence-balances'
import type { AbsenceBalanceRow } from '@/features/absences/types'

const MANAGE_ALL = 'absence-requests.manageAll'
const FORBIDDEN_STATUS = 403
const YEAR_OFFSETS = [-1, 0, 1] as const

interface AbsenceBalancesCardProps {
  /** Fixed person; omitted, the actor's own balances (with a person picker for who may manage all). */
  userId?: number
}

/** Yearly balances per absence type, with allowance editing for who holds `absence-requests.manageAll`. */
export function AbsenceBalancesCard({ userId }: AbsenceBalancesCardProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canManage = can(MANAGE_ALL)
  const currentYear = new Date().getFullYear()
  const [year, setYear] = useState(currentYear)
  const [pickedUserId, setPickedUserId] = useState<number | null>(null)
  const [editing, setEditing] = useState<AbsenceBalanceRow | null>(null)
  const selectLabels = useSelectLabels()

  const targetUserId = userId ?? pickedUserId ?? undefined
  const query = useAbsenceBalances({ year, user_id: targetUserId })

  if (axios.isAxiosError(query.error) && query.error.response?.status === FORBIDDEN_STATUS) {
    return null
  }

  const yearOptions = YEAR_OFFSETS.map((offset) => ({
    value: String(currentYear + offset),
    label: String(currentYear + offset),
  }))
  const balances = query.data

  return (
    <Card>
      <CardHeader className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
        <CardTitle className="text-base font-semibold">
          {balances && targetUserId
            ? t('absences.balances.titleFor', { name: balances.user.name })
            : t('absences.balances.title')}
        </CardTitle>
        <SegmentedControl
          aria-label={t('absences.balances.year')}
          value={String(year)}
          options={yearOptions}
          onValueChange={(value) => setYear(Number(value))}
          className="sm:w-60"
        />
      </CardHeader>
      <CardContent className="flex flex-col gap-3 p-3 pt-0">
        {userId === undefined ? (
          <Can permission={MANAGE_ALL}>
            <AsyncPaginatedSelect
              resource={USERS_FOR_SELECT_RESOURCE}
              value={pickedUserId}
              onChange={setPickedUserId}
              labels={selectLabels}
              showAvatar
            />
          </Can>
        ) : null}
        {query.isPending ? <Skeleton className="h-24 w-full" /> : null}
        {query.isError ? (
          <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
            {t('absences.balances.error')}
            <Button type="button" size="sm" variant="secondary" onClick={() => void query.refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : null}
        {balances && balances.balances.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('absences.balances.empty')}</p>
        ) : null}
        {balances && balances.balances.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {balances.balances.map((row) => (
              <BalanceRow key={row.absence_type.id} row={row} onEdit={canManage ? setEditing : undefined} />
            ))}
          </ul>
        ) : null}
      </CardContent>
      {canManage && balances ? (
        <AllowanceDialog
          row={editing}
          userId={balances.user.id}
          year={balances.year}
          onOpenChange={(open) => {
            if (!open) {
              setEditing(null)
            }
          }}
        />
      ) : null}
    </Card>
  )
}
