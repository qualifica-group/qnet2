import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import {
  CompanySiteDraftChildSections,
  CompanySiteIdentitySection,
} from '@/features/company-sites/company-site-card-sections'
import {
  CompanySiteBanksSection,
  CompanySiteCompanySection,
  CompanySiteGeneralSection,
} from '@/features/company-sites/company-site-rows'
import { useCompanySiteDraftInlineEdit } from '@/features/company-sites/use-company-site-draft-inline-edit'
import { useCompanySiteForm } from '@/features/company-sites/use-company-site-form'
import { useCompanySiteFormSubmit } from '@/features/company-sites/use-company-site-form-submit'
import type { BankDraft, CompanySiteDetail } from '@/features/company-sites/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const COMPANY_SITE_FORM_ID = 'company-site-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

/** Stable empty id list: a fresh `[]` per render would break the label hook's memo. */
const NO_IDS: number[] = []

interface CompanySiteFormBodyProps {
  onSuccess: (companySite: CompanySiteDetail) => void
  onCancel: () => void
}

/**
 * The company-site create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same sections and rows, closed until clicked
 * (Fatto/Ripristina, nothing saved per row), the anagraphic card and the banks
 * included; contacts and address in their quick-fill layout, the logo only
 * previewed (uploaded right after the save). Salva validates and creates the
 * whole draft.
 */
export function CompanySiteFormBody({ onSuccess, onCancel }: CompanySiteFormBodyProps) {
  const { t } = useTranslation()
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(() => emptyPersonalDataDraft('company'))
  const [banksDraft, setBanksDraft] = useState<BankDraft[]>([])
  const [pendingLogo, setPendingLogo] = useState<File | null>(null)
  const { form, customFieldErrorPaths } = useCompanySiteForm({ mode: CREATE_MODE })
  const { inline, cardSignal } = useCompanySiteDraftInlineEdit(
    form,
    profileDraft,
    setProfileDraft,
    banksDraft,
    setBanksDraft,
  )
  const submit = useCompanySiteFormSubmit({
    form,
    mode: CREATE_MODE,
    profileDraft,
    banksDraft,
    pendingLogo,
    customFieldErrorPaths,
    onSuccess,
  })
  const [name, companyId, customFields] = useWatch({
    control: form.control,
    name: ['name', 'company_id', 'custom_fields'],
  })
  const labels = useForSelectLabels({
    resource: COMPANIES_FOR_SELECT_RESOURCE,
    ids: companyId === null ? NO_IDS : [companyId],
  })
  const company = companyId === null ? null : { id: companyId, label: labels.get(companyId)?.label ?? '…' }
  const heading = name.trim() || t('companySites.form.createTitle')
  const card = {
    draft: profileDraft,
    setDraft: setProfileDraft,
    revalidateSignal: submit.revalidateSignal + cardSignal,
    fieldPermission: submit.personalDataFieldPermission,
  }

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={COMPANY_SITE_FORM_ID}
        onSubmit={form.handleSubmit(submit.onSubmit)}
        title={heading}
        subtitle={t('companySites.form.createSubtitle')}
        media={<DetailMonogram name={heading} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={submit.serverError}
        onCancel={onCancel}
        labels={{
          save: t('companySites.form.save'),
          saving: t('companySites.form.saving'),
          cancel: t('companySites.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <CompanySiteGeneralSection
            form={form}
            inline={inline}
            logo={{ mode: 'deferred', onFileSelected: setPendingLogo }}
          />
          <CompanySiteIdentitySection identity={profileDraft} buffer={card} inline={inline} />
          <CompanySiteCompanySection form={form} inline={inline} company={company} />
          <CustomFieldRecordSections
            resource="company-sites"
            values={(customFields ?? {}) as Record<string, CustomFieldValue>}
            inline={inline}
            control={form.control}
          />
          <CompanySiteBanksSection inline={inline} banks={banksDraft} onChange={setBanksDraft} />
          <CompanySiteDraftChildSections
            draft={profileDraft}
            setDraft={setProfileDraft}
            fieldPermission={submit.personalDataFieldPermission}
          />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
