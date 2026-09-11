import { useTranslation } from 'react-i18next'
import { IdCard, Mail, MapPin, Pencil, Radar } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { enumLabelOf } from '@/features/config/enum-label'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

/**
 * Identity band and KPI strip of the referente record card. Kept in one file:
 * both read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

interface ReferentDetailHeaderProps {
  referent: ReferentDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band: monogram, name, the referent type as subtitle, the contact
 * ambit as a pill, edit action.
 *
 * The linked system user (spec 0090 D-2) is deliberately NOT a pill here: an
 * unlabelled name next to the record's own name reads as a second identity for
 * the same person. It stays a labelled row in the details section, where
 * "Linked user: Ada" says exactly what it is.
 */
export function ReferentDetailHeader({ referent, onEdit }: ReferentDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && referent.permissions.resource.update

  return (
    <RecordCardHeader
      media={<DetailMonogram name={referent.name} className="size-10 text-base" />}
      title={referent.name}
      subtitle={referent.referent_type?.name}
      badges={
        <Badge variant="secondary" className="gap-1.5">
          <Radar aria-hidden="true" />
          {enumLabelOf('referent_contact_scope', referent.contact_scope)}
        </Badge>
      }
      actions={
        canEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

/**
 * KPI strip: how reachable this person is, plus the kind of card behind them.
 *
 * Three tiles, not four, and none of them repeats the header: the type and the
 * ambit are already pills up there, and a strip echoing them would read as the
 * same field twice rather than as a summary of the record. Its labels are its
 * OWN (`referents.detail.stats.*`) for the same reason — the side column
 * already titles the contacts and addresses blocks.
 */
export function ReferentDetailStats({ referent }: { referent: ReferentDetailWithPermissions }) {
  const { t } = useTranslation()
  const card = referent.personal_data

  return (
    <RecordStatStrip>
      <RecordStat
        icon={<Mail />}
        label={t('referents.detail.stats.contacts')}
        value={card?.contacts.length ?? 0}
      />
      <RecordStat
        icon={<MapPin />}
        label={t('referents.detail.stats.addresses')}
        value={card?.addresses.length ?? 0}
      />
      <RecordStat
        icon={<IdCard />}
        label={t('referents.detail.stats.cardType')}
        value={card ? enumLabelOf('personal_data_type', card.type) : <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
