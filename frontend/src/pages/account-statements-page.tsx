import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { AccountSelector } from '@/features/account-statements/account-selector'
import { AccountStatementPanel } from '@/features/account-statements/account-statement-panel'
import { UnassignedBox } from '@/features/account-statements/unassigned-box'
import type { AccountBalance } from '@/features/account-statements/types'
import { useBalances } from '@/features/account-statements/use-account-statements'

/** URL parameter holding the chosen account, so the page is linkable (account sheet links here). */
const ACCOUNT_PARAM = 'account'
const NO_ACCOUNTS: AccountBalance[] = []

function parseAccountId(raw: string | null): number | null {
  const id = Number(raw)
  return Number.isInteger(id) && id > 0 ? id : null
}

/** Account statement page (Accounting > Finance), gated by `treasury-movements.viewAny`. */
export default function AccountStatementsPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const [companyId, setCompanyId] = useState<number | null>(null)
  const accountId = parseAccountId(searchParams.get(ACCOUNT_PARAM))
  const { data: balances, isError } = useBalances(null)

  const selectAccount = (id: number | null) => {
    const next = new URLSearchParams(searchParams)
    if (id === null) {
      next.delete(ACCOUNT_PARAM)
    } else {
      next.set(ACCOUNT_PARAM, String(id))
    }
    setSearchParams(next)
  }

  return (
    <Can
      permission="treasury-movements.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('accountStatements.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        {isError ? (
          <p role="alert" className="text-sm text-destructive">{t('accountStatements.loadError')}</p>
        ) : null}
        <AccountSelector
          accounts={balances?.accounts ?? NO_ACCOUNTS}
          companyId={companyId}
          accountId={accountId}
          onCompanyChange={setCompanyId}
          onAccountChange={selectAccount}
        />
        <UnassignedBox companyId={companyId} />
        {accountId === null ? (
          <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">
            {t('accountStatements.emptyState')}
          </p>
        ) : (
          <AccountStatementPanel key={accountId} accountId={accountId} />
        )}
      </div>
    </Can>
  )
}
