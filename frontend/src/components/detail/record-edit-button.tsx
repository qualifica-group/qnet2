import { useTranslation } from 'react-i18next'
import { Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface RecordEditButtonProps {
  onClick: () => void
}

/**
 * The single Edit affordance of a record detail, pinned in the identity
 * header's `actions` slot (Opportunita' reference). The caller renders it only
 * when `onEdit` is wired AND the record's `permissions.resource.update` allows
 * it; the backend re-authorizes regardless.
 */
export function RecordEditButton({ onClick }: RecordEditButtonProps) {
  const { t } = useTranslation()

  return (
    <Button size="sm" onClick={onClick}>
      <Pencil aria-hidden="true" />
      {t('common.edit')}
    </Button>
  )
}
