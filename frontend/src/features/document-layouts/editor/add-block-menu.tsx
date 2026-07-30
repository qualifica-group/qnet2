import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { BLOCK_TYPE_ICONS } from '@/features/document-layouts/editor/block-type-catalog'
import { BLOCK_TYPES } from '@/features/document-layouts/layout-config'
import type { BlockType } from '@/features/document-layouts/layout-config'

interface AddBlockMenuProps {
  onAdd: (type: BlockType) => void
  /** `image` needs at least one uploaded layout image to seed `attachment_id` (AC-126). */
  imageDisabled: boolean
  disabled: boolean
}

/** Dropdown listing all 7 block types (spec 0069 `config_schema`) — appends to the end of the zone (AC-120). */
export function AddBlockMenu({ onAdd, imageDisabled, disabled }: AddBlockMenuProps) {
  const { t } = useTranslation()

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="outline" size="xs" disabled={disabled}>
          <Plus aria-hidden="true" />
          {t('documentLayouts.editor.addBlock')}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {BLOCK_TYPES.map((type) => {
          const Icon = BLOCK_TYPE_ICONS[type]
          const itemDisabled = type === 'image' && imageDisabled
          return (
            <DropdownMenuItem key={type} disabled={itemDisabled} onSelect={() => onAdd(type)}>
              <Icon aria-hidden="true" />
              {t(`documentLayouts.editor.blockTypes.${type}`)}
            </DropdownMenuItem>
          )
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
