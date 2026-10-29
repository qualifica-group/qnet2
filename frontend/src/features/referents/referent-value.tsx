import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import type { ReferentRef, ReferentTypeRef } from '@/features/referents/types'

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

/** A "Referente" row value: the link to the referent with its Tipo referente under it, or the empty marker. */
export function ReferentValue({ referent }: { referent: ReferentRef | null }) {
  if (!referent) {
    return <DetailEmpty />
  }
  return (
    <div className="flex min-w-0 flex-col gap-0.5">
      <RecordLink domain="referents" id={referent.id}>
        {referent.name}
      </RecordLink>
      <ReferentTypeLine type={referent.referent_type} />
    </div>
  )
}
