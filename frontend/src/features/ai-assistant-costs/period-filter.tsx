import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { currentMonthPeriod, isValidPeriod, today } from '@/features/ai-assistant-costs/period'
import type { AiCostPeriod } from '@/features/ai-assistant-costs/types'

interface PeriodFilterProps {
  period: AiCostPeriod
  onChange: (period: AiCostPeriod) => void
}

/** From / to dates, with a way back to the current month. The server refuses a day after today. */
export function PeriodFilter({ period, onChange }: PeriodFilterProps) {
  const { t } = useTranslation('aiAssistantCosts')
  const fromId = useId()
  const toId = useId()

  return (
    <section aria-label={t('filters.title')} className="flex flex-col gap-2 rounded-lg border bg-surface p-3">
      <div className="grid grid-cols-2 items-end gap-3 sm:grid-cols-[repeat(2,minmax(0,12rem))_auto]">
        <div className="flex flex-col gap-1">
          <Label htmlFor={fromId} className="text-xs text-muted-foreground">{t('filters.from')}</Label>
          <Input
            id={fromId}
            type="date"
            max={today()}
            value={period.from}
            onChange={(event) => onChange({ ...period, from: event.target.value })}
          />
        </div>
        <div className="flex flex-col gap-1">
          <Label htmlFor={toId} className="text-xs text-muted-foreground">{t('filters.to')}</Label>
          <Input
            id={toId}
            type="date"
            max={today()}
            value={period.to}
            onChange={(event) => onChange({ ...period, to: event.target.value })}
          />
        </div>
        <Button type="button" variant="secondary" className="col-span-2 sm:col-span-1" onClick={() => onChange(currentMonthPeriod())}>
          {t('filters.currentMonth')}
        </Button>
      </div>
      {isValidPeriod(period) || period.from === '' || period.to === '' ? null : (
        <p role="alert" className="text-xs text-destructive">{t('filters.invalid')}</p>
      )}
    </section>
  )
}
