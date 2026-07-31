import { useState } from 'react'
import { Briefcase, IdCard, Phone, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS, TabErrorDot } from '@/components/form-tab-strip'
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

interface UserFormBodyProps {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
  onAvatarChange?: () => void
}

/** Tab selected when the form opens. */
const DEFAULT_TAB = 'account'

/** One entry in the tab strip: its value, label, icon, and gating flags. */
interface UserFormTab {
  value: string
  label: string
  Icon: LucideIcon
  visible: boolean
  hasError: boolean
}

/**
 * The user create/edit form UI, organized into three macro tabs that each group
 * several `FormSection`s (a scrollable panel): Account (identity, credentials,
 * access), Employment (profile, contract, contract data) and Contact info
 * (contacts, addresses). A macro tab is shown only when at least one of its
 * sections is visible, and carries the error dot when any of them has one. Every
 * field is wrapped in `MetaField` (spec 0004): hidden fields are absent,
 * non-editable fields render disabled/read-only, `required` comes from the
 * resolved `ResourcePermissions` — no hardcoded permission logic lives here. All
 * non-render logic lives in `useUserForm`; each section's content lives in a
 * sibling module (`user-form-account-tabs.tsx`, `user-form-employment-tabs.tsx`,
 * `user-form-contract-data-tab.tsx`) so this file stays within the size limits.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined custom
 * fields on the Account tab, with zero users-specific rendering/validation logic.
 */
