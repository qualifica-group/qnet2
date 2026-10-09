import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { PersonalDataChildCards } from '@/features/personal-data/personal-data-record-cards'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { RegistryCreateSections } from '@/features/registries/registry-create-sections'
import { RegistryFormHeader } from '@/features/registries/registry-form-header'
import { RegistryGeneralNotesRow } from '@/features/registries/registry-general-notes'
import { REGISTRY_CARD_FIELD } from '@/features/registries/registry-record'
import { useRegistryDraftInlineEdit } from '@/features/registries/use-registry-draft-inline-edit'
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
  isSupplierPreset?: boolean
}

/**
 * The anagrafica create form UI, aligned with the in-place detail (spec 0200,
 * spec 0195 D-8 applied to Anagrafiche): the same `RecordCanvas`, the record
 * card with its identity band, KPI strip and sections, every row closed until
 * clicked — the anagraphic card too (`RegistryCreateSections`) — and the same
 * Contatti/Indirizzi cards in the side column, here kept in the draft until
 * Salva and laid out with their quick fields ready to fill (user 2026-10-07),
 * the phone one required. There is
 * no edit form: the detail edits a persisted anagrafica in place.
 *
 * The duplicate warning heads the side column, the general notes (spec 0207)
 * follow it. The warning refuses nothing: the save goes through either way.
 */
export function RegistryFormBody({ onSuccess, onCancel, isSupplierPreset = false }: RegistryFormBodyProps) {
  const { t } = useTranslation()
  // The form's own scroll scope: what a refused save reveals, never another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(emptyPersonalDataDraft)
  const { form, customFieldErrorPaths } = useRegistryForm({ mode: CREATE_MODE, isSupplierPreset })
  const { inline, cardSignal } = useRegistryDraftInlineEdit(form, profileDraft, setProfileDraft)
  const submit = useRegistryFormSubmit({
    form,
    mode: CREATE_MODE,
    profileDraft,
    customFieldErrorPaths,
    onSuccess,
    // An incomplete card refuses the save: open its row, where the card marks the fields.
    onRefused: (section) => {
      if (section === 'card') {
        inline.start(REGISTRY_CARD_FIELD)
      }
    },
  })
  const fieldPermission = submit.personalDataFieldPermission

  useRevealBlockedSection(submit.revalidateSignal, submit.blockedSection, containerRef)
  const { matches: duplicateMatches } = useIdentityDuplicateCheck({ enabled: true, profileDraft })
  const { isSubmitting } = form.formState
  const generalNotes = useWatch({ control: form.control, name: 'general_notes' })

  const side = (
    <>
      <IdentityDuplicateWarning matches={duplicateMatches} />
      <RegistryGeneralNotesRow notes={generalNotes || null} control={form.control} inline={inline} />
      <PersonalDataChildCards
        draft={profileDraft}
        contactsTitle={t('registries.form.sections.contacts.title')}
        addressesTitle={t('registries.form.sections.addresses.title')}
        showSiteType
        fieldPermission={fieldPermission}
        onChange={(patch) => setProfileDraft({ ...profileDraft, ...patch })}
        createMode
        requiredCreateTypes={REQUIRED_CREATE_CONTACT_TYPES}
        contactsBoxProps={anagraphicSectionProps('contacts')}
        addressesBoxProps={anagraphicSectionProps('addresses')}
      />
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
                  draft={inline}
                  card={{
                    draft: profileDraft,
                    setDraft: setProfileDraft,
                    revalidateSignal: submit.revalidateSignal + cardSignal,
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
