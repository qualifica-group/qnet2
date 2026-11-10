import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

interface CategoryFavoritesBulkActionsProps {
  canSelectAll: boolean
  canDeselectAll: boolean
  onSelectAll: () => void
  onDeselectAll: () => void
}

/** The strip menu's two bulk actions on the favourites. */
export function CategoryFavoritesBulkActions({
  canSelectAll,
  canDeselectAll,
  onSelectAll,
  onDeselectAll,
}: CategoryFavoritesBulkActionsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-1 border-t border-border px-2 py-2">
      <Button type="button" variant="secondary" size="xs" className="flex-1" disabled={!canSelectAll} onClick={onSelectAll}>
        {t('categoryTabs.selectAllFavorites')}
      </Button>
      <Button type="button" variant="secondary" size="xs" className="flex-1" disabled={!canDeselectAll} onClick={onDeselectAll}>
        {t('categoryTabs.deselectAllFavorites')}
      </Button>
    </div>
  )
}
