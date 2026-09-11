import { useRef } from 'react'
import { IdCard, MapPin, Phone } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Form } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { DetailsTabContent } from '@/features/referents/referent-form-details-tab'
import { ReferentFormHeader } from '@/features/referents/referent-form-header'
import { ReferentFormSummary } from '@/features/referents/referent-form-summary'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'
import { useReferentForm } from '@/features/referents/use-referent-form'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { ReferentDetail, ReferentFormMode } from '@/features/referents/types'

/**
 * A referent must be reachable by phone at creation (user directive
 * 2026-07-31): the quick field carries the asterisk, `useReferentForm` blocks
 * the save, and StoreReferentRequest enforces it server-side.
 */
const REQUIRED_CREATE_CONTACT_TYPES: QuickContactType[] = ['phone']

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the Opportunità and Gestione Richieste screens do: the same id
 * serves the footer actions, so both copies of the button submit this form
 * without either of them nesting the other.
 */
const REFERENT_FORM_ID = 'referent-form'

interface ReferentFormBodyProps {
  mode: ReferentFormMode
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * The referente create/edit form UI (spec 0016), rebuilt as the TWIN of the
 * Opportunità form and of its own sibling `RegistryFormBody` (user directive
 * 2026-09-11). Not a resemblance: the layout primitives are literally the same
 * objects (`@/components/record-form`), so none of the record forms can drift
 * apart with a later edit to another.
 *
 * The **duplicate warning moved to the side column**, for the same reason it
 * did on the anagrafica: it used to sit under the custom fields, i.e.
 * off-screen exactly while the operator was typing the name that triggers it.
 * It still refuses nothing — the save goes through either way.
 *
 * Each block keeps its own visibility gate; every field is wrapped in
 * `MetaField` (spec 0004); all non-render logic lives in `useReferentForm`.
 */
export function ReferentFormBody({ mode, onSuccess, onCancel }: ReferentFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  // The form's own scroll container: what a refused save scrolls, and the
  // boundary that keeps it from scrolling another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const {
    form,
    serverError,
    profileDraft,
    setProfileDraft,
    revalidateSignal,
    blockedSection,
    selectedReferentTypeItem,
    selectedUserItem,
    onSubmit,
    personalDataFieldPermission,
  } = useReferentForm({ mode, onSuccess })

  // The blocks are all on screen, but the offending one can be far above the
  // save button: a refused save brings it back under the user's eyes.
  useRevealBlockedSection(revalidateSignal, blockedSection, containerRef)
  const { matches: duplicateMatches } = useIdentityDuplicateCheck({
    enabled: mode.type === 'create',
    profileDraft,
  })

  // Section visibility, read from the same authorization context `MetaField`
  // uses (the anagraphic card has no permission-gated field, so it is always
  // shown).
  const detailsVisible =
    fieldPermission('referent_type_id').visible ||
    fieldPermission('user_id').visible ||
    fieldPermission('contact_scope').visible ||
    fieldPermission('notes').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible

  // Contacts/addresses persist immediately once the card exists; otherwise they
  // stay buffered until the form is saved (parity with the Users module).
  const persistence = cardOwnerRef(profileDraft)
  const { isSubmitting } = form.formState

  return (
    <div ref={containerRef} className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <ReferentFormHeader
          control={form.control}
          isEdit={mode.type === 'edit'}
          formId={REFERENT_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads the duplicate warning
              before the form, reordered to the right on two columns. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <IdentityDuplicateWarning matches={duplicateMatches} />
            <ReferentFormSummary
              control={form.control}
              selectedReferentTypeItem={selectedReferentTypeItem}
              selectedUserItem={selectedUserItem}
              profileDraft={profileDraft}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={REFERENT_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <div {...anagraphicSectionProps('card')}>
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
              </div>

              {detailsVisible && (
                <DetailsTabContent
                  control={form.control}
                  selectedReferentTypeItem={selectedReferentTypeItem}
                  selectedUserItem={selectedUserItem}
                />
              )}

              {contactsVisible && (
                <div {...anagraphicSectionProps('contacts')}>
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
                </div>
              )}

              {addressesVisible && (
                <div {...anagraphicSectionProps('addresses')}>
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
                </div>
              )}

              <CustomFieldsSection resource="referents" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={REFERENT_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('referents.form.save')}
                submittingLabel={t('referents.form.saving')}
                cancel={{ label: t('referents.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
