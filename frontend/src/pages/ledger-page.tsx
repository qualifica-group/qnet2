import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Label } from '@/components/ui/label'
import { Can } from '@/features/auth/can'
import { LedgerPanel } from '@/features/ledger/ledger-panel'

/** URL parameter holding the chosen registry, so the page is linkable and survives a reload. */
const LEDGER_REGISTRY_PARAM = 'registry'

function parseRegistryId(raw: string | null): number | null {
  const id = Number(raw)
  return Number.isInteger(id) && id > 0 ? id : null
}

/**
 * Partitario page (Accounting): picks a registry (kept in `?registry=<id>`)
 * and mounts the shared ledger panel. Gated by `ledger.view`.
 */
export default function LedgerPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const registryId = parseRegistryId(searchParams.get(LEDGER_REGISTRY_PARAM))

  const selectRegistry = (id: number | null) => {
    const next = new URLSearchParams(searchParams)
    if (id === null) {
      next.delete(LEDGER_REGISTRY_PARAM)
    } else {
      next.set(LEDGER_REGISTRY_PARAM, String(id))
    }
    setSearchParams(next)
  }

  return (
    <Can permission="ledger.view" fallback={<p className="text-sm text-muted-foreground">{t('ledger.forbidden')}</p>}>
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('ledger.title')} />
        <div className="flex max-w-md flex-col gap-1">
          <Label className="text-xs text-muted-foreground">{t('ledger.page.registryLabel')}</Label>
          <AsyncPaginatedSelect
            resource="registries"
            value={registryId}
            onChange={selectRegistry}
            labels={{
              placeholder: t('ledger.page.registryPlaceholder'),
              searchPlaceholder: t('ledger.page.registrySearch'),
              empty: t('ledger.page.registryEmpty'),
              error: t('ledger.page.registryError'),
              clearLabel: t('ledger.page.registryClear'),
              triggerLabel: t('ledger.page.registryLabel'),
              retry: t('ledger.retry'),
            }}
          />
        </div>
        {registryId === null ? (
          <p className="rounded-lg border bg-surface p-4 text-sm text-muted-foreground">
            {t('ledger.page.emptyState')}
          </p>
        ) : (
          <LedgerPanel key={registryId} registryId={registryId} />
        )}
      </div>
    </Can>
  )
}
