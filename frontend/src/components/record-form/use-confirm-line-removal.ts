import { useTranslation } from 'react-i18next'
import { useConfirm } from '@/components/confirm-dialog-context'

/** Asks before deleting a saved line, then runs `remove`. */
export function useConfirmLineRemoval(remove: (index: number) => void) {
  const { t } = useTranslation()
  const confirm = useConfirm()

  return async (index: number) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('common.inlineEdit.lines.removeConfirm.title'),
      description: t('common.inlineEdit.lines.removeConfirm.description', { n: index + 1 }),
      confirmLabel: t('common.inlineEdit.lines.removeConfirm.confirm'),
    })
    if (confirmed) {
      remove(index)
    }
  }
}
