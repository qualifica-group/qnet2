import { useTranslation } from 'react-i18next'
import { Globe, Mail, Phone, Printer, type LucideIcon } from 'lucide-react'
import {
  useReferentContacts,
  type ReferentContactType,
} from '@/features/referents/use-referent-contacts'

interface OpportunityContactRecapProps {
  /** The currently selected referent/commercial/reporter id, or null when unset. */
  referentId: number | null
}

/** Per-channel icon; falls back to the mail glyph for any unmapped type. */
const CONTACT_ICON: Record<ReferentContactType, LucideIcon> = {
  email: Mail,
  pec: Mail,
  phone: Phone,
  fax: Printer,
  website: Globe,
}

/**
 * Compact graphical recap of a selected referent's PRIMARY contacts (spec 0040
 * A-4), reused under the referent, commercial and reporter selects. Renders
 * nothing until a referent is chosen and its primary contacts arrive.
 * Values are plain text (no href), so no URL-scheme sanitization is needed.
 */
export function OpportunityContactRecap({ referentId }: OpportunityContactRecapProps) {
  const { t } = useTranslation()
  const { data: contacts = [] } = useReferentContacts(referentId)

  if (referentId === null || contacts.length === 0) {
    return null
  }

  return (
    <ul
      aria-label={t('opportunities.form.contactsRecap')}
      className="flex flex-wrap gap-x-3 gap-y-1 px-0.5 text-xs text-muted-foreground"
    >
      {contacts.map((contact, index) => {
        const Icon = CONTACT_ICON[contact.type] ?? Mail
        return (
          <li
            // A referent has at most one primary contact per type; index is a
            // stable enough key for this small, read-only list.
            key={index}
            className="flex min-w-0 items-center gap-1"
            title={contact.label ?? undefined}
          >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            <span className="truncate">{contact.value}</span>
          </li>
        )
      })}
    </ul>
  )
}
