import { useRef } from 'react'
import { IdCard, MapPin, Phone } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
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
import { DetailsTabContent } from '@/features/registries/registry-form-details-tab'
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

interface RegistryFormBodyProps {
  mode: RegistryFormMode
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * The registry create/edit form UI (spec 0020), laid out as a SINGLE screen
 * like its twin `ReferentForm` (user directive 2026-09-07): anagraphic card,
 * registry details (relations + business fields), contacts, addresses and
 * custom fields stacked one under the other in a single column, with no macro
 * tabs. Each block keeps its own `FormSection` heading and its own visibility
 * gate. Contacts/addresses open in the shared dialog and persist immediately
 * when the card already exists (`cardOwnerRef`), with the "site type" select
 * enabled on the addresses. Every field is wrapped in `MetaField` (spec 0004);
 * all non-render logic lives in `useRegistryForm`.
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
