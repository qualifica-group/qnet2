import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, IdCard, MapPin, Phone, UserRound } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { useController } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { AddressCreateField } from '@/features/personal-data/address-create-field'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

interface RequestClientSectionProps {
  control: Control<RequestWorkFormValues>
}

interface ClientGroupProps {
  icon: LucideIcon
  title: string
  children: ReactNode
  /** When true the title becomes a toggle folding the group's fields away (default: false). */
  collapsible?: boolean
  /** Uncontrolled initial open state, only read when `collapsible` (default: true). */
  defaultOpen?: boolean
}

const GROUP_TITLE_CLASS =
  'flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase'

/** `cursor-pointer` is explicit: Tailwind 4's reset leaves a `<button>` on the default arrow. */
const GROUP_TRIGGER_CLASS =
  'group flex w-full cursor-pointer items-center gap-1.5 rounded-sm text-left outline-none transition-colors hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50'

/**
 * One labelled group inside the anagraphic card. Compact by design: the card
 * header already carries the section identity, these only say which part of it
 * the fields below belong to. Exported: the create form (spec 0057) reuses it
 * verbatim for its own anagrafica section (`request-create-client-section.tsx`).
 *
 * Optionally `collapsible` (user directive 2026-09-10, for the address group):
 * the micro-title becomes the toggle, with the same rotating chevron and the
 * same height animation `FormSection` uses one level up — a group folds like a
 * section, it does not invent a second interaction.
 */
export function ClientGroup({
  icon: Icon,
  title,
  children,
  collapsible = false,
  defaultOpen = true,
}: ClientGroupProps) {
  const heading = (
    <>
      <Icon className="size-3.5 shrink-0" aria-hidden="true" />
      {title}
    </>
  )

  if (!collapsible) {
    return (
      <div className="flex flex-col gap-2.5">
        <h4 className={GROUP_TITLE_CLASS}>{heading}</h4>
        {children}
      </div>
    )
  }

  return (
    <Collapsible defaultOpen={defaultOpen} className="flex flex-col gap-2.5">
      <h4 className={GROUP_TITLE_CLASS}>
        {/* Explicit `type`: this group can sit inside a `<form>`, where an
            untyped button submits. */}
        <CollapsibleTrigger type="button" className={GROUP_TRIGGER_CLASS}>
          {heading}
          <ChevronDown
            className="ml-auto size-3.5 shrink-0 transition-transform motion-safe:duration-200 group-data-[state=open]:rotate-180"
            aria-hidden="true"
          />
        </CollapsibleTrigger>
      </h4>
      <CollapsibleContent className="form-section-collapsible-content">{children}</CollapsibleContent>
    </Collapsible>
  )
}

/**
 * The "anagrafica" section of the work panel: the client's identity, contacts
 * and address in a SINGLE card, split into labelled groups, all as
 * ALWAYS-ACTIVE inline inputs (the quick-create surface, not the
 * read-then-open-a-dialog one) so the operator types straight into them.
 *
 * The identity group reuses `PersonalDataCardForm` verbatim — the same
 * individual/company toggle and fiscal fields (tax code, VAT number, SDI,
 * birth date, gender) the Registries and Users forms render — so this panel
 * captures the client's data through the exact same surface, never a
 * look-alike. It is rendered only when the client HAS a card: without one
 * there is no write target (`client_identity` is then null on the wire).
 *
 * All three fields are buffered inside the panel's RHF form and travel with its
 * single submit — no per-field persistence — which is why `ContactsManager`
 * gets no `persistence` prop here: `createMode` (the four inline quick fields)
 * and immediate persistence do not compose, the quick fields write to the
 * buffer only. Extra channels beyond email/phone/pec/fax stay available
 * through the manager's own dialog and are saved by the same submit.
 */
export function RequestClientSection({ control }: RequestClientSectionProps) {
  const { t } = useTranslation()
  const identity = useController({ control, name: 'client_identity' })
  const contacts = useController({ control, name: 'client_contacts' })
  const address = useController({ control, name: 'client_address' })
  const identityDraft = identity.field.value

  return (
    <FormSection
      icon={IdCard}
      title={t('requestManagement.workPanel.client.title', { defaultValue: 'Client details' })}
      description={t('requestManagement.workPanel.client.description', {
        defaultValue: 'Contacts and address of the client.',
      })}
    >
      {identityDraft && (
        <>
          <ClientGroup
            icon={UserRound}
            title={t('requestManagement.workPanel.client.identityGroup', { defaultValue: 'Identity' })}
          >
            <PersonalDataCardForm value={identityDraft} onChange={identity.field.onChange} />
          </ClientGroup>

          <div className="border-t" />
        </>
      )}

      <ClientGroup
        icon={Phone}
        title={t('requestManagement.workPanel.client.contactsGroup', { defaultValue: 'Contacts' })}
      >
        <ContactsManager
          value={contacts.field.value}
          onChange={contacts.field.onChange}
          showHeader={false}
          createMode
        />
      </ClientGroup>

      <div className="border-t" />

      <ClientGroup
        icon={MapPin}
        title={t('requestManagement.workPanel.client.addressGroup', { defaultValue: 'Address' })}
      >
        <AddressCreateField
          value={address.field.value}
          onChange={address.field.onChange}
          cityRequired={false}
        />
      </ClientGroup>
    </FormSection>
  )
}
