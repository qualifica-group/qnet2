import { useTranslation } from 'react-i18next'
import { MapPin, Target } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { Badge } from '@/components/ui/badge'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { UserSwitchField } from '@/features/users/user-account-fields'
import { UserAssignmentCallout } from '@/features/users/user-assignment-callout'
import {
  UserCompetenceField,
  UserCoversAllField,
  UserPrimarySiteField,
  UserRemoteSitesField,
} from '@/features/users/user-employment-fields'
import { COVERS_ALL_CASCADE_FIELDS, type UserRecordSectionProps } from '@/features/users/user-record'
import type { EmploymentRelationRef } from '@/features/users/types'

/** Zero or more Sedi as linked chips; the shared empty marker when there is none. */
function SiteBadges({ sites }: { sites: EmploymentRelationRef[] }) {
  if (sites.length === 0) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-wrap gap-1">
      {sites.map((site) => (
        <Badge key={site.id} variant="secondary" className="max-w-full">
          <RecordLink domain="operational-sites" id={site.id}>
            {site.label}
          </RecordLink>
        </Badge>
      ))}
    </div>
  )
}

/**
 * The person's ASSIGNMENT configuration, first and full width (user directive
 * 2026-09-11): the "Assegnabile" flag, the competence rows and the Sedi,
 * which together decide whether a record can ever reach them. The callout
 * names what is missing, only when something is. The jolly switch clears the
 * competence rows (spec 0129 D-2), so its row opens only when they are
 * editable too, and the rows are hidden while it is on.
 */
export function UserAssignmentRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const coversAllEditable = useCascadeEditable(COVERS_ALL_CASCADE_FIELDS)

  return (
    <RecordSection title={t('users.assignment.title')} icon={<Target />} full>
      {values.assignment.assignable ? null : <UserAssignmentCallout summary={values.assignment} />}
      <RecordFieldList>
        <RecordInlineField
          field="employment.is_assignable"
          label={t('users.form.employment.isAssignable')}
          inline={inline}
          editor={
            <UserSwitchField
              control={control}
              name="employment.is_assignable"
              label={t('users.form.employment.isAssignable')}
              description={t('users.form.employment.isAssignableDescription')}
            />
          }
        >
          {t(values.is_assignable ? 'common.yes' : 'common.no')}
        </RecordInlineField>
        <RecordInlineField
          field="employment.covers_all_product_categories"
          label={t('users.form.employment.coversAllProductCategories')}
          inline={inline}
          canEdit={coversAllEditable}
          editor={<UserCoversAllField control={control} />}
        >
          {t(values.covers_all_product_categories ? 'common.yes' : 'common.no')}
        </RecordInlineField>
        {values.covers_all_product_categories ? null : (
          <RecordInlineField
            field="employment.product_lines"
            label={t('users.detail.employment.productLines')}
            inline={inline}
            editor={<UserCompetenceField control={control} knownLines={values.product_lines} />}
          >
            <ProductLinesReadOnlyList lines={values.product_lines} />
          </RecordInlineField>
        )}
        <RecordInlineField
          field="employment.primary_operational_site_id"
          label={t('users.detail.employment.primaryOperationalSite')}
          icon={<MapPin />}
          inline={inline}
          editor={<UserPrimarySiteField control={control} selected={values.primary_operational_site} />}
        >
          {values.primary_operational_site ? (
            <RecordLink domain="operational-sites" id={values.primary_operational_site.id}>
              {values.primary_operational_site.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="employment.remote_operational_site_ids"
          label={t('users.detail.employment.remoteOperationalSites')}
          icon={<MapPin />}
          inline={inline}
          editor={<UserRemoteSitesField control={control} selected={values.remote_operational_sites} />}
        >
          <SiteBadges sites={values.remote_operational_sites} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
