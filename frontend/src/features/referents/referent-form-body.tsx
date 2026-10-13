import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { PERSONAL_DATA_CARD_FIELD } from '@/features/personal-data/personal-data-identity-record-section'
import { PersonalDataChildCards } from '@/features/personal-data/personal-data-record-cards'
import type { QuickContactType } from '@/features/personal-data/quick-contacts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { usePersonalDataDraftInlineEdit } from '@/features/personal-data/use-personal-data-draft-inline-edit'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { ReferentCreateSections } from '@/features/referents/referent-create-sections'
import { ReferentFormHeader } from '@/features/referents/referent-form-header'
import { useReferentForm } from '@/features/referents/use-referent-form'
import { useReferentFormSubmit } from '@/features/referents/use-referent-form-submit'
import type { ReferentDetail } from '@/features/referents/types'

/**
 * A referent must be reachable by phone at creation (user directive
 * 2026-07-31): the quick field carries the asterisk, `useReferentFormSubmit`
 * blocks the save, and StoreReferentRequest enforces it server-side.
 */
const REQUIRED_CREATE_CONTACT_TYPES: QuickContactType[] = ['phone']

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const REFERENT_FORM_ID = 'referent-form'

/** Stable create mode: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ReferentFormBodyProps {
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * The referente create form UI, aligned with the in-place detail (spec 0216,
 * spec 0195 D-8 applied to Referenti): the same `RecordCanvas`, the record card
 * with its identity band, KPI strip and sections, every row closed until
 * clicked — the anagraphic card too (`ReferentCreateSections`) — and the
 * Contatti/Indirizzi cards in the side column, kept in the draft until Salva
 * with their quick fields ready to fill, the phone one required (the twin of
 * `RegistryFormBody`). There is no edit form: the detail edits a persisted
 * referente in place.
 *
 * The duplicate warning heads the side column. It refuses nothing: the save
 * goes through either way.
 */
export function ReferentFormBody({ onSuccess, onCancel }: ReferentFormBodyProps) {
  const { t } = useTranslation()
  // The form's own scroll scope: what a refused save reveals, never another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(emptyPersonalDataDraft)
  const { form, customFieldErrorPaths } = useReferentForm({ mode: CREATE_MODE })
  const { inline, cardSignal } = usePersonalDataDraftInlineEdit(form, profileDraft, setProfileDraft)
  const submit = useReferentFormSubmit({
    form,
    mode: CREATE_MODE,
    profileDraft,
    customFieldErrorPaths,
    onSuccess,
    // An incomplete card refuses the save: open its row, where the card marks the fields.
    onRefused: (section) => {
      if (section === 'card') {
        inline.start(PERSONAL_DATA_CARD_FIELD)
      }
    },
  })
  const fieldPermission = submit.personalDataFieldPermission

  useRevealBlockedSection(submit.revalidateSignal, submit.blockedSection, containerRef)
  const { matches: duplicateMatches } = useIdentityDuplicateCheck({ enabled: true, profileDraft })
  const { isSubmitting } = form.formState

  const side = (
    <>
      <IdentityDuplicateWarning matches={duplicateMatches} />
      <PersonalDataChildCards
        draft={profileDraft}
        contactsTitle={t('referents.form.sections.contacts.title')}
        addressesTitle={t('referents.form.sections.addresses.title')}
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
        <form id={REFERENT_FORM_ID} onSubmit={form.handleSubmit(submit.onSubmit)} className="contents" noValidate>
          <RecordCanvas>
            <RecordFormBody side={side}>
              <RecordCard>
                <ReferentFormHeader
                  control={form.control}
                  profileDraft={profileDraft}
                  formId={REFERENT_FORM_ID}
                  isSubmitting={isSubmitting}
                  submitError={submit.serverError}
                  onCancel={onCancel}
                />
                <ReferentCreateSections
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
                formId={REFERENT_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('referents.form.save')}
                submittingLabel={t('referents.form.saving')}
                cancel={{ label: t('referents.form.cancel'), onCancel }}
              />
            </RecordFormBody>
          </RecordCanvas>
        </form>
      </Form>
    </div>
  )
}
