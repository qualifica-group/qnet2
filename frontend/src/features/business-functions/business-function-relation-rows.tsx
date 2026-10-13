import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { DetailEmpty, DetailPerson } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import type { BusinessFunctionRecordValues } from '@/features/business-functions/business-function-record'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

interface RelationRowProps {
  inline: InlineEdit
  canEdit: boolean
  values: BusinessFunctionRecordValues
}

/** The parent function: its name when closed, the business-functions picker when open. */
export function BusinessFunctionParentRow({ inline, canEdit, values }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()

  return (
    <RecordInlineField
      field="parent_id"
      label={t('businessFunctions.form.parent')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="parent_id"
          metaKey="parent_id"
          label={t('businessFunctions.form.parent')}
          resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('businessFunctions.form.parentSearch')}
          selected={values.parent}
          placeholder={t('businessFunctions.form.parentPlaceholder')}
          emptyLabel={t('businessFunctions.form.parentEmpty')}
          errorLabel={t('businessFunctions.form.parentError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {values.parent ? values.parent.name : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** The responsible user: avatar and name when closed, the users picker when open. */
export function BusinessFunctionManagerRow({ inline, canEdit, values }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()

  return (
    <RecordInlineField
      field="manager_id"
      label={t('businessFunctions.form.manager')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="manager_id"
          metaKey="manager_id"
          label={t('businessFunctions.form.manager')}
          resource={USERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('businessFunctions.form.usersSearch')}
          selected={values.manager}
          showAvatar
          placeholder={t('businessFunctions.form.managerPlaceholder')}
          emptyLabel={t('businessFunctions.form.usersEmpty')}
          errorLabel={t('businessFunctions.form.usersError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {values.manager ? <DetailPerson name={values.manager.name} avatarUrl={values.manager.avatar_url} /> : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** The associated users, synced as a whole set: every person when closed, the multi picker when open. */
export function BusinessFunctionUsersRow({ inline, canEdit, values }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()

  return (
    <RecordInlineField
      field="users"
      label={t('businessFunctions.form.users')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name="users"
          metaKey="users"
          label={t('businessFunctions.form.users')}
          resource={USERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('businessFunctions.form.usersSearch')}
          selected={values.users}
          showAvatar
          placeholder={t('businessFunctions.form.usersPlaceholder')}
          emptyLabel={t('businessFunctions.form.usersEmpty')}
          errorLabel={t('businessFunctions.form.usersError')}
          removeLabel={t('businessFunctions.form.usersRemove')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {values.users.length > 0 ? (
        <div className="flex flex-col gap-3">
          {values.users.map((user) => (
            <DetailPerson key={user.id} name={user.name} avatarUrl={user.avatar_url} />
          ))}
        </div>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

/** The operational sites, synced as a whole set: linked badges when closed, the multi picker when open. */
export function BusinessFunctionSitesRow({ inline, canEdit, values }: RelationRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<BusinessFunctionFormValues>()

  return (
    <RecordInlineField
      field="operational_sites"
      label={t('businessFunctions.form.operationalSites')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name="operational_sites"
          metaKey="operational_sites"
          label={t('businessFunctions.form.operationalSites')}
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('businessFunctions.form.operationalSitesSearch')}
          selected={values.operational_sites.map((site) => ({ id: site.id, name: site.label }))}
          placeholder={t('businessFunctions.form.operationalSitesPlaceholder')}
          emptyLabel={t('businessFunctions.form.operationalSitesEmpty')}
          errorLabel={t('businessFunctions.form.operationalSitesError')}
          removeLabel={t('businessFunctions.form.operationalSitesRemove')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {values.operational_sites.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {values.operational_sites.map((site) => (
            <Badge key={site.id} variant="outline" className="max-w-full">
              <RecordLink domain="operational-sites" id={site.id}>
                {site.label}
              </RecordLink>
            </Badge>
          ))}
        </div>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}
