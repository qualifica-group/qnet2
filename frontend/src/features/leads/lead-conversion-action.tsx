import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Handshake } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { leadDetailQueryKey } from '@/features/leads/api'
import { useLeadConversion } from '@/features/leads/use-lead-conversion'
import type { LeadOpportunityRef } from '@/features/leads/types'

interface LeadConversionActionProps {
  leadId: number
  /** The opportunity already generated from this lead, or null/undefined when it has none yet. */
  opportunity: LeadOpportunityRef | null | undefined
}

/**
 * The lead -> opportunity CTA of the record card: "Crea opportunita'" while the
 * lead has none, "Vai all'opportunita'" once it does. Extracted out of
 * `LeadDetailPageActions` (which only ever appeared in the dedicated page's
 * header, so the Sheet never offered the conversion at all): living in the
 * identity band it is present on BOTH surfaces, the same way Opportunita' and
 * Utenti already own their Edit action.
 *
 * No fetch of its own: the card already holds the lead, so the button reads the
 * relation it was handed. On save it invalidates THIS lead's detail query, so
 * the card refetches and the button flips to "Vai all'opportunita'" (the
 * behaviour the page actions had, kept identical).
 *
 * `secondary`, not `outline`: the identity band IS `bg-card`, which is exactly
 * what `outline` fills itself with — the button would carry the surface it sits
 * on and read as "missing" (frontend.md §9). Filled with its own token it stands
 * off the band, and off the primary Edit beside it.
 */
export function LeadConversionAction({ leadId, opportunity }: LeadConversionActionProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const { startConversion, sheets } = useLeadConversion({
    onOpportunitySaved: () =>
      queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(leadId) }),
  })

  if (opportunity) {
    return (
      <Button variant="secondary" size="sm" asChild>
        <Link to={`/opportunities/${opportunity.id}`}>
          <Handshake aria-hidden="true" />
          {t('leads.detail.goToOpportunity')}
        </Link>
      </Button>
    )
  }

  return (
    <Can permission="opportunities.create">
      <Button variant="secondary" size="sm" onClick={() => startConversion(leadId)}>
        <Handshake aria-hidden="true" />
        {t('leads.detail.createOpportunity')}
      </Button>
      {sheets}
    </Can>
  )
}
