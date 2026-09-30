import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

interface TableFieldRowRemoveProps {
  index: number
  onRemove: () => void
}

export function TableFieldRowRemove({ index, onRemove }: TableFieldRowRemoveProps) {
  const { t } = useTranslation()

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-xs"
      aria-label={t('customFields.tableField.removeRow', { index: index + 1 })}
      onClick={onRemove}
    >
      <Trash2 className="size-3.5" aria-hidden="true" />
    </Button>
  )
}
