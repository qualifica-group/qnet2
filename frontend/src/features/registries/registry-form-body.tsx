import { useState } from 'react'
import { IdCard, MapPin, Phone, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS, TabErrorDot } from '@/components/form-tab-strip'
import { FormSection } from '@/components/form-section'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { DetailsTabContent } from '@/features/registries/registry-form-details-tab'
import { useRegistryForm } from '@/features/registries/use-registry-form'
import type { RegistryDetail, RegistryFormMode } from '@/features/registries/types'

interface RegistryFormBodyProps {
  mode: RegistryFormMode
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/** One entry in the tab strip: its value, label, icon, and gating flags. */
/** Tab selected when the form opens. */
const DEFAULT_TAB = 'account'

interface RegistryFormTab {
  value: string
  label: string
  Icon: LucideIcon
  visible: boolean
  hasError: boolean
}

/**
 * The registry create/edit form UI, aligned with `ReferentForm`'s look (spec
 * 0020): two macro tabs — Account (the anagraphic card + registry details:
 * relations + business fields) and Contact info (contacts + addresses, with
 * the "site type" select enabled) — over the shared premium tab strip. A
 * macro tab is shown only when at least one of its sections is visible and
 * carries the error dot when any of them is invalid. Contacts/addresses open
 * in the shared dialog and persist immediately when the card already exists
 * (`cardOwnerRef`). Every field is wrapped in `MetaField` (spec 0004); all
 * non-render logic lives in `useRegistryForm`.
 */
export function RegistryFormBody({ mode, onSuccess, onCancel }: RegistryFormBodyProps) {
  const { t } = useTranslation()
  // Controlled, so the tab strip can hand the selection over to its select
  // fallback when the tabs no longer fit.
  const [activeTab, setActiveTab] = useState(DEFAULT_TAB)
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    serverError,
    profileDraft,
    setProfileDraft,
    profileValid,
    selectedItems,
    onSubmit,
    personalDataFieldPermission,
  } = useRegistryForm({ mode, onSuccess })

  // Section visibility, read from the same authorization context `MetaField`
  // uses (the anagraphic card has no permission-gated field, so Account is
  // always shown).
  const detailsVisible =
    fieldPermission('source_id').visible ||
    fieldPermission('sector_ids').visible ||
    fieldPermission('referent_ids').visible ||
    fieldPermission('manager_ids').visible ||
    fieldPermission('supervisor_id').visible ||
    fieldPermission('commercial_id').visible ||
    fieldPermission('reporter_id').visible ||
    fieldPermission('vat_group').visible ||
    fieldPermission('is_supplier').visible ||
    fieldPermission('is_qualified_supplier').visible ||
    fieldPermission('agreement_status').visible ||
    fieldPermission('agreement_notes').visible ||
    fieldPermission('size_class').visible ||
    fieldPermission('employee_count').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible
  const contactInfoVisible = contactsVisible || addressesVisible

  // `is_qualified_supplier` only makes sense while the registry is a supplier
  // (spec 0020): the form hides the toggle otherwise.
  const isSupplier = form.watch('is_supplier')

  // Account error = the mandatory card is invalid (its buffer lives outside RHF)
  // or any registry-detail field carries a validation error.
  const errors = form.formState.errors
  const accountHasError = !profileValid || Object.keys(errors).length > 0

  // Contacts/addresses persist immediately once the card exists; otherwise they
  // stay buffered until the form is saved (parity with the Referents module).
  const persistence = cardOwnerRef(profileDraft)
  const tabHasErrorsLabel = t('registries.form.tabs.tabHasErrors')

  const tabItems: RegistryFormTab[] = [
    { value: 'account', label: t('registries.form.tabs.account'), Icon: IdCard, visible: true, hasError: accountHasError },
    { value: 'contactInfo', label: t('registries.form.tabs.contactInfo'), Icon: Phone, visible: contactInfoVisible, hasError: false },
  ]

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-1 flex-col gap-4">
            <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
              {tabItems
                .filter((tab) => tab.visible)
                .map(({ value, label, Icon, hasError }) => (
                  <TabsTrigger key={value} value={value} className={FORM_TAB_TRIGGER_CLASS}>
                    <Icon aria-hidden="true" />
                    {label}
                    {hasError && <TabErrorDot label={tabHasErrorsLabel} />}
                  </TabsTrigger>
                ))}
            </FormTabStrip>

            <TabsContent value="account" className="flex flex-col gap-4">
              <FormSection
                icon={IdCard}
                title={t('registries.form.sections.identity.title')}
                description={t('registries.form.sections.identity.description')}
              >
                <PersonalDataCardForm
                  value={profileDraft}
                  onChange={setProfileDraft}
                  fieldPermission={personalDataFieldPermission}
                />
              </FormSection>

              {detailsVisible && (
                <DetailsTabContent
                  control={form.control}
                  selectedItems={selectedItems}
                  isSupplier={isSupplier}
                />
              )}

              <CustomFieldsSection resource="registries" control={form.control} />
            </TabsContent>

            {contactInfoVisible && (
              <TabsContent value="contactInfo" className="flex flex-col gap-4">
                {contactsVisible && (
                  <FormSection
                    icon={Phone}
                    title={t('registries.form.sections.contacts.title')}
                    description={t('registries.form.sections.contacts.description')}
                    aside={<Badge variant="secondary">{profileDraft.contacts.length}</Badge>}
                  >
                    <ContactsManager
                      value={profileDraft.contacts}
                      onChange={(contacts) => setProfileDraft({ ...profileDraft, contacts })}
                      fieldPermission={personalDataFieldPermission}
                      showHeader={false}
                      persistence={persistence}
                      createMode={mode.type === 'create'}
                    />
                  </FormSection>
                )}

                {addressesVisible && (
                  <FormSection
                    icon={MapPin}
                    title={t('registries.form.sections.addresses.title')}
                    description={t('registries.form.sections.addresses.description')}
                    aside={<Badge variant="secondary">{profileDraft.addresses.length}</Badge>}
                  >
                    <AddressesManager
                      value={profileDraft.addresses}
                      onChange={(addresses) => setProfileDraft({ ...profileDraft, addresses })}
                      fieldPermission={personalDataFieldPermission}
                      showHeader={false}
                      persistence={persistence}
                      showSiteType
                      createMode={mode.type === 'create'}
                    />
                  </FormSection>
                )}
              </TabsContent>
            )}
          </Tabs>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('registries.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('registries.form.saving') : t('registries.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
