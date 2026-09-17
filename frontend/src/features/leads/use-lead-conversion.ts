import { useCallback, useState } from 'react'
import axios from 'axios'
import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { conversionBlockers } from '@/features/leads/lead-conversion-error'
import { useConvertLeads } from '@/features/leads/use-convert-leads'

const UNPROCESSABLE_ENTITY = 422

interface UseLeadConversionOptions {
  /** Ran after the lead converted (caller's grid refresh / detail invalidation). */
  onConverted?: () => void
}

interface UseLeadConversionResult {
  /** Converts the lead straight away: Opportunity and its Offerta are derived server-side, no form. */
  startConversion: (leadId: number) => void
  /** The lead being converted, or null when idle. */
  convertingId: number | null
}

/**
 * Shared controller for both single-lead conversion triggers (the leads table
 * row action and the lead record card button). Spec 0140: the conversion no
 * longer opens the prefilled Opportunity form — it calls the same endpoint as
 * the bulk action with one id, so it behaves like the import (Opportunity plus
 * its empty/derived Offerta, all server-side).
 */
export function useLeadConversion({ onConverted }: UseLeadConversionOptions = {}): UseLeadConversionResult {
  const { t } = useTranslation()
  const { mutateAsync } = useConvertLeads()
  const [convertingId, setConvertingId] = useState<number | null>(null)

  const convert = useCallback(
    async (leadId: number) => {
      setConvertingId(leadId)
      try {
        await mutateAsync({ lead_ids: [leadId] })
        toast.success(t('leads.convert.success'))
        onConverted?.()
      } catch (error) {
        toast.error(conversionErrorMessage(error, t))
      } finally {
        setConvertingId(null)
      }
    },
    [mutateAsync, onConverted, t],
  )

  const startConversion = useCallback((leadId: number) => void convert(leadId), [convert])

  return { startConversion, convertingId }
}

/** A blocker reason, then the server's own 422 message (e.g. single-managed offer rows), then the generic fallback. */
function conversionErrorMessage(error: unknown, t: TFunction): string {
  const blockers = conversionBlockers(error)

  if (blockers && blockers.length > 0) {
    return t('leads.convert.blocked', { reason: t(`leads.bulkConvert.reasons.${blockers[0].reason}`) })
  }

  if (axios.isAxiosError(error) && error.response?.status === UNPROCESSABLE_ENTITY) {
    const message = error.response.data?.message

    if (typeof message === 'string' && message !== '') {
      return message
    }
  }

  return t('leads.convert.errors.generic')
}
