import { useTranslation } from 'react-i18next'
import { MapPin } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { ForSelectItem } from '@/features/for-select/types'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateSiteSectionProps {
  control: Control<RequestCreateFormValues>
  /**
   * The Sede hydrated from a picked operator — the operator half of the
   * Sede <-> Operatore link the FORM owns (`useRequestSiteOperatorLink`,
   * spec 0097 rev-2 D-7). This section only reports the Sede it takes and
   * shows the one that link fills in.
   */
  autoFilledSite: ForSelectItem | null
  onSiteItemChange: (item: ForSelectItem | null) => void
}

/**
 * "Sede operativa" as its own card, immediately above the Team it scopes
 * (user directive 2026-09-10). It used to be the third field of
 * "Attribuzione": the Sede IS attribution, but what the operator does with it
 * is pick the site whose people the team slots then list, so it now sits with
 * them in the side column instead of a column away.
 *
 * Mounted only for an actor holding `operational-sites.viewAny` — the same
 * ability the store endpoint enforces server-side — a decision the FORM owns
 * (`canPickSite`), not this card.
 *
 * Strings stay under the `attribution.*` i18n namespace they were written in:
 * the field is the same field, moved, and duplicating the keys would leave two
 * copies of one label free to drift apart. No description on the card: one
 * field under a title needs no second line, and the scoping it produces is
 * already explained where it BITES, under the team slots.
 */
export function RequestCreateSiteSection({
  control,
  autoFilledSite,
  onSiteItemChange,
}: RequestCreateSiteSectionProps) {
  const { t } = useTranslation()
  const siteQuickCreate = useQuickCreateAction(OPERATIONAL_SITES_FOR_SELECT_RESOURCE)

  return (
    <FormSection
      icon={MapPin}
      title={t('requestManagement.form.create.attribution.operationalSite')}
      className="min-w-0"
    >
      <FormField
        control={control}
        name="operational_site_id"
        render={({ field }) => (
          <FormItem>
            <FormControl>
              <AsyncPaginatedSelect
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                onItemChange={onSiteItemChange}
                selectedItem={siteQuickCreate.selectedItemFor(field.value) ?? autoFilledSite}
                action={siteQuickCreate.renderAction((ref) => field.onChange(ref.id))}
                labels={{
                  placeholder: t('requestManagement.form.create.attribution.selectPlaceholder'),
                  empty: t('requestManagement.form.create.attribution.selectEmpty'),
                  error: t('requestManagement.form.create.attribution.selectError'),
                  clearLabel: t('common.clear'),
                  retry: t('common.retry'),
                  searchPlaceholder: t('requestManagement.form.create.attribution.operationalSiteSearch'),
                  triggerLabel: t('requestManagement.form.create.attribution.operationalSite'),
                }}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}
