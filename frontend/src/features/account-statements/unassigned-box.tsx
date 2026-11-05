import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatEuro } from '@/features/invoices/invoice-format'
import { useUnassigned } from '@/features/account-statements/use-account-statements'

/** "To verify" box: collections/payments with no account, excluded from every balance (D-4). */
export function UnassignedBox({ companyId }: { companyId: number | null }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const { data } = useUnassigned(companyId)

  if (!data) return null

  return (
    <section
      className="flex flex-col gap-2 rounded-lg border bg-surface p-3"
      aria-label={t('accountStatements.unassigned.title')}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-sm font-semibold">{t('accountStatements.unassigned.title')}</h2>
          <p className="text-xs text-muted-foreground">
            {t('accountStatements.unassigned.count', { count: data.count })}
          </p>
        </div>
        {data.count > 0 ? (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            aria-expanded={open}
            onClick={() => setOpen((value) => !value)}
          >
            <ChevronDown className={open ? 'size-3.5 rotate-180' : 'size-3.5'} />
            {open ? t('accountStatements.unassigned.hide') : t('accountStatements.unassigned.show')}
          </Button>
        ) : null}
      </div>
      {open ? (
        <ul className="flex flex-col divide-y rounded-md border bg-card text-xs">
          {data.rows.map((row) => (
            <li
              key={`${row.source}:${row.installment_id}`}
              className="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-2 py-1.5"
            >
              <span className="font-medium">{t(`accountStatements.unassigned.${row.source}`)}</span>
              <span>{`${t(`accountStatements.documentKinds.${row.document.kind}`)} ${row.document.number ?? row.document.id}`}</span>
              <span className="min-w-0 flex-1 truncate text-muted-foreground">{row.registry?.name ?? ''}</span>
              <span className="tabular-nums">{row.date}</span>
              <span className="font-medium tabular-nums">{formatEuro(row.amount)}</span>
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}
