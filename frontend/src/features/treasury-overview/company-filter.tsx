import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { useBalances } from '@/features/account-statements/use-account-statements'

const SELECT_CLASS =
  'h-8 w-full rounded-md border border-field-border bg-card px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'

interface CompanyFilterProps {
  value: number | null
  onChange: (companyId: number | null) => void
}

/** "All" or one company: the companies owning a financial account (same source as the account statement). */
export function CompanyFilter({ value, onChange }: CompanyFilterProps) {
  const { t } = useTranslation()
  const id = useId()
  const { data } = useBalances(null)

  const companies = useMemo(() => {
    const byId = new Map<number, string>()
    data?.accounts.forEach((account) => account.company && byId.set(account.company.id, account.company.name))
    return [...byId].map(([companyId, name]) => ({ id: companyId, name }))
  }, [data])

  return (
    <div className="flex w-full max-w-xs flex-col gap-1">
      <Label htmlFor={id} className="text-xs text-muted-foreground">
        {t('treasuryOverview.company')}
      </Label>
      <select
        id={id}
        className={SELECT_CLASS}
        value={value ?? ''}
        onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
      >
        <option value="">{t('treasuryOverview.allCompanies')}</option>
        {companies.map((company) => (
          <option key={company.id} value={company.id}>
            {company.name}
          </option>
        ))}
      </select>
    </div>
  )
}
