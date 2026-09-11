import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import {
  AccessTabContent,
  AddressesTabContent,
  ContactsTabContent,
  CredentialsTabContent,
  IdentityTabContent,
} from '@/features/users/user-form-account-tabs'
import { useAssignmentFieldsVisibility } from '@/features/users/user-assignment'
import { UserAssignmentSection } from '@/features/users/user-form-assignment-section'
import { ContractDataTabContent } from '@/features/users/user-form-contract-data-tab'
import { ContractTabContent, ProfileTabContent } from '@/features/users/user-form-employment-tabs'
import { UserFormHeader } from '@/features/users/user-form-header'
import { UserFormSummary } from '@/features/users/user-form-summary'
import { useUserForm } from '@/features/users/use-user-form'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserDetail } from '@/features/users/types'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the Opportunità and Gestione Richieste screens do: the same id
 * serves the footer actions, so both copies of the button submit this form
 * without either of them nesting the other.
 */
const USER_FORM_ID = 'user-form'

interface UserFormBodyProps {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
  onAvatarChange?: () => void
}

/**
 * The user create/edit form UI (spec 0015), rebuilt as the TWIN of the
 * Opportunità form (user directive 2026-09-11: "rifatti al modulo
 * opportunità"). Not a resemblance: the layout primitives are literally the
 * same objects (`@/components/record-form` — `RECORD_HEADER_CLASS`,
 * `PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/`MAIN_COLUMN_CLASS`, `SummaryRow`,
 * `RecordFormActions`), so neither screen can drift apart with a later edit to
 * the other.
 *
 * Same skeleton as that form:
 *  - `@container` + `bg-surface`, sticky identity bar carrying the live pills
 *    and the save/cancel actions, repeated at the foot;
 *  - two columns at `@4xl` — the read-only side column FIRST in the DOM
 *    (narrow containers read it before the long form), reordered to the right;
 *  - side column = the live assignment verdict on top, then the recap;
 *  - main column = who the account IS first (anagrafica, credentials, roles),
 *    then the ASSIGNMENT configuration (competence + Sedi: the two halves of
 *    what makes this person reachable by a record, user directive
 *    2026-09-11), then what merely describes them.
 *
 * The assignment block sits right after "Ruoli e accessi" and before the
 * employment ones on purpose: it reads as the second half of "what this person
 * is allowed and able to receive", not as a term of their contract. Its
 * verdict stays visible from the sticky bar and the sticky side column
 * wherever the operator has scrolled to.
 *
 * Each section keeps its own visibility gate, so a section whose fields are
 * all hidden simply is not rendered. Every field is wrapped in `MetaField`
 * (spec 0004): hidden fields are absent, non-editable fields render
 * disabled/read-only, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. All
 * non-render logic lives in `useUserForm`.
 */
