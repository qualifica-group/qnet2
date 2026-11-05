import { MoreHorizontal } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { RowActionKey } from '@/features/chart-of-accounts/tree-actions'

interface ChartTreeRowMenuProps {
  /** Accessible subject of the menu, e.g. "61.01 Consulenze". */
  label: string
  actions: RowActionKey[]
  onAction: (action: RowActionKey) => void
}

/** Row action menu: the only place the row's actions live, so a narrow screen never needs a toolbar per row. */
export function ChartTreeRowMenu({ label, actions, onAction }: ChartTreeRowMenuProps) {
  const { t } = useTranslation()

  if (actions.length === 0) {
    return <span className="size-6 shrink-0" aria-hidden="true" />
  }

  const hasDelete = actions.includes('delete')
  const regular = actions.filter((action) => action !== 'delete')

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon-xs" className="shrink-0" aria-label={t('chartOfAccounts.rowActions', { label })}>
          <MoreHorizontal className="size-3.5" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {regular.map((action) => (
          <DropdownMenuItem key={action} onSelect={() => onAction(action)}>
            {t(`chartOfAccounts.actions.${action}`)}
          </DropdownMenuItem>
        ))}
        {hasDelete && regular.length > 0 ? <DropdownMenuSeparator /> : null}
        {hasDelete ? (
          <DropdownMenuItem variant="destructive" onSelect={() => onAction('delete')}>
            {t('chartOfAccounts.actions.delete')}
          </DropdownMenuItem>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
