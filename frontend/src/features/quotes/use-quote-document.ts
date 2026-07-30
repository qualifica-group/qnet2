import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { generateQuoteDocument } from '@/features/quotes/quote-document-api'

/**
 * Owns the "Scarica preventivo" action's loading state and error mapping (spec 0070
 * AC-302..AC-304): a 403 shows the translated permission-denied message; a
 * 422 shows the BACKEND's own message — the real case is
 * `no_layout_available`, a business explanation the frontend should not
 * paraphrase, mirroring `DocumentLayoutsTable.runDelete`'s 422 handling. Any
 * other failure falls back to a generic message. `generatingId` (rather than
 * a plain boolean) lets both the detail button (one fixed quote) and the
 * table's row action (one row among many) share this single hook: each
 * caller checks `isGenerating(theirOwnId)`, and a second click on ANY row is
 * a no-op while one generation is in flight, never a double submit.
 */
export function useQuoteDocument() {
  const { t } = useTranslation()
  const [generatingId, setGeneratingId] = useState<number | null>(null)

  const generate = useCallback(
    async (quoteId: number, quoteCode: string) => {
      if (generatingId !== null) {
        return
      }
      setGeneratingId(quoteId)
      try {
        await generateQuoteDocument(quoteId, quoteCode)
        toast.success(t('quotes.detail.documentGenerated'))
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('quotes.detail.documentGenericError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('quotes.detail.documentForbidden'))
        } else if (status === 422) {
          toast.error(error.response?.data?.message ?? t('quotes.detail.documentGenericError'))
        } else {
          toast.error(t('quotes.detail.documentGenericError'))
        }
      } finally {
        setGeneratingId(null)
      }
    },
    [generatingId, t],
  )

  const isGenerating = useCallback((quoteId: number) => generatingId === quoteId, [generatingId])

  return { generate, isGenerating }
}