export function UserFormBody({ mode, onSuccess, onCancel, onAvatarChange }: UserFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const assignmentFields = useAssignmentFieldsVisibility()
  // The form's own scroll container: what a refused save scrolls, and the
  // boundary that keeps it from scrolling another owner form's blocks.
  const containerRef = useRef<HTMLDivElement>(null)
  const {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    revalidateSignal,
    blockedSection,
    selectedRoleItems,
    selectedCompanyItem,
    selectedPrimaryOperationalSiteItem,
    selectedRemoteOperationalSiteItems,
    knownProductLines,
    selectedReportsToItem,
    onSubmit,
    setPendingAvatar,
    handleAvatarUpload,
    handleAvatarRemove,
    canUploadAvatar,
    canRemoveAvatar,
    personalDataFieldPermission,
  } = useUserForm({ mode, onSuccess, onAvatarChange })

  // The blocks are all on screen, but the offending one can be far above the
  // save button: a refused save brings it back under the user's eyes.
  useRevealBlockedSection(revalidateSignal, blockedSection, containerRef)

  // The identity card's data is still loading/failed (edit mode only): show a
  // single skeleton/retry in its place, and hold off on contacts/addresses
  // (their buffers are not seeded yet either) instead of rendering empty rows.
  // `isFetching` (not just `isPending`) so a reopen with a stale cached card
  // waits for the on-open refetch before mounting: the card's inner RHF seeds
  // its defaults once at mount, so it must mount only from fresh server values.
  const isProfileLoading = isEdit && (profileQuery.isPending || profileQuery.isFetching)
  const isProfileError = isEdit && profileQuery.isError

  // Section visibility, read from the same authorization context `MetaField`
  // uses: a section is only worth rendering if at least one of its fields is
  // visible. `MetaField` still gates each field individually — this only
  // decides whether the surrounding section is shown at all. Identity has no
  // permission-gated field of its own, so it is always shown.
  const credentialsVisible =
    fieldPermission('email').visible || fieldPermission('password').visible
  const accessVisible = fieldPermission('roles').visible
  const profileVisible =
    fieldPermission('employment.is_manager').visible ||
    fieldPermission('employment.job_description').visible ||
    fieldPermission('employment.reports_to_id').visible
  const contractVisible =
    fieldPermission('employment.relationship_type').visible ||
    fieldPermission('employment.company_id').visible
  const contractDataVisible =
    fieldPermission('employment.qualification_type').visible ||
    fieldPermission('employment.hired_at').visible ||
    fieldPermission('employment.terminated_at').visible ||
    fieldPermission('employment.standard_daily_minutes').visible ||
    fieldPermission('employment.break_daily_minutes').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible

  // Contacts/addresses live in the buffered personal-data draft, seeded only
  // once the identity card has loaded — so they are gated on the profile query
  // too, not just field visibility.
  const contactsRenderable = !isProfileLoading && !isProfileError && contactsVisible
  const addressesRenderable = !isProfileLoading && !isProfileError && addressesVisible

  const { isSubmitting } = form.formState

  return (
    <div ref={containerRef} className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <UserFormHeader
          control={form.control}
          isEdit={isEdit}
          formId={USER_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads the verdict before
              the form, reordered to the right on two columns. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <UserFormSummary
              control={form.control}
              selectedPrimaryOperationalSiteItem={selectedPrimaryOperationalSiteItem}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={USER_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <div {...anagraphicSectionProps('card')}>
                <IdentityTabContent
                  mode={mode}
                  profileName={profileName}
                  isLoading={isProfileLoading}
                  isError={isProfileError}
                  onRetry={() => profileQuery.refetch()}
                  profileDraft={profileDraft}
                  setProfileDraft={setProfileDraft}
                  revalidateSignal={revalidateSignal}
                  personalDataFieldPermission={personalDataFieldPermission}
                  setPendingAvatar={setPendingAvatar}
                  handleAvatarUpload={handleAvatarUpload}
                  handleAvatarRemove={handleAvatarRemove}
                  canUploadAvatar={canUploadAvatar}
                  canRemoveAvatar={canRemoveAvatar}
                />
              </div>

              {credentialsVisible && <CredentialsTabContent control={form.control} isEdit={isEdit} />}

              {accessVisible && (
                <AccessTabContent control={form.control} selectedRoleItems={selectedRoleItems} />
              )}

              {assignmentFields.any && (
                <UserAssignmentSection
                  control={form.control}
                  knownProductLines={knownProductLines}
                  selectedPrimaryOperationalSiteItem={selectedPrimaryOperationalSiteItem}
                  selectedRemoteOperationalSiteItems={selectedRemoteOperationalSiteItems}
                />
              )}

              {profileVisible && (
                <ProfileTabContent
                  control={form.control}
                  selectedReportsToItem={selectedReportsToItem}
                />
              )}

              {contractVisible && (
                <ContractTabContent control={form.control} selectedCompanyItem={selectedCompanyItem} />
              )}

              {contractDataVisible && <ContractDataTabContent control={form.control} />}

              {contactsRenderable && (
                <div {...anagraphicSectionProps('contacts')}>
                  <ContactsTabContent
                    profileDraft={profileDraft}
                    setProfileDraft={setProfileDraft}
                    personalDataFieldPermission={personalDataFieldPermission}
                    createMode={!isEdit}
                  />
                </div>
              )}

              {addressesRenderable && (
                <div {...anagraphicSectionProps('addresses')}>
                  <AddressesTabContent
                    profileDraft={profileDraft}
                    setProfileDraft={setProfileDraft}
                    personalDataFieldPermission={personalDataFieldPermission}
                    createMode={!isEdit}
                  />
                </div>
              )}

              <CustomFieldsSection resource="users" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={USER_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('users.form.save')}
                submittingLabel={t('users.form.saving')}
                cancel={{ label: t('users.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
