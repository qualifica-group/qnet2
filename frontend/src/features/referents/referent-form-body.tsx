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
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import type { BlockedSection } from '@/features/personal-data/personal-data-issues'
import { useRevealBlockedSection } from '@/features/personal-data/use-reveal-blocked-section'
import { DetailsTabContent } from '@/features/referents/referent-form-details-tab'
import { ReferentDuplicateWarning } from '@/features/referents/referent-duplicate-warning'
import { useReferentDuplicateCheck } from '@/features/referents/use-referent-duplicate-check'
import { useReferentForm } from '@/features/referents/use-referent-form'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { ReferentDetail, ReferentFormMode } from '@/features/referents/types'

/**
 * A referent must be reachable by phone at creation (user directive
 * 2026-07-31): the quick field carries the asterisk, `useReferentForm` blocks
 * the save, and StoreReferentRequest enforces it server-side.
 */
const REQUIRED_CREATE_CONTACT_TYPES: QuickContactType[] = ['phone']

interface ReferentFormBodyProps {
  mode: ReferentFormMode
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/** One entry in the tab strip: its value, label, icon, and gating flags. */
/** Tab selected when the form opens. */
const DEFAULT_TAB = 'account'

/** Which tab owns each anagraphic block, for the reveal on a refused save. */
const TAB_OF_SECTION: Record<BlockedSection, string> = {
  card: 'account',
  contacts: 'contactInfo',
  addresses: 'contactInfo',
}

interface ReferentFormTab {
  value: string
  label: string
  Icon: LucideIcon
  visible: boolean
  hasError: boolean
}

/**
 * The referent create/edit form UI, aligned with `UserForm`'s look (spec 0016):
 * two macro tabs — Account (the anagraphic card + referent details) and Contact
 * info (contacts + addresses) — over the shared premium tab strip. A macro tab is
 * shown only when at least one of its sections is visible and carries the error
 * dot when any of them is invalid. Contacts/addresses open in the shared dialog
 * and persist immediately when the card already exists (`cardOwnerRef`). Every
 * field is wrapped in `MetaField` (spec 0004); all non-render logic lives in
 * `useReferentForm`. `<CustomFieldsSection>` (spec 0021) mounts the resource's
 * admin-defined custom fields on the Account tab, with zero referents-specific
 * rendering/validation logic.
 */
export function ReferentFormBody({ mode, onSuccess, onCancel }: ReferentFormBodyProps) {
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
    revalidateSignal,
    blockedSection,
    selectedReferentTypeItem,
    selectedUserItem,
    onSubmit,
    personalDataFieldPermission,
  } = useReferentForm({ mode, onSuccess })

  // A highlighted field on a hidden tab is no highlight at all: a refused save
  // brings its own block on screen.
  useRevealBlockedSection(revalidateSignal, blockedSection, TAB_OF_SECTION, setActiveTab)
  const { matches: duplicateMatches } = useReferentDuplicateCheck({ mode, profileDraft })

  // Section visibility, read from the same authorization context `MetaField`
  // uses (the anagraphic card has no permission-gated field, so Account is
  // always shown).
  const detailsVisible =
    fieldPermission('referent_type_id').visible ||
    fieldPermission('user_id').visible ||
    fieldPermission('contact_scope').visible ||
    fieldPermission('notes').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible
  const contactInfoVisible = contactsVisible || addressesVisible

  // Account error = the mandatory card is invalid (its buffer lives outside RHF)
  // or any referent-detail field carries a validation error.
  const errors = form.formState.errors
  const accountHasError =
    !profileValid ||
    Boolean(errors.referent_type_id || errors.user_id || errors.contact_scope || errors.notes)

  // Contacts/addresses persist immediately once the card exists; otherwise they
  // stay buffered until the form is saved (parity with the Users module).
  const persistence = cardOwnerRef(profileDraft)
  const tabHasErrorsLabel = t('referents.form.tabs.tabHasErrors')

  const tabItems: ReferentFormTab[] = [
    { value: 'account', label: t('referents.form.tabs.account'), Icon: IdCard, visible: true, hasError: accountHasError },
    { value: 'contactInfo', label: t('referents.form.tabs.contactInfo'), Icon: Phone, visible: contactInfoVisible, hasError: false },
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
                title={t('referents.form.sections.identity.title')}
                description={t('referents.form.sections.identity.description')}
              >
                <PersonalDataCardForm
                  value={profileDraft}
                  onChange={setProfileDraft}
                  fieldPermission={personalDataFieldPermission}
                  revalidateSignal={revalidateSignal}
                />
              </FormSection>

              {detailsVisible && (
                <DetailsTabContent
                  control={form.control}
                  selectedReferentTypeItem={selectedReferentTypeItem}
                  selectedUserItem={selectedUserItem}
                />
              )}

              <CustomFieldsSection resource="referents" control={form.control} />
            </TabsContent>

            {contactInfoVisible && (
              <TabsContent value="contactInfo" className="flex flex-col gap-4">
                {contactsVisible && (
                  <FormSection
                    icon={Phone}
                    title={t('referents.form.sections.contacts.title')}
                    description={t('referents.form.sections.contacts.description')}
                    aside={<Badge variant="secondary">{profileDraft.contacts.length}</Badge>}
                  >
                    <ContactsManager
                      value={profileDraft.contacts}
                      onChange={(contacts) => setProfileDraft({ ...profileDraft, contacts })}
                      fieldPermission={personalDataFieldPermission}
                      showHeader={false}
                      persistence={persistence}
                      createMode={mode.type === 'create'}
                      requiredCreateTypes={REQUIRED_CREATE_CONTACT_TYPES}
                    />
                  </FormSection>
                )}

                {addressesVisible && (
                  <FormSection
                    icon={MapPin}
                    title={t('referents.form.sections.addresses.title')}
                    description={t('referents.form.sections.addresses.description')}
                    aside={<Badge variant="secondary">{profileDraft.addresses.length}</Badge>}
                  >
                    <AddressesManager
                      value={profileDraft.addresses}
                      onChange={(addresses) => setProfileDraft({ ...profileDraft, addresses })}
                      fieldPermission={personalDataFieldPermission}
                      showHeader={false}
                      persistence={persistence}
                      createMode={mode.type === 'create'}
                    />
                  </FormSection>
                )}
              </TabsContent>
            )}
          </Tabs>

          <ReferentDuplicateWarning matches={duplicateMatches} />

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
              {t('referents.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('referents.form.saving') : t('referents.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
