import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { AccountBalance } from '@/features/account-statements/types'

const SELECT_CLASS =
  'h-8 w-full rounded-md border border-field-border bg-card px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
const NO_COMPANY_KEY = 'none'

interface AccountSelectorProps {
  accounts: AccountBalance[]
  companyId: number | null
  accountId: number | null
  onCompanyChange: (companyId: number | null) => void
  onAccountChange: (accountId: number | null) => void
}

interface AccountGroup {
  key: string
  label: string | null
  accounts: AccountBalance[]
}

/** Company filter ("All") plus the account picker grouped by company, balance next to each account. */
export function AccountSelector({
  accounts,
  companyId,
  accountId,
  onCompanyChange,
  onAccountChange,
}: AccountSelectorProps) {
  const { t } = useTranslation()
  const companyFieldId = useId()
  const accountFieldId = useId()

  const companies = useMemo(() => {
    const byId = new Map<number, string>()
    accounts.forEach((account) => account.company && byId.set(account.company.id, account.company.name))
    return [...byId].map(([id, name]) => ({ id, name }))
  }, [accounts])

  const groups = useMemo<AccountGroup[]>(() => {
    const byKey = new Map<string, AccountGroup>()
    accounts
      .filter((account) => companyId === null || account.company?.id === companyId)
      .forEach((account) => {
        const key = account.company ? String(account.company.id) : NO_COMPANY_KEY
        const group = byKey.get(key) ?? { key, label: account.company?.name ?? null, accounts: [] }
        group.accounts.push(account)
        byKey.set(key, group)
      })
    return [...byKey.values()]
  }, [accounts, companyId])

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
      <div className="flex min-w-0 flex-col gap-1">
        <Label htmlFor={companyFieldId} className="text-xs text-muted-foreground">
          {t('accountStatements.company')}
        </Label>
        <select
          id={companyFieldId}
          className={SELECT_CLASS}
          value={companyId ?? ''}
          onChange={(event) => onCompanyChange(event.target.value === '' ? null : Number(event.target.value))}
        >
          <option value="">{t('accountStatements.allCompanies')}</option>
          {companies.map((company) => (
            <option key={company.id} value={company.id}>
              {company.name}
            </option>
          ))}
        </select>
      </div>
      <div className="flex min-w-0 flex-col gap-1">
        <Label htmlFor={accountFieldId} className="text-xs text-muted-foreground">
          {t('accountStatements.account')}
        </Label>
        <select
          id={accountFieldId}
          className={SELECT_CLASS}
          value={accountId ?? ''}
          onChange={(event) => onAccountChange(event.target.value === '' ? null : Number(event.target.value))}
        >
          <option value="">{t('accountStatements.accountPlaceholder')}</option>
          {groups.map((group) => (
            <optgroup key={group.key} label={group.label ?? t('accountStatements.noCompany')}>
              {group.accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {`${account.name} - ${formatEuro(account.balance)}`}
                </option>
              ))}
            </optgroup>
          ))}
        </select>
      </div>
    </div>
  )
}
