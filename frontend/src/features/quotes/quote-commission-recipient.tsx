import { useTranslation } from 'react-i18next'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import { RecordLink } from '@/components/detail/record-link'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import { useViewableDomain } from '@/features/modules/use-viewable-domain'
import { TruncatedLabel } from '@/features/quotes/quote-truncated-label'
import type { QuoteCommissionRecipientType, QuoteRelationRef } from '@/features/quotes/types'

/** The module a non-user recipient opens in: a Referente or an Anagrafica. */
const RECORD_DOMAINS: Record<Exclude<QuoteCommissionRecipientType, 'user'>, string> = {
  referent: 'referents',
  registry: 'registries',
}

const NAME_CLASS = 'truncate font-medium text-foreground'

/**
 * A referent or registry recipient: the name opens its record (`RecordLink`, a
 * modal leading to the dedicated page) and hovering it shows a card with the
 * type and the same link. Not viewable by the actor: the plain name.
 */
function RecordRecipient({ recipient, type }: { recipient: QuoteRelationRef; type: Exclude<QuoteCommissionRecipientType, 'user'> }) {
  const { t } = useTranslation()
  const domain = RECORD_DOMAINS[type]
  const viewable = useViewableDomain(domain) !== null

  if (!viewable) {
    return <TruncatedLabel label={recipient.name} className={NAME_CLASS} />
  }

  return (
    <HoverCard>
      <HoverCardTrigger asChild>
        <span className="block min-w-0">
          <RecordLink domain={domain} id={recipient.id} className="max-w-full font-medium">
            {recipient.name}
          </RecordLink>
        </span>
      </HoverCardTrigger>
      <HoverCardContent align="start" className="flex w-auto max-w-72 min-w-56 flex-col gap-1 p-2 text-xs">
        <span className="text-muted-foreground">{t(`commissionConfigurations.options.recipient_type.${type}`)}</span>
        <RecordLink domain={domain} id={recipient.id} className="font-medium">
          {recipient.name}
        </RecordLink>
      </HoverCardContent>
    </HoverCard>
  )
}

interface QuoteCommissionRecipientProps {
  recipient: QuoteRelationRef | null | undefined
  type: QuoteCommissionRecipientType
}

/**
 * The recipient of a commission in "Dettaglio provvigioni" (user directive
 * 2026-10-23): hovering a user shows the profile card (`UserProfileHoverCard`),
 * a referent or registry the card with the link to its record.
 */
export function QuoteCommissionRecipient({ recipient, type }: QuoteCommissionRecipientProps) {
  if (!recipient) {
    return <span className="text-muted-foreground">—</span>
  }

  if (type === 'user') {
    return (
      <UserProfileHoverCard user={recipient} triggerClassName="max-w-full rounded-sm">
        <span className={NAME_CLASS}>{recipient.name}</span>
      </UserProfileHoverCard>
    )
  }

  return <RecordRecipient recipient={recipient} type={type} />
}
