import { Info, Target } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { toRelationFieldRef, toRelationFieldRefs } from '@/components/form/relation-field-ref'
import { MetaField } from '@/features/authorization/MetaField'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLine } from '@/features/product-lines/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserFormValues } from '@/features/users/use-user-form'

interface UserAssignmentSectionProps {
  control: Control<UserFormValues>
  /** The persisted competence pairs, whose `{id, name}` projections label the rows without a fetch. */
  knownProductLines: ProductLine[]
  selectedPrimaryOperationalSiteItem: ForSelectItem | null
  selectedRemoteOperationalSiteItems: ForSelectItem[]
}

/**
 * The person's ASSIGNMENT configuration, gathered in one block at the top of
 * the form (user directive 2026-09-11): the competence rows
 * (funzione aziendale -> categoria prodotto) and the Sedi — physical and
 * remote — that together decide whether a record can ever reach them.
 *
 * They used to sit in two different sections, competence under "Profilo" and
 * the Sedi under "Rapporto contrattuale", as if the Sede were a contractual
 * fact. It is not: the server reads it as one half of the assignment pool
 * (`AssignmentCandidates` = operators of the record's Sede INTERSECTED with
 * those competent for its categories), so the two halves belong next to each
 * other and nowhere else. What stayed behind in those sections is what really
 * describes the contract — relationship type, company, reporting line.
 *
 * The `single`-mode row cap of `ProductLinesField` is opted out here (spec
 * 0111 D-5): it is an invariant of a commercial card, not of a person's
 * competence.
 */
export function UserAssignmentSection({
  control,
  knownProductLines,
  selectedPrimaryOperationalSiteItem,
  selectedRemoteOperationalSiteItems,
}: UserAssignmentSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Target}
      title={t('users.assignment.title')}
      description={t('users.assignment.description')}
    >
      <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
        <Info className="mt-px size-3.5 shrink-0" aria-hidden="true" />
        {t('users.assignment.rule')}
      </p>

      <MetaField
        control={control}
        name="employment.product_lines"
        metaKey="employment.product_lines"
        label={t('users.form.employment.productLines')}
        hint={t('users.form.employment.productLinesHint')}
      >
        {({ field, disabled }) => (
          <ProductLinesField
            value={field.value}
            onChange={field.onChange}
            knownLines={knownProductLines}
            disabled={disabled}
            enforceManagementModeCap={false}
          />
        )}
      </MetaField>

      <div className="border-t" />

      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="employment.primary_operational_site_id"
          metaKey="employment.primary_operational_site_id"
          label={t('users.form.employment.primaryOperationalSite')}
          hint={t('users.assignment.sitesHint')}
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
      </div>
    </FormSection>
  )
}
