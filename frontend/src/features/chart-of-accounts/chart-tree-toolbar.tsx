import { useState } from 'react'
import { ChevronsDownUp, ChevronsUpDown, Search, Upload } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ChartExportMenu } from '@/features/chart-of-accounts/chart-export-menu'
import { ChartImportDialog } from '@/features/chart-of-accounts/chart-import-dialog'

interface ChartTreeToolbarProps {
  query: string
  onQueryChange: (query: string) => void
  onExpandAll: () => void
  onCollapseAll: () => void
  /** Expansion is derived from the matches while searching. */
  expansionDisabled: boolean
  canExport: boolean
  canImport: boolean
}

/** Search box, expand / collapse all, and the export / import entry points. */
export function ChartTreeToolbar({
  query,
  onQueryChange,
  onExpandAll,
  onCollapseAll,
  expansionDisabled,
  canExport,
  canImport,
}: ChartTreeToolbarProps) {
  const { t } = useTranslation()
  const [importOpen, setImportOpen] = useState(false)

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="relative min-w-0 flex-1 basis-48">
        <Search
          className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <Input
          type="search"
          value={query}
          onChange={(event) => onQueryChange(event.target.value)}
          placeholder={t('chartOfAccounts.search.placeholder')}
          aria-label={t('chartOfAccounts.search.label')}
          className="h-8 bg-card pl-8 text-sm"
        />
      </div>
      <Button variant="outline" size="sm" className="bg-card" onClick={onExpandAll} disabled={expansionDisabled}>
        <ChevronsUpDown aria-hidden="true" />
        {t('chartOfAccounts.expandAll')}
      </Button>
      <Button variant="outline" size="sm" className="bg-card" onClick={onCollapseAll} disabled={expansionDisabled}>
        <ChevronsDownUp aria-hidden="true" />
        {t('chartOfAccounts.collapseAll')}
      </Button>
      {canExport ? <ChartExportMenu /> : null}
      {canImport ? (
        <>
          <Button variant="outline" size="sm" className="bg-card" onClick={() => setImportOpen(true)}>
            <Upload aria-hidden="true" />
            {t('chartOfAccounts.import.label')}
          </Button>
          <ChartImportDialog open={importOpen} onOpenChange={setImportOpen} />
        </>
      ) : null}
    </div>
  )
}
