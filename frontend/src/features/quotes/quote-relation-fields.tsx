import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { toManagerSlotLabels } from '@/lib/utils'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { quoteRelationLabels } from '@/features/quotes/quote-field-strings'
import { useQuoteManagerLabels } from '@/features/quotes/use-quote-manager-labels'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

/*
 * The offer's people and sites — Commerciale, Supervisore, Gestori Account,
 * Societa', Societa' sede, Sede operativa — one component each, shared by
 * the detail's in-place rows and the create draft's rows (spec 0197). The
 * Segnalatore keeps its own `QuoteReporterField`, which carries the buoni.
 */

interface FieldProps {
  control: Control<QuoteFormValues>
}

interface RelationFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never falls back to `#id`. */
  selected: RelationFieldRef | null
}

/** "Commerciale" (a referent, D-3): a snapshot inherited from the Opportunita' on create, then free. */
export function QuoteCommercialField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="commercial_id"
      metaKey="commercial_id"
      label={t('quotes.form.commercial')}
      resource={REFERENTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.commercialSearch')}
      selected={selected}
      {...quoteRelationLabels(t)}
    />
  )
}

/** "Supervisore" (a user, spec 0097 rev-2 D-8): same snapshot rule as the Commerciale. */
export function QuoteSupervisorField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="supervisor_id"
      metaKey="supervisor_id"
      label={t('quotes.form.supervisor')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.supervisorSearch')}
      selected={selected}
      showAvatar
      {...quoteRelationLabels(t)}
    />
  )
}

interface ManagersFieldProps extends FieldProps {
  /** `{id, label}` of the filled slots, so a card never falls back to `#id`. */
  selectedItems: ForSelectItem[]
  /** D-7: the offer's G.A. mirror its Opportunita's (a persisted offer only). */
  synchronized: boolean
}

/**
 * The ordered, gap-aware "G.A. n" slots (spec 0087 D-1/D-11, the shared
 * `ManagerSlotsField`). Each slot is relabeled LIVE from the offer's own
 * REVENUE rows (D-8, `useQuoteManagerLabels`), falling back to the linked
 * Opportunity's categories while there are none yet.
 */
export function QuoteManagersField({ control, selectedItems, synchronized }: ManagersFieldProps) {
  const { t } = useTranslation()
  const opportunityId = useWatch({ control, name: 'opportunity_id' })
  const offerLines = useWatch({ control, name: 'offer_lines' })
  const slotLabels = toManagerSlotLabels(useQuoteManagerLabels(offerLines ?? [], opportunityId))

  return (
    <div className="flex flex-col gap-2">
      {synchronized ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Info aria-hidden="true" className="size-3.5 shrink-0" />
          {t('quotes.form.managersSyncHint')}
        </p>
      ) : null}
      <MetaField control={control} name="manager_slots" metaKey="manager_slots" label={t('quotes.form.managers')}>
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedItems}
            disabled={disabled}
            labels={slotLabels}
          />
        )}
      </MetaField>
    </div>
  )
}

interface CompanyFieldProps extends RelationFieldProps {
  setValue: UseFormSetValue<QuoteFormValues>
}

/**
 * "Societa'" (directive 2026-07-30): a different company clears the Societa'
 * sede, so the pair can never drift — the server rejects a mismatched one
 * anyway (ValidatesQuoteCompanySite). The cleared sede travels in the same save.
 */
export function QuoteCompanyField({ control, selected, setValue }: CompanyFieldProps) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })
  return (
    <RelationSelectField
      control={control}
      name="company_id"
      metaKey="company_id"
      label={t('quotes.form.company')}
      resource={COMPANIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.companySearch')}
      selected={selected}
      onValueChange={(next) => {
        if (next !== companyId) {
          setValue('company_site_id', null, { shouldDirty: true })
        }
      }}
      {...quoteRelationLabels(t)}
    />
  )
}

/** "Societa' sede": scoped to the chosen Societa' (`company_id` param), locked without one. */
export function QuoteCompanySiteField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })
  return (
    <RelationSelectField
      control={control}
      name="company_site_id"
      metaKey="company_site_id"
      label={t('quotes.form.companySite')}
      hint={t('quotes.form.hints.companySite')}
      resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.companySiteSearch')}
      selected={selected}
      forceDisabled={companyId === null}
      params={companyId !== null ? { company_id: companyId } : undefined}
      {...quoteRelationLabels(t)}
    />
  )
}

/** "Sede operativa": inherited from the Opportunita' on create (directive 2026-07-30), then free. */
export function QuoteOperationalSiteField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="operational_site_id"
      metaKey="operational_site_id"
      label={t('quotes.form.operationalSite')}
      hint={t('quotes.form.hints.operationalSite')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.operationalSiteSearch')}
      selected={selected}
      {...quoteRelationLabels(t)}
    />
  )
}
