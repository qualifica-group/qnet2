import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProfileTabContent } from '@/features/company-sites/company-site-profile-tab'
import { SettingsTabContent } from '@/features/company-sites/company-site-settings-tab'
import { BanksTabContent } from '@/features/company-sites/company-site-banks-tab'
import { useCompanySiteForm } from '@/features/company-sites/use-company-site-form'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { CompanySiteDetail } from '@/features/company-sites/types'

interface CompanySiteFormBodyProps {
  mode: CompanySiteFormMode
  onSuccess: (companySite: CompanySiteDetail) => void
  onCancel: () => void
  onSiteChange?: () => void
}

/**
 * The company-site create/edit form UI, laid out as a SINGLE screen (user
 * directive 2026-09-07, same move as the Referents/Anagrafiche/Utenti forms):
 * the profile blocks (identity, logo, contacts, address), the settings
 * (responsibles, progressives, read-only quotation ids), the inline banks
 * collection — where one bank can be flagged preferred — and the universal
 * custom fields (spec 0021) last, all stacked one under the other. Each block
 * keeps its own visibility gate. Every editable field is wrapped in `MetaField`
 * (spec 0004): hidden fields are absent, non-editable fields render disabled,
 * `required` comes from the resolved `ResourcePermissions`. All non-render
 * logic lives in `useCompanySiteForm`; each section's content lives in a
 * sibling module so this file stays within the size limits (engineering.md §6).
 */
export function CompanySiteFormBody({
  mode,
  onSuccess,
  onCancel,
  onSiteChange,
}: CompanySiteFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    serverError,
    profileDraft,
    setProfileDraft,
    revalidateSignal,
    personalDataFieldPermission,
    banksDraft,
    setBanksDraft,
    selectedCompanyItem,
    onSubmit,
    setPendingLogo,
    handleLogoUpload,
    handleLogoRemove,
    canUploadLogo,
    canRemoveLogo,
    canSetDefault,
    settingDefault,
    handleSetDefault,
  } = useCompanySiteForm({ mode, onSuccess, onSiteChange })

  const siteName = useWatch({ control: form.control, name: 'name' }) || ''

  // Section visibility, read from the same authorization context `MetaField`
  // uses: a section is only worth rendering if at least one of its fields is
  // visible. The profile blocks carry the mandatory name/email, so they are
  // always shown.
  const settingsVisible = fieldPermission('company_id').visible
  const banksPermission = fieldPermission('banks')
  const banksVisible = banksPermission.visible
  const banksReadOnly = banksPermission.disabled || !banksPermission.editable

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <ProfileTabContent
            mode={mode}
            control={form.control}
            siteName={siteName}
            profileDraft={profileDraft}
            setProfileDraft={setProfileDraft}
            revalidateSignal={revalidateSignal}
            personalDataFieldPermission={personalDataFieldPermission}
            onLogoFileSelected={setPendingLogo}
            onLogoUpload={handleLogoUpload}
            onLogoRemove={handleLogoRemove}
            canUploadLogo={canUploadLogo}
            canRemoveLogo={canRemoveLogo}
          />

          {settingsVisible && (
            <SettingsTabContent control={form.control} selectedCompanyItem={selectedCompanyItem} />
          )}

          {banksVisible && (
            <BanksTabContent
              banksDraft={banksDraft}
              setBanksDraft={setBanksDraft}
              readOnly={banksReadOnly}
            />
          )}

          <CustomFieldsSection resource="company-sites" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            {canSetDefault && (
              <Button
                type="button"
                variant="secondary"
                onClick={() => void handleSetDefault()}
                disabled={settingDefault || form.formState.isSubmitting}
              >
                {settingDefault
                  ? t('companySites.form.settingDefault')
                  : t('companySites.form.setDefault')}
              </Button>
            )}
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('companySites.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('companySites.form.saving')
                : t('companySites.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
