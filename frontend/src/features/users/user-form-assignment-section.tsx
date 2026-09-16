import { Info, Target } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { toRelationFieldRef, toRelationFieldRefs } from '@/components/form/relation-field-ref'
import { MetaField } from '@/features/authorization/MetaField'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { CompetenceLinesField } from '@/features/product-lines/competence-lines-field'
import type { KnownProductLine } from '@/features/product-lines/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserFormValues } from '@/features/users/use-user-form'

interface UserAssignmentSectionProps {
  control: Control<UserFormValues>
  /** The persisted competence pairs, whose `{id, name}` projections label the rows without a fetch. */
  knownProductLines: KnownProductLine[]
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
 * Spec 0129 D-1/D-2: the "competent for all categories" switch is the row
 * editor's jolly — checking it hides the editor and clears its rows (one
 * state only, no dormant rows survive under a true flag); unchecking it opens
 * back on an empty editor, never on what was cleared. The row editor itself
 * is `CompetenceLinesField` (spec 0111 D-5: no `single`-mode cap; spec
 * 0129 D-6/D-7: container categories pickable, per-row "all categories"
 * checkbox; spec 0132 D-4: this contract is unaffected by the card's move to
 * root-category classification).
 */
export function UserAssignmentSection({
  control,
  knownProductLines,
  selectedPrimaryOperationalSiteItem,
  selectedRemoteOperationalSiteItems,
}: UserAssignmentSectionProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<UserFormValues>()
  const coversAllProductCategories = useWatch({ control, name: 'employment.covers_all_product_categories' })

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
        name="employment.covers_all_product_categories"
        metaKey="employment.covers_all_product_categories"
        layout="inline"
        label={t('users.form.employment.coversAllProductCategories')}
        description={
          <FormDescription>{t('users.form.employment.coversAllProductCategoriesDescription')}</FormDescription>
        }
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch
              checked={field.value}
              onCheckedChange={(checked) => {
                field.onChange(checked)
                // D-2: one state only — a row set left under a true flag would be a
                // dormant value the server discards anyway; clearing it here keeps
                // the editor's own reappearance (unchecking) genuinely empty.
                if (checked) {
                  setValue('employment.product_lines', [], { shouldDirty: true })
                }
              }}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>

      {coversAllProductCategories ? null : (
        <MetaField
          control={control}
          name="employment.product_lines"
          metaKey="employment.product_lines"
          label={t('users.form.employment.productLines')}
          hint={t('users.form.employment.productLinesHint')}
        >
          {({ field, disabled }) => (
            <CompetenceLinesField
              value={field.value}
              onChange={field.onChange}
              knownLines={knownProductLines}
              disabled={disabled}
            />
          )}
        </MetaField>
      )}

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
