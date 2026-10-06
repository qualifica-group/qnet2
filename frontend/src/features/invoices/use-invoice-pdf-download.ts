import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { downloadInvoicePdf } from '@/features/invoices/invoice-pdf-api'

/**
 * "Scarica PDF" action: 403 and generic failures use translated messages, a 422
 * (no layout available, invalid layout) shows the server's own message.
 * `downloadingId` makes a second click a no-op while one download is running.
 */
export function useInvoicePdfDownload() {
  const { t } = useTranslation()
  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  const download = useCallback(
    async (invoiceId: number) => {
      if (downloadingId !== null) {
        return
      }
      setDownloadingId(invoiceId)
      try {
        await downloadInvoicePdf(invoiceId)
        toast.success(t('invoices.document.pdfReady'))
      } catch (error) {
        const status = axios.isAxiosError<ApiErrorResponse>(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('invoices.document.forbidden'))
        } else if (status === 422 && axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(error.response?.data?.message ?? t('invoices.document.pdfError'))
        } else {
          toast.error(t('invoices.document.pdfError'))
        }
      } finally {
        setDownloadingId(null)
      }
    },
    [downloadingId, t],
  )

  return { download, isDownloading: downloadingId !== null }
}
