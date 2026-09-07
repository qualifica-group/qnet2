import { Briefcase, FileSignature } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { type Control, useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { FormControl, FormDescription } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { toRelationFieldRef, toRelationFieldRefs } from '@/components/form/relation-field-ref'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { RELATIONSHIP_TYPES, type RelationshipType } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Radix `Select` cannot hold an empty-string value: "no selection" uses this sentinel. */
const NONE_VALUE = '__none__'

interface EmploymentTabProps {
  control: Control<UserFormValues>
}

interface ProfileTabContentProps extends EmploymentTabProps {
  selectedBusinessFunctionItem: ForSelectItem | null
  selectedReportsToItem: ForSelectItem | null
}

/**
 * Profile tab: organizational role (business function, manager status, job
 * description) and the reporting line. `reports_to` is hidden and its value
 * force-nulled at the payload boundary whenever `is_manager` is true (AC-015).
 */
export function ProfileTabContent({
  control,
  selectedBusinessFunctionItem,
  selectedReportsToItem,
}: ProfileTabContentProps) {
  const { t } = useTranslation()
  const isManager = useWatch({ control, name: 'employment.is_manager' })

  return (
    <FormSection
      icon={Briefcase}
      title={t('users.form.sections.profile.title')}
      description={t('users.form.sections.profile.description')}
    >
      <RelationSelectField
        control={control}
        name="employment.business_function_id"
        metaKey="employment.business_function_id"
        label={t('users.form.employment.businessFunction')}
        resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('users.form.employment.businessFunctionSearch')}
        selected={toRelationFieldRef(selectedBusinessFunctionItem)}
        placeholder={t('users.form.employment.businessFunctionPlaceholder')}
        emptyLabel={t('users.form.employment.businessFunctionEmpty')}
        errorLabel={t('users.form.employment.businessFunctionError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="employment.is_manager"
        metaKey="employment.is_manager"
        label={t('users.form.employment.isManager')}
        description={<FormDescription>{t('users.form.employment.isManagerDescription')}</FormDescription>}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="employment.job_description"
        metaKey="employment.job_description"
        label={t('users.form.employment.jobDescription')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      {isManager ? null : (
        <RelationSelectField
          control={control}
          name="employment.reports_to_id"
          metaKey="employment.reports_to_id"
          label={t('users.form.employment.reportsTo')}
          resource={USERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('users.form.employment.reportsToSearch')}
          selected={toRelationFieldRef(selectedReportsToItem)}
          showAvatar
          placeholder={t('users.form.employment.reportsToPlaceholder')}
          emptyLabel={t('users.form.employment.reportsToEmpty')}
          errorLabel={t('users.form.employment.reportsToError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      )}
    </FormSection>
  )
}

interface ContractTabContentProps extends EmploymentTabProps {
  selectedCompanyItem: ForSelectItem | null
  selectedPrimaryOperationalSiteItem: ForSelectItem | null
  selectedRemoteOperationalSiteItems: ForSelectItem[]
}

/**
 * Contract tab: relationship type, company, physical site and remote sites.
 * The physical site is at most one (spec 0103 D-3); the remote sites are a
 * multi-select fed by the same for-select resource (D-1: operative exactly
 * like the physical one).
 */
export function ContractTabContent({
  control,
  selectedCompanyItem,
  selectedPrimaryOperationalSiteItem,
  selectedRemoteOperationalSiteItems,
}: ContractTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={FileSignature}
      title={t('users.form.sections.contract.title')}
      description={t('users.form.sections.contract.description')}
    >
      <MetaField
        control={control}
        name="employment.relationship_type"
        metaKey="employment.relationship_type"
        label={t('users.form.employment.relationshipType')}
      >
        {({ field, disabled }) => (
          <Select
            value={field.value ?? NONE_VALUE}
            onValueChange={(next) =>
              field.onChange(next === NONE_VALUE ? null : (next as RelationshipType))
            }
            disabled={disabled}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value={NONE_VALUE}>
                {t('users.form.employment.relationshipTypeNone')}
              </SelectItem>
              {RELATIONSHIP_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`enums.relationship_type.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      <RelationSelectField
        control={control}
        name="employment.company_id"
        metaKey="employment.company_id"
        label={t('users.form.employment.company')}
        resource={COMPANIES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('users.form.employment.companySearch')}
        selected={toRelationFieldRef(selectedCompanyItem)}
        placeholder={t('users.form.employment.companyPlaceholder')}
        emptyLabel={t('users.form.employment.companyEmpty')}
        errorLabel={t('users.form.employment.companyError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <RelationSelectField
        control={control}
        name="employment.primary_operational_site_id"
        metaKey="employment.primary_operational_site_id"
        label={t('users.form.employment.primaryOperationalSite')}
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('users.form.employment.primaryOperationalSiteSearch')}
        selected={toRelationFieldRef(selectedPrimaryOperationalSiteItem)}
        placeholder={t('users.form.employment.primaryOperationalSitePlaceholder')}
        emptyLabel={t('users.form.employment.primaryOperationalSiteEmpty')}
        errorLabel={t('users.form.employment.primaryOperationalSiteError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <RelationMultiSelectField
        control={control}
        name="employment.remote_operational_site_ids"
        metaKey="employment.remote_operational_site_ids"
        label={t('users.form.employment.remoteOperationalSites')}
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('users.form.employment.remoteOperationalSitesSearch')}
        selected={toRelationFieldRefs(selectedRemoteOperationalSiteItems)}
        placeholder={t('users.form.employment.remoteOperationalSitesPlaceholder')}
        emptyLabel={t('users.form.employment.remoteOperationalSitesEmpty')}
        errorLabel={t('users.form.employment.remoteOperationalSitesError')}
        removeLabel={t('users.form.employment.remoteOperationalSitesRemove')}
        retryLabel={t('common.retry')}
      />
    </FormSection>
  )
}
