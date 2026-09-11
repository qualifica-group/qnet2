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
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { RELATIONSHIP_TYPES, type RelationshipType } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Radix `Select` cannot hold an empty-string value: "no selection" uses this sentinel. */
const NONE_VALUE = '__none__'

interface EmploymentTabProps {
  control: Control<UserFormValues>
}

interface ProfileTabContentProps extends EmploymentTabProps {
  selectedReportsToItem: ForSelectItem | null
}

/**
 * Profile section: what the person IS in the organization — manager status,
 * job description and the reporting line.
 *
 * The competence rows left this section (user directive 2026-09-11): they are
 * half of the assignment configuration, so they now live next to the Sedi in
 * `UserAssignmentSection`. `reports_to` is hidden and its value force-nulled
 * at the payload boundary whenever `is_manager` is true (AC-015).
 */
export function ProfileTabContent({ control, selectedReportsToItem }: ProfileTabContentProps) {
  const { t } = useTranslation()
  const isManager = useWatch({ control, name: 'employment.is_manager' })

  return (
    <FormSection
      icon={Briefcase}
      title={t('users.form.sections.profile.title')}
      description={t('users.form.sections.profile.description')}
    >
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
}

/**
 * Contract section: relationship type and employing company — the terms of the
 * contract, and nothing else.
 *
 * The physical and remote Sedi used to sit here as if they described the
 * contract. They do not: they are the Sede half of the assignment pool, and
 * they moved to `UserAssignmentSection` with the competence rows they are
 * always read together with (user directive 2026-09-11).
 */
export function ContractTabContent({ control, selectedCompanyItem }: ContractTabContentProps) {
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
    </FormSection>
  )
}
