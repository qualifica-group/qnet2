import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { forbiddenMessage } from '@/lib/forbidden-message'
import { downloadPurchaseOrderPdf } from '@/features/purchase-orders/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const UNPROCESSABLE_STATUS = 422

/**
 * "Print PDF" action: 403 and generic failures use translated messages, a 422
 * (no active layout for the module) shows the server's own message. A second
 * click is a no-op while a download runs.
 */
export function usePurchaseOrderPdf() {
  const { t, i18n } = useTranslation()
  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  const download = useCallback(
    async (orderId: number) => {
      if (downloadingId !== null) {
        return
      }
      setDownloadingId(orderId)
      try {
        await downloadPurchaseOrderPdf(orderId)
        toast.success(t('purchaseOrderActions.pdf.ready'))
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden) {
          toast.error(forbidden)
        } else if (status === UNPROCESSABLE_STATUS) {
          toast.error(serverMessage(error) ?? t('purchaseOrderActions.pdf.error'))
        } else {
          toast.error(t('purchaseOrderActions.pdf.error'))
        }
      } finally {
        setDownloadingId(null)
      }
    },
    [downloadingId, t, i18n],
  )

  return { download, isDownloading: downloadingId !== null }
}
