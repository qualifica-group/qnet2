import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { ContactChips } from '@/components/detail/contact-chips'
import { RecordLink } from '@/components/detail/record-link'
import type { ReferentRef, ReferentTypeRef } from '@/features/referents/types'
import type { PrimaryContact } from '@/features/table/types'

const EMPTY_CONTACTS: PrimaryContact[] = []

/** The referent's Tipo referente (e.g. "Amministratore") as a muted line; nothing when it has none. */
export function ReferentTypeLine({ type }: { type?: ReferentTypeRef | null }) {
  const { t } = useTranslation()
  if (!type) {
    return null
  }
  return (
    <span className="truncate text-xs text-muted-foreground">
      <span className="sr-only">{t('referents.form.referentType')}: </span>
      {type.name}
    </span>
  )
}

/**
 * A "Referente" row value: the link to the referent with its Tipo referente
 * under it, or the empty marker. When the payload carries them (commessa
 * detail, task 428287), also the "primary referent of the registry" note and
 * the callable primary contacts.
 */
export function ReferentValue({ referent }: { referent: ReferentRef | null }) {
  const { t } = useTranslation()
  if (!referent) {
    return <DetailEmpty />
  }
  return (
    <div className="flex min-w-0 flex-col gap-0.5">
      <RecordLink domain="referents" id={referent.id}>
        {referent.name}
      </RecordLink>
      <ReferentTypeLine type={referent.referent_type} />
      {referent.is_registry_primary ? (
        <span className="truncate text-xs text-muted-foreground">{t('referents.detail.registryPrimary')}</span>
      ) : null}
      <ContactChips contacts={referent.primary_contacts ?? EMPTY_CONTACTS} className="mt-1" />
    </div>
  )
}
