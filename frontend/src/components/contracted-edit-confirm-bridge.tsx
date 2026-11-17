import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useConfirm } from '@/components/confirm-dialog-context'
import { setContractedEditConfirmer } from '@/api/contracted-edit'

/**
 * Connects the axios client to the app confirm dialog (spec 0404 E-D4): the
 * client asks, through this bridge, whether to resend an edit of an offer under
 * contract. Mount once under `ConfirmDialogProvider`; renders nothing.
 */
export function ContractedEditConfirmBridge() {
  const { t } = useTranslation()
  const confirm = useConfirm()

  useEffect(() => {
    setContractedEditConfirmer(() =>
      confirm({
        title: t('common.contractedQuote.confirmTitle'),
        description: t('common.contractedQuote.confirmDescription'),
        confirmLabel: t('common.contractedQuote.confirmAction'),
        tone: 'warning',
      }),
    )
    return () => setContractedEditConfirmer(null)
  }, [confirm, t])

  return null
}
