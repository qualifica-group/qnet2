import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { Switch } from '@/components/ui/switch'
import { useAbsenceTypeOptions } from '@/features/absences/requests/use-absence-type-options'
import { canRepeatYearly, type CompanyClosureFormValues } from '@/features/company-closures/company-closure-schema'
import { COMPANY_CLOSURE_KINDS, type CompanyClosureRef } from '@/features/company-closures/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'

interface CompanyClosureFieldsProps {
  /** Every control is disabled (a closure that already started). */
  locked: boolean
  /** Sites and absence type of the saved closure: the pickers hydrate their labels from them. */
  knownSites: CompanyClosureRef[]
  knownAbsenceType: CompanyClosureRef | null
}

/** The closure form's fields; the conditional ones (type of absence, chosen sites, recurrence) appear only when their rule applies. */
export function CompanyClosureFields({ locked, knownSites, knownAbsenceType }: CompanyClosureFieldsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CompanyClosureFormValues>()
  const [kind, allSites, startsOn, endsOn] = useWatch({
    control,
    name: ['kind', 'applies_to_all_sites', 'starts_on', 'ends_on'],
  })
  const kindOptions = useMemo(
    () => COMPANY_CLOSURE_KINDS.map((value) => ({ value, label: t(`companyClosures.kinds.${value}`) })),
    [t],
  )

  return (
    <div className="flex flex-col gap-4">
      <FormField
        control={control}
        name="name"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('companyClosures.form.name')}</FormLabel>
            <FormControl>
              <Input {...field} disabled={locked} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <DateField name="starts_on" label={t('companyClosures.form.startsOn')} locked={locked} />
        <DateField name="ends_on" label={t('companyClosures.form.endsOn')} locked={locked} />
      </div>

      <FormField
        control={control}
        name="kind"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('companyClosures.form.kind')}</FormLabel>
            <FormControl>
              <SegmentedControl value={field.value} options={kindOptions} onValueChange={field.onChange} disabled={locked} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />

      {kind === 'collective_leave' ? <AbsenceTypeField locked={locked} knownAbsenceType={knownAbsenceType} /> : null}

      <SwitchField name="applies_to_all_sites" label={t('companyClosures.form.appliesToAllSites')} locked={locked} />
      {allSites ? null : <SitesField locked={locked} knownSites={knownSites} />}

      {canRepeatYearly({ kind, starts_on: startsOn, ends_on: endsOn }) ? (
        <SwitchField
          name="repeats_yearly"
          label={t('companyClosures.form.repeatsYearly')}
          hint={t('companyClosures.form.repeatsYearlyHint')}
          locked={locked}
        />
      ) : null}
    </div>
  )
}

interface DateFieldProps {
  name: 'starts_on' | 'ends_on'
  label: string
  locked: boolean
}

function DateField({ name, label, locked }: DateFieldProps) {
  const { control } = useFormContext<CompanyClosureFormValues>()
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{label}</FormLabel>
          <FormControl>
            <Input type="date" {...field} disabled={locked} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

interface SwitchFieldProps {
  name: 'applies_to_all_sites' | 'repeats_yearly'
  label: string
  hint?: string
  locked: boolean
}

function SwitchField({ name, label, hint, locked }: SwitchFieldProps) {
  const { control } = useFormContext<CompanyClosureFormValues>()
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="flex flex-col gap-1">
          <div className="flex items-center justify-between gap-3">
            <FormLabel>{label}</FormLabel>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={locked} />
            </FormControl>
          </div>
          {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

function AbsenceTypeField({ locked, knownAbsenceType }: Pick<CompanyClosureFieldsProps, 'locked' | 'knownAbsenceType'>) {
  const { t } = useTranslation()
  const { control } = useFormContext<CompanyClosureFormValues>()
  const optionsQuery = useAbsenceTypeOptions()
  // The saved type may have been deactivated since: it stays selectable so the form does not lose it.
  const options = useMemo(() => {
    const active = (optionsQuery.data ?? []).map(({ id, name }) => ({ id, name }))
    return knownAbsenceType && !active.some((option) => option.id === knownAbsenceType.id)
      ? [...active, knownAbsenceType]
      : active
  }, [optionsQuery.data, knownAbsenceType])

  return (
    <FormField
      control={control}
      name="absence_type_id"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('companyClosures.form.absenceType')}</FormLabel>
          <FormControl>
            <SearchableSelect
              value={field.value}
              onChange={field.onChange}
              onClear={() => field.onChange(null)}
              options={options}
              isPending={optionsQuery.isPending}
              isError={optionsQuery.isError}
              onRetry={() => void optionsQuery.refetch()}
              disabled={locked}
              labels={{
                placeholder: t('companyClosures.form.absenceTypePlaceholder'),
                searchPlaceholder: t('companyClosures.form.search'),
                empty: t('companyClosures.form.empty'),
                noMatch: t('companyClosures.form.noMatch'),
                error: t('companyClosures.form.loadError'),
                retry: t('common.retry'),
                clearLabel: t('companyClosures.form.clear'),
              }}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

function SitesField({ locked, knownSites }: Pick<CompanyClosureFieldsProps, 'locked' | 'knownSites'>) {
  const { t } = useTranslation()
  const { control } = useFormContext<CompanyClosureFormValues>()
  const selectedItems = useMemo(() => knownSites.map(({ id, name }) => ({ id, label: name })), [knownSites])

  return (
    <FormField
      control={control}
      name="operational_site_ids"
      render={({ field }) => (
        <FormItem>
          <FormLabel required>{t('companyClosures.form.sites')}</FormLabel>
          <FormControl>
            <AsyncPaginatedMultiSelect
              resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              selectedItems={selectedItems}
              disabled={locked}
              labels={{
                placeholder: t('companyClosures.form.sitesPlaceholder'),
                searchPlaceholder: t('companyClosures.form.search'),
                empty: t('companyClosures.form.empty'),
                error: t('companyClosures.form.loadError'),
                removeLabel: t('companyClosures.form.removeSite'),
                triggerLabel: t('companyClosures.form.sites'),
                retry: t('common.retry'),
              }}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
