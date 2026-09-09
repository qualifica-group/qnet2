import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import {
  AccessTabContent,
  AddressesTabContent,
  ContactsTabContent,
  CredentialsTabContent,
  IdentityTabContent,
} from '@/features/users/user-form-account-tabs'
import { ContractDataTabContent } from '@/features/users/user-form-contract-data-tab'
import { ContractTabContent, ProfileTabContent } from '@/features/users/user-form-employment-tabs'
import { useUserForm } from '@/features/users/use-user-form'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserDetail } from '@/features/users/types'
import {
  anagraphicSectionProps,
  useRevealBlockedSection,
} from '@/features/personal-data/use-reveal-blocked-section'

interface UserFormBodyProps {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
  onAvatarChange?: () => void
}

/**
 * The user create/edit form UI (spec 0015), laid out as a SINGLE screen: the
 * `FormSection`s that used to be grouped under three macro tabs — identity,
 * credentials, access, then employment (profile, contract, contract data),
 * then contacts and addresses, and the custom fields last — are stacked one
 * under the other in a single column (user directive 2026-09-07, same move as
 * the Referents/Anagrafiche forms). Each section keeps its own visibility gate,
 * so a section whose fields are all hidden simply is not rendered. Every field
 * is wrapped in `MetaField` (spec 0004): hidden fields are absent, non-editable
 * fields render disabled/read-only, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. All
 * non-render logic lives in `useUserForm`; each section's content lives in a
 * sibling module (`user-form-account-tabs.tsx`, `user-form-employment-tabs.tsx`,
 * `user-form-contract-data-tab.tsx`) so this file stays within the size limits.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields, with zero users-specific rendering/validation logic.
 */
export function UserFormBody({ mode, onSuccess, onCancel, onAvatarChange }: UserFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
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
    fieldPermission('employment.product_lines').visible ||
    fieldPermission('employment.is_manager').visible ||
    fieldPermission('employment.job_description').visible ||
    fieldPermission('employment.reports_to_id').visible
  const contractVisible =
    fieldPermission('employment.relationship_type').visible ||
    fieldPermission('employment.company_id').visible ||
    fieldPermission('employment.primary_operational_site_id').visible ||
    fieldPermission('employment.remote_operational_site_ids').visible
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

  return (
    <div ref={containerRef} className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
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

          {profileVisible && (
            <ProfileTabContent
              control={form.control}
              knownProductLines={knownProductLines}
              selectedReportsToItem={selectedReportsToItem}
            />
          )}

          {contractVisible && (
            <ContractTabContent
              control={form.control}
              selectedCompanyItem={selectedCompanyItem}
              selectedPrimaryOperationalSiteItem={selectedPrimaryOperationalSiteItem}
              selectedRemoteOperationalSiteItems={selectedRemoteOperationalSiteItems}
            />
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
              {t('users.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('users.form.saving') : t('users.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
