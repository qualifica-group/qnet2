import { useCallback, type RefObject } from 'react'
import { SkillLevelBadge } from '@/features/skills/skill-level-badge'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { RowActionHandler } from '@/features/table/row-actions'
import { USER_SKILLS_DOMAIN } from '@/features/user-skills/types'

/** The `level` column arrives as an integer: it is drawn as the level meter with its name. */
const RENDERERS: TableRendererMap = {
  level: (params) => <SkillLevelBadge level={params.value} />,
}

interface UserSkillsGridProps {
  tableRef: RefObject<TableViewHandle | null>
  /** Opens the detail of the clicked row: the abilities live in the detail. */
  onOpen: (id: number) => void
}

/** The "Persone" tab: the backend-driven grid of the person skills the actor may see. */
export function UserSkillsGrid({ tableRef, onOpen }: UserSkillsGridProps) {
  const handleAction: RowActionHandler = useCallback((_action, row) => onOpen(Number(row.id)), [onOpen])

  return (
    <TableView ref={tableRef} domain={USER_SKILLS_DOMAIN} renderers={RENDERERS} onAction={handleAction} inlineAllActions />
  )
}
