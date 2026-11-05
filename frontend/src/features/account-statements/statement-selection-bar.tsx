import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

interface StatementSelectionBarProps {
  count: number
  isPending: boolean
  onMark: (reconciled: boolean) => void
}

/** "Tick" / "Untick" actions on the selected statement rows (spec 0363). */
export function StatementSelectionBar({ count, isPending, onMark }: StatementSelectionBarProps) {
  const { t } = useTranslation()

  return (
    <div
      className="flex flex-wrap items-center gap-2 rounded-lg border bg-surface p-2"
      role="group"
      aria-label={t('accountStatements.reconciliation.selectionBar')}
    >
      <span className="text-xs text-muted-foreground" aria-live="polite">
        {t('accountStatements.reconciliation.selected', { count })}
      </span>
      <Button type="button" size="sm" disabled={count === 0 || isPending} onClick={() => onMark(true)}>
        {t('accountStatements.reconciliation.mark')}
      </Button>
      <Button type="button" size="sm" variant="secondary" disabled={count === 0 || isPending} onClick={() => onMark(false)}>
        {t('accountStatements.reconciliation.unmark')}
      </Button>
    </div>
  )
}
