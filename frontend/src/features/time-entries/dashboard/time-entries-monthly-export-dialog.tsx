/**
 * Monthly report export dialog (spec 0122 D-12, AC-039): user/month/year
 * picker, blocked with the q-net wording when the user is missing or the
 * period is in the future. Mirrors q-net's `Modal` monthly-report form
 * (structure/density, D-2), rebuilt on `components/ui/dialog`.
 */

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import type { UseTimeEntriesExportResult } from '@/features/time-entries/dashboard/use-time-entries-export'

const MONTH_KEYS = [
  'january', 'february', 'march', 'april', 'may', 'june',
  'july', 'august', 'september', 'october', 'november', 'december',
] as const

interface TimeEntriesMonthlyExportDialogProps {
  state: UseTimeEntriesExportResult
}

export function TimeEntriesMonthlyExportDialog({ state }: TimeEntriesMonthlyExportDialogProps) {
  const { t } = useTranslation()
  const userLabels: AsyncPaginatedSelectLabels = {
    placeholder: t('timeEntries.export.selectUser'),
    searchPlaceholder: t('timeEntries.filters.search'),
    empty: t('timeEntries.filters.noResults'),
    error: t('timeEntries.filters.loadError'),
    clearLabel: t('timeEntries.filters.clearSelection'),
    triggerLabel: t('timeEntries.export.user'),
    retry: t('timeEntries.page.retry'),
  }

  return (
    <Dialog open={state.monthlyDialogOpen} onOpenChange={(open) => (open ? undefined : state.closeMonthlyDialog())}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{t('timeEntries.export.monthlyDialogTitle')}</DialogTitle>
          <DialogDescription>{t('timeEntries.export.monthlyDialogDescription')}</DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2 sm:col-span-2">
            <Label>{t('timeEntries.export.user')}</Label>
            <AsyncPaginatedSelect
              resource="users"
              value={state.userId}
              onChange={state.setUserId}
              showAvatar
              labels={userLabels}
            />
          </div>
          <div className="space-y-2">
            <Label>{t('timeEntries.export.month')}</Label>
            <Select
              value={String(state.month)}
              onValueChange={(value) => state.setMonth(Number(value))}
            >
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {MONTH_KEYS.map((key, index) => (
                  <SelectItem key={key} value={String(index + 1)}>
                    {t(`timeEntries.export.months.${key}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="time-entries-monthly-export-year">{t('timeEntries.export.year')}</Label>
            <Input
              id="time-entries-monthly-export-year"
              type="number"
              min={2000}
              value={state.year}
              onChange={(event) => state.setYear(Number(event.target.value))}
            />
          </div>
        </div>

        {state.missingUser ? (
          <p className="text-sm text-destructive" role="alert">
            {t('timeEntries.export.userRequired')}
          </p>
        ) : null}
        {state.isFuturePeriod ? (
          <p className="text-sm text-destructive" role="alert">
            {t('timeEntries.export.futurePeriod')}
          </p>
        ) : null}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={state.closeMonthlyDialog}>
            {t('timeEntries.form.cancel')}
          </Button>
          <Button
            type="button"
            disabled={!state.canSubmitMonthly || state.isExportingMonthly}
            onClick={state.submitMonthly}
          >
            {state.isExportingMonthly ? t('timeEntries.export.submitting') : t('timeEntries.export.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
