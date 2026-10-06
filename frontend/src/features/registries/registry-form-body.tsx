import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Mail, MapPin } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard, RecordSection } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { RegistryCreateSections } from '@/features/registries/registry-create-sections'
import { RegistryFormHeader } from '@/features/registries/registry-form-header'
import { useRegistryForm } from '@/features/registries/use-registry-form'
import { useRegistryFormSubmit } from '@/features/registries/use-registry-form-submit'
import type { RegistryDetail } from '@/features/registries/types'

/**
 * An anagrafica must be reachable by phone at creation (user directive
 * 2026-09-07, same rule the referenti carry): the quick field carries the
 * asterisk, `useRegistryFormSubmit` blocks the save, and StoreRegistryRequest
 * enforces it server-side.
 */
const REQUIRED_CREATE_CONTACT_TYPES: QuickContactType[] = ['phone']

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const REGISTRY_FORM_ID = 'registry-form'

/** Stable create mode: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface RegistryFormBodyProps {
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * The anagrafica create form UI, a replica of the anagrafica detail (spec
 * 0200, spec 0195 D-8 applied to Anagrafiche): the same `RecordCanvas`, the
 * record card with its identity band, KPI strip and sections, every row closed
 * until clicked (`RegistryCreateSections`), the card's contacts and addresses
 * in the side column — here with their quick fields, phone required. There is
 * no edit form: the detail edits a persisted anagrafica in place.
 *
 * The duplicate warning heads the side column, read while the name that
 * triggers it is typed. It refuses nothing: the save goes through either way.
 */
export function RegistryFormBody({ onSuccess, onCancel }: RegistryFormBodyProps) {
  const { t } = useTranslation()
  // The form's own scroll scope: what a refused save reveals, never another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(emptyPersonalDataDraft)
  const { form, customFieldErrorPaths } = useRegistryForm({ mode: CREATE_MODE })
  const submit = useRegistryFormSubmit({ form, mode: CREATE_MODE, profileDraft, customFieldErrorPaths, onSuccess })
  const draft = useDraftInlineEdit(form)
  const { personalDataFieldPermission: fieldPermission } = submit

  useRevealBlockedSection(submit.revalidateSignal, submit.blockedSection, containerRef)
  const { matches: duplicateMatches } = useIdentityDuplicateCheck({ enabled: true, profileDraft })
  const { isSubmitting } = form.formState

  const side = (
    <>
      <IdentityDuplicateWarning matches={duplicateMatches} />
      {fieldPermission('personal_data.contacts').visible ? (
        <div {...anagraphicSectionProps('contacts')}>
          <RecordCard className="p-4">
            <RecordSection
              title={t('registries.form.sections.contacts.title')}
              icon={<Mail />}
              action={<Badge variant="secondary">{profileDraft.contacts.length}</Badge>}
            >
              <ContactsManager
                value={profileDraft.contacts}
                onChange={(contacts) => setProfileDraft({ ...profileDraft, contacts })}
                fieldPermission={fieldPermission}
                showHeader={false}
                createMode
                requiredCreateTypes={REQUIRED_CREATE_CONTACT_TYPES}
              />
            </RecordSection>
          </RecordCard>
        </div>
      ) : null}
      {fieldPermission('personal_data.addresses').visible ? (
        <div {...anagraphicSectionProps('addresses')}>
          <RecordCard className="p-4">
            <RecordSection
              title={t('registries.form.sections.addresses.title')}
              icon={<MapPin />}
              action={<Badge variant="secondary">{profileDraft.addresses.length}</Badge>}
            >
              <AddressesManager
                value={profileDraft.addresses}
                onChange={(addresses) => setProfileDraft({ ...profileDraft, addresses })}
                fieldPermission={fieldPermission}
                showHeader={false}
                showSiteType
                createMode
              />
            </RecordSection>
          </RecordCard>
        </div>
      ) : null}
    </>
  )

  return (
    <div ref={containerRef} className="contents">
      <Form {...form}>
        {/* `display: contents`: this native `<form>` only scopes the HTML submit
            boundary, it must not become an extra box around the canvas. */}
        <form id={REGISTRY_FORM_ID} onSubmit={form.handleSubmit(submit.onSubmit)} className="contents" noValidate>
          <RecordCanvas>
            <RecordBody side={side}>
              <RecordCard>
                <RegistryFormHeader
                  control={form.control}
                  profileDraft={profileDraft}
                  formId={REGISTRY_FORM_ID}
                  isSubmitting={isSubmitting}
                  submitError={submit.serverError}
                  onCancel={onCancel}
                />
                <RegistryCreateSections
                  form={form}
                  draft={draft}
                  card={{
                    draft: profileDraft,
                    setDraft: setProfileDraft,
                    revalidateSignal: submit.revalidateSignal,
                    fieldPermission,
                  }}
                />
              </RecordCard>

              {/* The same actions the identity band carries, repeated where the
                  form ends: the operator finishes typing far from the top. */}
              <RecordFormActions
                formId={REGISTRY_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('registries.form.save')}
                submittingLabel={t('registries.form.saving')}
                cancel={{ label: t('registries.form.cancel'), onCancel }}
              />
            </RecordBody>
          </RecordCanvas>
        </form>
      </Form>
    </div>
  )
}
