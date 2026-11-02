import { useState } from 'react'
import { useTranslation } from 'react-i18next'
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
import { balanceYearOptions } from '@/features/absences/balances/balance-format'
import { BalanceTile } from '@/features/absences/balances/balance-tile'
import { useAbsenceBalances } from '@/features/absences/balances/use-absence-balances'
import type { AbsenceBalanceRow } from '@/features/absences/types'
import { forbiddenMessage } from '@/lib/forbidden-message'

const MANAGE_ALL = 'absence-requests.manageAll'

interface AbsenceBalancesCardProps {
  /** Fixed person; omitted, the actor's own balances (with a person picker for who may manage all). */
  userId?: number
}

/** Yearly balance tiles per absence type, with allowance editing for who holds `absence-requests.manageAll`. */
export function AbsenceBalancesCard({ userId }: AbsenceBalancesCardProps) {
  const { t, i18n } = useTranslation()
  const { can } = useAbilities()
  const canManage = can(MANAGE_ALL)
  const currentYear = new Date().getFullYear()
  const [year, setYear] = useState(currentYear)
  const [pickedUserId, setPickedUserId] = useState<number | null>(null)
  const [editing, setEditing] = useState<AbsenceBalanceRow | null>(null)
  const selectLabels = useSelectLabels()

  const targetUserId = userId ?? pickedUserId ?? undefined
  const query = useAbsenceBalances({ year, user_id: targetUserId })

  if (forbiddenMessage(query.error, i18n) !== null) {
    return null
  }

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
          options={balanceYearOptions(currentYear)}
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
          <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {balances.balances.map((row) => (
              <BalanceTile
                key={row.absence_type.id}
                row={row}
                dailyMinutes={balances.daily_minutes}
                onEdit={canManage ? setEditing : undefined}
              />
            ))}
          </ul>
        ) : null}
      </CardContent>
      {canManage && balances ? (
        <AllowanceDialog
          row={editing}
          userId={balances.user.id}
          year={balances.year}
          dailyMinutes={balances.daily_minutes}
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
