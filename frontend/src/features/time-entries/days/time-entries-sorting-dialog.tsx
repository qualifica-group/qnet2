/**
 * "Ordinamento" dialog of the days list (spec 0122 D-13): sort field +
 * direction, applied immediately on change (no separate "apply" step), with
 * a reset to the D-13 defaults (`date` / `asc`). Mirrors q-net's
 * `WorkActivitiesSortingDialog`.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowUpDown } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  TIME_ENTRIES_DEFAULT_SORT_BY,
  TIME_ENTRIES_DEFAULT_SORT_DIRECTION,
  TIME_ENTRY_SORT_OPTIONS,
} from '@/features/time-entries/time-entries-filters'
import type { TimeEntriesSortBy } from '@/features/time-entries/types'

interface TimeEntriesSortingDialogProps {
  sortBy: TimeEntriesSortBy
  sortDirection: 'asc' | 'desc'
  onChange: (sortBy: TimeEntriesSortBy, sortDirection: 'asc' | 'desc') => void
}

export function TimeEntriesSortingDialog({ sortBy, sortDirection, onChange }: TimeEntriesSortingDialogProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const isCustom =
    sortBy !== TIME_ENTRIES_DEFAULT_SORT_BY || sortDirection !== TIME_ENTRIES_DEFAULT_SORT_DIRECTION

  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogTrigger asChild>
        <Button className="gap-1.5" size="sm" type="button" variant="outline">
          <ArrowUpDown aria-hidden="true" className="size-3.5" />
          {t('timeEntries.sorting.title')}
          {isCustom ? (
            <Badge className="size-4 justify-center rounded-full p-0 text-[10px]" variant="secondary">
              1
            </Badge>
          ) : null}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('timeEntries.sorting.title')}</DialogTitle>
        </DialogHeader>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t('timeEntries.sorting.sortBy')}
            </span>
            <Select onValueChange={(value) => onChange(value as TimeEntriesSortBy, sortDirection)} value={sortBy}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TIME_ENTRY_SORT_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {t(`timeEntries.sorting.fields.${option.labelKey}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t('timeEntries.sorting.direction')}
            </span>
            <Select onValueChange={(value) => onChange(sortBy, value as 'asc' | 'desc')} value={sortDirection}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="asc">{t('timeEntries.sorting.ascending')}</SelectItem>
                <SelectItem value="desc">{t('timeEntries.sorting.descending')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="flex justify-end">
          <Button
            onClick={() => onChange(TIME_ENTRIES_DEFAULT_SORT_BY, TIME_ENTRIES_DEFAULT_SORT_DIRECTION)}
            type="button"
            variant="outline"
          >
            {t('timeEntries.sorting.reset')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
