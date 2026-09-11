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
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'
import { DetailsTabContent } from '@/features/registries/registry-form-details-tab'
import { RegistryFormHeader } from '@/features/registries/registry-form-header'
import { RegistryFormSummary } from '@/features/registries/registry-form-summary'
import { useRegistryForm } from '@/features/registries/use-registry-form'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { RegistryDetail, RegistryFormMode } from '@/features/registries/types'

/**
 * An anagrafica must be reachable by phone at creation (user directive
 * 2026-09-07, same rule the referenti carry): the quick field carries the
 * asterisk, `useRegistryForm` blocks the save, and StoreRegistryRequest
 * enforces it server-side.
 */
const REQUIRED_CREATE_CONTACT_TYPES: QuickContactType[] = ['phone']

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the Opportunità and Gestione Richieste screens do: the same id
 * serves the footer actions, so both copies of the button submit this form
 * without either of them nesting the other.
 */
const REGISTRY_FORM_ID = 'registry-form'

interface RegistryFormBodyProps {
  mode: RegistryFormMode
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * The anagrafica create/edit form UI (spec 0020), rebuilt as the TWIN of the
 * Opportunità form (user directive 2026-09-11). Not a resemblance: the layout
 * primitives are literally the same objects (`@/components/record-form` —
 * `RECORD_HEADER_CLASS`, `PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/
 * `MAIN_COLUMN_CLASS`, `SummaryRow`, `RecordFormActions`), so neither screen
 * can drift apart with a later edit to the other.
 *
 * The **duplicate warning moved to the side column**, and that is the point of
 * the move rather than a side effect: it used to sit at the very bottom, under
 * the custom fields, i.e. off-screen exactly while the operator was typing the
 * name that triggers it. In the sticky side column it is read where it is
 * useful. It still refuses nothing — the save goes through either way.
 *
 * Each block keeps its own visibility gate; every field is wrapped in
 * `MetaField` (spec 0004); all non-render logic lives in `useRegistryForm`.
 */
export function RegistryFormBody({ mode, onSuccess, onCancel }: RegistryFormBodyProps) {
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
    selectedItems,
    onSubmit,
    personalDataFieldPermission,
  } = useRegistryForm({ mode, onSuccess })

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

  // `is_qualified_supplier` only makes sense while the registry is a supplier
  // (spec 0020): the form hides the toggle otherwise.
  const isSupplier = form.watch('is_supplier')

  // Contacts/addresses persist immediately once the card exists; otherwise they
  // stay buffered until the form is saved (parity with the Referents module).
  const persistence = cardOwnerRef(profileDraft)
  const { isSubmitting } = form.formState

  return (
    <div ref={containerRef} className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <RegistryFormHeader
          control={form.control}
          isEdit={mode.type === 'edit'}
          formId={REGISTRY_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads the duplicate warning
              before the form, reordered to the right on two columns. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <IdentityDuplicateWarning matches={duplicateMatches} />
            <RegistryFormSummary control={form.control} selectedItems={selectedItems} />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={REGISTRY_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <div {...anagraphicSectionProps('card')}>
                <FormSection
                  icon={IdCard}
                  title={t('registries.form.sections.identity.title')}
                  description={t('registries.form.sections.identity.description')}
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
                  selectedItems={selectedItems}
                  isSupplier={isSupplier}
                />
              )}

              {contactsVisible && (
                <div {...anagraphicSectionProps('contacts')}>
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
                      requiredCreateTypes={REQUIRED_CREATE_CONTACT_TYPES}
                    />
                  </FormSection>
                </div>
              )}

              {addressesVisible && (
                <div {...anagraphicSectionProps('addresses')}>
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
                </div>
              )}

              <CustomFieldsSection resource="registries" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={REGISTRY_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('registries.form.save')}
                submittingLabel={t('registries.form.saving')}
                cancel={{ label: t('registries.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