export function UserFormBody({ mode, onSuccess, onCancel, onAvatarChange }: UserFormBodyProps) {
  const { t } = useTranslation()
  // Controlled, so the tab strip can hand the selection over to its select
  // fallback when the tabs no longer fit.
  const [activeTab, setActiveTab] = useState(DEFAULT_TAB)
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    profileValid,
    selectedRoleItems,
    selectedBusinessFunctionItem,
    selectedCompanyItem,
    selectedOperationalSiteItem,
    selectedReportsToItem,
    onSubmit,
    setPendingAvatar,
    handleAvatarUpload,
    handleAvatarRemove,
    canUploadAvatar,
    canRemoveAvatar,
    personalDataFieldPermission,
  } = useUserForm({ mode, onSuccess, onAvatarChange })

  // The identity card's data is still loading/failed (edit mode only): show a
  // single skeleton/retry in its place, and hold off on contacts/addresses
  // (their buffers are not seeded yet either) instead of rendering empty rows.
  // `isFetching` (not just `isPending`) so a reopen with a stale cached card
  // waits for the on-open refetch before mounting: the card's inner RHF seeds
  // its defaults once at mount, so it must mount only from fresh server values.
  const isProfileLoading = isEdit && (profileQuery.isPending || profileQuery.isFetching)
  const isProfileError = isEdit && profileQuery.isError

  // Whole-tab visibility, read from the same authorization context `MetaField`
  // uses: a tab is only worth rendering if at least one of its fields is
  // visible. `MetaField` still gates each field individually — this only
  // decides whether the surrounding tab is shown at all. Identity has no
  // permission-gated field of its own, so it is always shown.
  const credentialsVisible =
    fieldPermission('email').visible || fieldPermission('password').visible
  const accessVisible = fieldPermission('roles').visible
  const profileVisible =
    fieldPermission('employment.business_function_id').visible ||
    fieldPermission('employment.is_manager').visible ||
    fieldPermission('employment.job_description').visible ||
    fieldPermission('employment.reports_to_id').visible
  const contractVisible =
    fieldPermission('employment.relationship_type').visible ||
    fieldPermission('employment.company_id').visible ||
    fieldPermission('employment.operational_site_id').visible
  const contractDataVisible =
    fieldPermission('employment.qualification_type').visible ||
    fieldPermission('employment.hired_at').visible ||
    fieldPermission('employment.terminated_at').visible ||
    fieldPermission('employment.standard_daily_minutes').visible ||
    fieldPermission('employment.break_daily_minutes').visible
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible

  // Per-tab error indicator (AC-014): RHF field errors for the tab's own
  // fields, plus the buffered personal-data draft's own (schema-driven)
  // validity for Identity, since that buffer lives outside RHF.
  const errors = form.formState.errors
  const tabHasErrorsLabel = t('users.form.tabs.tabHasErrors')
  const identityHasError = !profileValid
  const credentialsHasError = Boolean(
    errors.email || errors.password || errors.password_confirmation,
  )
  const accessHasError = Boolean(errors.roles)
  const profileHasError = Boolean(
    errors.employment?.business_function_id ||
      errors.employment?.is_manager ||
      errors.employment?.job_description ||
      errors.employment?.reports_to_id,
  )
  const contractHasError = Boolean(
    errors.employment?.relationship_type ||
      errors.employment?.company_id ||
      errors.employment?.operational_site_id,
  )
  const contractDataHasError = Boolean(
    errors.employment?.qualification_type ||
      errors.employment?.hired_at ||
      errors.employment?.terminated_at ||
      errors.employment?.standard_daily_minutes ||
      errors.employment?.break_daily_minutes,
  )

  // Contacts/addresses live in the buffered personal-data draft, seeded only
  // once the identity card has loaded — so they are gated on the profile query
  // too, not just field visibility. Shared by both the trigger and its content.
  const contactsRenderable = !isProfileLoading && !isProfileError && contactsVisible
  const addressesRenderable = !isProfileLoading && !isProfileError && addressesVisible

  // Macro-tab visibility/error = OR over the sections each one groups (Account's
  // Identity section is always present, so Account is always shown).
  const employmentVisible = profileVisible || contractVisible || contractDataVisible
  const contactInfoVisible = contactsRenderable || addressesRenderable
  const accountHasError = identityHasError || credentialsHasError || accessHasError
  const employmentHasError = profileHasError || contractHasError || contractDataHasError

  const tabItems: UserFormTab[] = [
    { value: 'account', label: t('users.form.tabs.account'), Icon: IdCard, visible: true, hasError: accountHasError },
    { value: 'employment', label: t('users.form.tabs.employment'), Icon: Briefcase, visible: employmentVisible, hasError: employmentHasError },
    { value: 'contactInfo', label: t('users.form.tabs.contactInfo'), Icon: Phone, visible: contactInfoVisible, hasError: false },
  ]

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-1 flex-col gap-4">
            <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
              {tabItems
                .filter((tab) => tab.visible)
                .map(({ value, label, Icon, hasError }) => (
                  <TabsTrigger key={value} value={value} className={FORM_TAB_TRIGGER_CLASS}>
                    <Icon aria-hidden="true" />
                    {label}
                    {hasError && <TabErrorDot label={tabHasErrorsLabel} />}
                  </TabsTrigger>
                ))}
            </FormTabStrip>

            <TabsContent value="account" className="flex flex-col gap-4">
              <IdentityTabContent
                mode={mode}
                profileName={profileName}
                isLoading={isProfileLoading}
                isError={isProfileError}
                onRetry={() => profileQuery.refetch()}
                profileDraft={profileDraft}
                setProfileDraft={setProfileDraft}
                personalDataFieldPermission={personalDataFieldPermission}
                setPendingAvatar={setPendingAvatar}
                handleAvatarUpload={handleAvatarUpload}
                handleAvatarRemove={handleAvatarRemove}
                canUploadAvatar={canUploadAvatar}
                canRemoveAvatar={canRemoveAvatar}
              />
              {credentialsVisible && (
                <CredentialsTabContent control={form.control} isEdit={isEdit} />
              )}
              {accessVisible && (
                <AccessTabContent control={form.control} selectedRoleItems={selectedRoleItems} />
              )}
              <CustomFieldsSection resource="users" control={form.control} />
            </TabsContent>

            {employmentVisible && (
              <TabsContent value="employment" className="flex flex-col gap-4">
                {profileVisible && (
                  <ProfileTabContent
                    control={form.control}
                    selectedBusinessFunctionItem={selectedBusinessFunctionItem}
                    selectedReportsToItem={selectedReportsToItem}
                  />
                )}
                {contractVisible && (
                  <ContractTabContent
                    control={form.control}
                    selectedCompanyItem={selectedCompanyItem}
                    selectedOperationalSiteItem={selectedOperationalSiteItem}
                  />
                )}
                {contractDataVisible && <ContractDataTabContent control={form.control} />}
              </TabsContent>
            )}

            {contactInfoVisible && (
              <TabsContent value="contactInfo" className="flex flex-col gap-4">
                {contactsRenderable && (
                  <ContactsTabContent
                    profileDraft={profileDraft}
                    setProfileDraft={setProfileDraft}
                    personalDataFieldPermission={personalDataFieldPermission}
                    createMode={!isEdit}
                  />
                )}
                {addressesRenderable && (
                  <AddressesTabContent
                    profileDraft={profileDraft}
                    setProfileDraft={setProfileDraft}
                    personalDataFieldPermission={personalDataFieldPermission}
                    createMode={!isEdit}
                  />
                )}
              </TabsContent>
            )}
          </Tabs>

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
