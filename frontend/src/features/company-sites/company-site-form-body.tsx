import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { CompanySiteFormHeader } from '@/features/company-sites/company-site-form-header'
import { CompanySiteFormSummary } from '@/features/company-sites/company-site-form-summary'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProfileTabContent } from '@/features/company-sites/company-site-profile-tab'
import { SettingsTabContent } from '@/features/company-sites/company-site-settings-tab'
import { BanksTabContent } from '@/features/company-sites/company-site-banks-tab'
import { useCompanySiteForm } from '@/features/company-sites/use-company-site-form'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { CompanySiteDetail } from '@/features/company-sites/types'

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as every other record form does: the same id serves the footer
 * actions, so both copies of the button submit this form without either of them
 * nesting the other.
 */
const COMPANY_SITE_FORM_ID = 'company-site-form'

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

  // Rendered twice on purpose — in the sticky bar and at the foot of the form —
  // the same way Save is: this screen is long, and setting the default must not
  // require scrolling back up. A function, not a shared element: two React
  // trees cannot share one node.
  const setDefaultButton = () =>
    canSetDefault ? (
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
    ) : null

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <CompanySiteFormHeader
          isEdit={mode.type === 'edit'}
          isDefault={mode.type === 'edit' && mode.companySite.is_default}
          formId={COMPANY_SITE_FORM_ID}
          isSubmitting={form.formState.isSubmitting}
          submitError={serverError}
          leadingActions={setDefaultButton()}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          <aside className={SIDE_COLUMN_CLASS}>
            <CompanySiteFormSummary
              control={form.control}
              selectedCompanyItem={selectedCompanyItem}
              banksDraft={banksDraft}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={COMPANY_SITE_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
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

              {/* The same actions the identity bar carries, repeated where the
                  form ends: the operator finishes typing far from the sticky bar. */}
              <RecordFormActions
                formId={COMPANY_SITE_FORM_ID}
                isSubmitting={form.formState.isSubmitting}
                submitLabel={t('companySites.form.save')}
                submittingLabel={t('companySites.form.saving')}
                cancel={{ label: t('companySites.form.cancel'), onCancel }}
                leadingActions={setDefaultButton()}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
