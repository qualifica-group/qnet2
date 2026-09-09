import { useRef } from 'react'
import { IdCard, MapPin, Phone } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
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

interface ReferentFormBodyProps {
  mode: ReferentFormMode
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * The referent create/edit form UI (spec 0016), laid out as a SINGLE screen:
 * anagraphic card, referent details, contacts, addresses and custom fields are
 * stacked one under the other in a single column, with no macro tabs (user
 * directive 2026-09-07: creating a Segnalatore must take no tab switching).
 * Each block keeps its own `FormSection` heading and its own visibility gate,
 * so an actor who cannot see contacts simply gets no contacts block. Contacts
 * and addresses open in the shared dialog and persist immediately when the card
 * already exists (`cardOwnerRef`); in create mode both offer their inline quick
 * fields instead. Every field is wrapped in `MetaField` (spec 0004); all
 * non-render logic lives in `useReferentForm`. `<CustomFieldsSection>` (spec
 * 0021) mounts the resource's admin-defined custom fields last, with zero
 * referents-specific rendering/validation logic.
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

  return (
    <div ref={containerRef} className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
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

          <IdentityDuplicateWarning matches={duplicateMatches} />

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
