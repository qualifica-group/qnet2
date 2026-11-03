import { ChevronsDownUp, ChevronsUpDown, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

interface ChartTreeToolbarProps {
  query: string
  onQueryChange: (query: string) => void
  onExpandAll: () => void
  onCollapseAll: () => void
  /** Expansion is derived from the matches while searching. */
  expansionDisabled: boolean
}

/** Search box plus expand / collapse all. */
export function ChartTreeToolbar({
  query,
  onQueryChange,
  onExpandAll,
  onCollapseAll,
  expansionDisabled,
}: ChartTreeToolbarProps) {
  const { t } = useTranslation()

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
    </div>
  )
}
