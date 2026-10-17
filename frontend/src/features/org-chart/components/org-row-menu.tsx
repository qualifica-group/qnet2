import { ArrowRightLeft, MoreHorizontal } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface OrgRowMenuProps {
  name: string
  onMove: () => void
}

/** Per-row actions menu (spec 0273 D-5): the keyboard / touch path to a move. */
export function OrgRowMenu({ name, onMove }: OrgRowMenuProps) {
  const { t } = useTranslation()
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="secondary" size="icon-xs" aria-label={t('orgChart.move.actions', { name })}>
          <MoreHorizontal aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onSelect={onMove}>
          <ArrowRightLeft aria-hidden="true" className="size-3.5" />
          {t('orgChart.move.action')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
