import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AvatarUpload } from '@/components/avatar-upload'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { PERSONAL_DATA_CARD_FIELD } from '@/features/personal-data/personal-data-identity-record-section'
import { PersonalDataChildCards } from '@/features/personal-data/personal-data-record-cards'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { usePersonalDataDraftInlineEdit } from '@/features/personal-data/use-personal-data-draft-inline-edit'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'
import { UserCreateSections } from '@/features/users/user-create-sections'
import { UserFormHeader } from '@/features/users/user-form-header'
import { useUserDraftValues } from '@/features/users/use-user-draft-values'
import { useUserForm } from '@/features/users/use-user-form'
import { useUserFormSubmit } from '@/features/users/use-user-form-submit'
import type { UserDetail } from '@/features/users/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const USER_FORM_ID = 'user-form'

/** Stable create mode: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface UserFormBodyProps {
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
}

/**
 * The user create form UI, aligned with the in-place detail (spec 0222, spec
 * 0195 D-8 applied to Utenti): the same `RecordCanvas`, the record card with
 * its identity band, KPI strip and sections, every row closed until clicked —
 * the anagraphic card too (`UserCreateSections`) — and the Contatti/Indirizzi
 * cards in the side column, kept in the draft until Salva (the twin of
 * `ReferentFormBody`). The profile photo is chosen here and uploaded once the
 * user exists. There is no edit form: the detail edits a persisted user in
 * place.
 */
export function UserFormBody({ onSuccess, onCancel }: UserFormBodyProps) {
  const { t } = useTranslation()
  // The form's own scroll scope: what a refused save reveals, never another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(emptyPersonalDataDraft)
  const [pendingAvatar, setPendingAvatar] = useState<File | null>(null)
  const { form, customFieldErrorPaths } = useUserForm({ mode: CREATE_MODE })
  const { inline, cardSignal } = usePersonalDataDraftInlineEdit(form, profileDraft, setProfileDraft)
  const submit = useUserFormSubmit({
    form,
    mode: CREATE_MODE,
    profileDraft,
    customFieldErrorPaths,
    pendingAvatar,
    onSuccess,
    // An incomplete card refuses the save: open its row, where the card marks the fields.
    onRefused: (section) => {
      if (section === 'card') {
        inline.start(PERSONAL_DATA_CARD_FIELD)
      }
    },
  })
  const values = useUserDraftValues(form.control)
  const fieldPermission = submit.personalDataFieldPermission

  useRevealBlockedSection(submit.revalidateSignal, submit.blockedSection, containerRef)
  const { isSubmitting } = form.formState

  const side = (
    <PersonalDataChildCards
      draft={profileDraft}
      contactsTitle={t('users.form.sections.contacts.title')}
      addressesTitle={t('users.form.sections.addresses.title')}
      fieldPermission={fieldPermission}
      onChange={(patch) => setProfileDraft({ ...profileDraft, ...patch })}
      createMode
      showWorkFlag
      contactsBoxProps={anagraphicSectionProps('contacts')}
      addressesBoxProps={anagraphicSectionProps('addresses')}
    />
  )

  return (
    <div ref={containerRef} className="contents">
      <Form {...form}>
        {/* `display: contents`: this native `<form>` only scopes the HTML submit
            boundary, it must not become an extra box around the canvas. */}
        <form id={USER_FORM_ID} onSubmit={form.handleSubmit(submit.onSubmit)} className="contents" noValidate>
          <RecordCanvas>
            <RecordFormBody side={side}>
              <RecordCard>
                <UserFormHeader
                  values={values}
                  profileDraft={profileDraft}
                  formId={USER_FORM_ID}
                  isSubmitting={isSubmitting}
                  submitError={submit.serverError}
                  onCancel={onCancel}
                />
                <UserCreateSections
                  values={values}
                  form={form}
                  draft={inline}
                  card={{
                    draft: profileDraft,
                    setDraft: setProfileDraft,
                    revalidateSignal: submit.revalidateSignal + cardSignal,
                    fieldPermission,
                  }}
                  avatar={
                    <AvatarUpload
                      mode="deferred"
                      label={t('users.form.avatarLabel')}
                      name={values.email}
                      onFileSelected={setPendingAvatar}
                    />
                  }
                />
              </RecordCard>

              {/* The same actions the identity band carries, repeated where the
                  form ends: the operator finishes typing far from the top. */}
              <RecordFormActions
                formId={USER_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('users.form.save')}
                submittingLabel={t('users.form.saving')}
                cancel={{ label: t('users.form.cancel'), onCancel }}
              />
            </RecordFormBody>
          </RecordCanvas>
        </form>
      </Form>
    </div>
  )
}
