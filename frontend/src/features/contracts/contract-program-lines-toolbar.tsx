import { Layers, ListPlus, Plus, Split } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

export interface ContractProgramLinesToolbarProps {
  selectedCount: number
  /** One entry per existing group, already formatted "G1 · title". */
  groupLabels: string[]
  atLimit: boolean
  /** Whether the bulk actions have lines to act on (the selection or, with none, every free line). */
  hasBulkTargets: boolean
  onNewGroup: () => void
  onAddToGroup: (index: number) => void
  onOnePerLine: () => void
  onByCategory: () => void
}

/** Quick actions above the lines table (spec 0215 D-3): all of them act on the current selection. */
export function ContractProgramLinesToolbar({
  selectedCount,
  groupLabels,
  atLimit,
  hasBulkTargets,
  onNewGroup,
  onAddToGroup,
  onOnePerLine,
  onByCategory,
}: ContractProgramLinesToolbarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Button type="button" variant="outline" size="xs" className="bg-card" disabled={atLimit} onClick={onNewGroup}>
        <Plus />
        {t('contracts.actions.programDialog.newGroup')}
      </Button>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            type="button"
            variant="outline"
            size="xs"
            className="bg-card"
            disabled={selectedCount === 0 || groupLabels.length === 0}
          >
            <ListPlus />
            {t('contracts.actions.programDialog.addToGroup')}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="max-w-xs">
          {groupLabels.map((label, index) => (
            <DropdownMenuItem key={label} className="text-xs" onSelect={() => onAddToGroup(index)}>
              <span className="truncate">{label}</span>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <Button
        type="button"
        variant="outline"
        size="xs"
        className="bg-card"
        disabled={atLimit || !hasBulkTargets}
        onClick={onOnePerLine}
      >
        <Split />
        {t('contracts.actions.programDialog.onePerLine')}
      </Button>
      <Button
        type="button"
        variant="outline"
        size="xs"
        className="bg-card"
        disabled={atLimit || !hasBulkTargets}
        onClick={onByCategory}
      >
        <Layers />
        {t('contracts.actions.programDialog.byCategory')}
      </Button>
    </div>
  )
}
