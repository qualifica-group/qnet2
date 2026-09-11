import { createElement } from 'react'
import { cn } from '@/lib/utils'
import { enumLabelOf } from '@/features/config/enum-label'
import { resolveEnumIcon } from '@/features/table/enum-icon-map'
import type { PrimaryContact } from '@/features/table/types'

/**
 * A record's PRIMARY contacts rendered as ACTIONABLE chips: the value plus a
 * `tel:`/`mailto:`/`https:` href, so a card can be called or mailed from the
 * record itself. The three read-only renderings that already exist
 * (`ContactsCell`'s tooltip, `registry-detail.tsx`'s person lines) only ever
 * show the value — this is the first one that acts on it, which is why it lives
 * in the shared detail kit rather than inside a feature.
 */

/** Schemes allowed on a generated href (frontend.md §10: allow-list, never the raw value). */
const ALLOWED_SCHEMES = ['mailto:', 'tel:', 'http:', 'https:']

/** Contact type -> the URI scheme its value is dialled/opened with. `website` is resolved apart (it carries its own scheme). */
const CONTACT_SCHEME: Record<string, string> = {
  email: 'mailto:',
  pec: 'mailto:',
  phone: 'tel:',
  mobile: 'tel:',
  fax: 'tel:',
}

/**
 * Per-contact-type icon tint, the SAME palette `ContactsCell` uses so a contact
 * reads identically in a grid cell and on a record card. Decorative only: the
 * accessible name always carries the type.
 */
const CONTACT_ICON_TINT: Record<string, string> = {
  mail: 'text-blue-600 dark:text-blue-300',
  phone: 'text-emerald-600 dark:text-emerald-300',
  smartphone: 'text-emerald-600 dark:text-emerald-300',
  printer: 'text-slate-500 dark:text-slate-300',
  'shield-check': 'text-violet-600 dark:text-violet-300',
  globe: 'text-amber-600 dark:text-amber-300',
}

/**
 * The chip sits INSIDE a `RecordCard`, i.e. on `bg-card` already: a `bg-card`
 * fill would be the very surface it lies on (ui-design.md §1-bis). It wears the
 * tint instead — the veil applied over the host surface, which is what a chip is
 * — plus the hairline, so it reads as raised in both themes.
 */
const CHIP_CLASS =
  'inline-flex min-w-0 max-w-full items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-1 text-xs'

/**
 * The dialable/mailable href of a contact, or null when it cannot be built
 * safely. Every candidate goes through `new URL()` and the scheme allow-list:
 * a value carrying its own `javascript:`/`data:` prefix never survives it.
 */
function contactHref(contact: PrimaryContact): string | null {
  const raw = rawHref(contact)

  if (raw === null) {
    return null
  }

  try {
    const url = new URL(raw)
    return ALLOWED_SCHEMES.includes(url.protocol) ? url.href : null
  } catch {
    return null
  }
}

/** The candidate href before validation: dial-stripped for phones, scheme-completed for websites. */
function rawHref(contact: PrimaryContact): string | null {
  const value = contact.value.trim()

  if (value === '') {
    return null
  }

  if (contact.type === 'website') {
    return value.includes('://') ? value : `https://${value}`
  }

  const scheme = CONTACT_SCHEME[contact.type]

  if (scheme === undefined) {
    return null
  }

  if (scheme === 'tel:') {
    // Only what a dialler understands: spaces, dots and parentheses in a
    // stored number would otherwise make the whole href invalid.
    const dialable = value.replace(/[^\d+]/g, '')
    return dialable === '' ? null : `tel:${dialable}`
  }

  return /[\s<>"]/.test(value) ? null : `${scheme}${value}`
}

/** Decorative type glyph, tinted. `createElement` keeps the resolved lucide component data, not a component declared during render. */
function contactIcon(contact: PrimaryContact) {
  const icon = resolveEnumIcon(contact.icon)
  const tint = contact.icon ? CONTACT_ICON_TINT[contact.icon] : undefined

  return icon
    ? createElement(icon, { 'aria-hidden': 'true', className: cn('size-3.5 shrink-0', tint) })
    : null
}

interface ContactChipsProps {
  contacts: PrimaryContact[]
  className?: string
}

/** Renders nothing at all when there is no contact: the caller decides what an empty card says. */
export function ContactChips({ contacts, className }: ContactChipsProps) {
  if (contacts.length === 0) {
    return null
  }

  return (
    <ul className={cn('flex flex-wrap gap-1.5', className)}>
      {contacts.map((contact) => {
        const typeLabel = enumLabelOf('contact_type', contact.type)
        const href = contactHref(contact)
        const isExternal = href !== null && href.startsWith('http')

        return (
          <li key={`${contact.type}-${contact.value}`} className="min-w-0">
            {href === null ? (
              <span className={cn(CHIP_CLASS, 'text-muted-foreground')}>
                {contactIcon(contact)}
                <span className="truncate">{contact.value}</span>
              </span>
            ) : (
              <a
                href={href}
                aria-label={`${typeLabel}: ${contact.value}`}
                title={contact.label}
                {...(isExternal ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                className={cn(
                  CHIP_CLASS,
                  'text-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring',
                )}
              >
                {contactIcon(contact)}
                <span className="truncate">{contact.value}</span>
              </a>
            )}
          </li>
        )
      })}
    </ul>
  )
}
