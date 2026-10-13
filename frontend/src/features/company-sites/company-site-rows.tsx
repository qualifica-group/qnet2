import { useTranslation } from 'react-i18next'
import { Building2, Landmark, Store } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { AvatarUpload } from '@/components/avatar-upload'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { BanksCountBadge, BanksManager } from '@/features/company-sites/banks-manager'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'
import type { BankDraft } from '@/features/company-sites/types'

/** Key of the banks' single in-place row. Not an RHF path: the banks are a buffered draft, so this only names the open editor. */
export const BANKS_FIELD = 'banks'

/** The logo control: stored at once on a persisted site, only previewed (uploaded after the save) on a create draft. */
export type CompanySiteLogo =
  | {
      mode: 'immediate'
      url: string | null
      onUpload: (file: File) => Promise<void>
      onRemove: () => Promise<void>
      canUpload: boolean
      canRemove: boolean
    }
  | { mode: 'deferred'; onFileSelected: (file: File | null) => void }

interface CompanySiteRowsProps {
  form: UseFormReturn<CompanySiteFormValues>
  inline: InlineEdit
}

/** "Generale": the logo, the site's own name and notes. */
export function CompanySiteGeneralSection({ form, inline, logo }: CompanySiteRowsProps & { logo: CompanySiteLogo }) {
  const { t } = useTranslation()
  const name = useWatch({ control: form.control, name: 'name' })
  const label = t('companySites.form.logoLabel')

  return (
    <RecordSection title={t('companySites.form.sections.general.title')} icon={<Store />}>
      {logo.mode === 'immediate' ? (
        <AvatarUpload
          mode="immediate"
          label={label}
          name={name}
          avatarUrl={logo.url}
          onUpload={logo.onUpload}
          onRemove={logo.onRemove}
          canUpload={logo.canUpload}
          canRemove={logo.canRemove}
        />
      ) : (
        <AvatarUpload mode="deferred" label={label} name={name} onFileSelected={logo.onFileSelected} />
      )}
      <RecordFieldList>
        <TextInlineRow<CompanySiteFormValues> name="name" label={t('companySites.form.name')} inline={inline} />
        <TextInlineRow<CompanySiteFormValues>
          name="notes"
          label={t('companySites.form.notes')}
          inline={inline}
          multiline
        />
      </RecordFieldList>
    </RecordSection>
  )
}

interface CompanySiteCompanySectionProps extends CompanySiteRowsProps {
  /** What the closed row shows: the persisted company on a detail, the one resolved from the draft's id on create. */
  company: { id: number; label: string } | null
}

/** "Società": the owning company, picked from the for-select list. */
export function CompanySiteCompanySection({ form, inline, company }: CompanySiteCompanySectionProps) {
  const { t } = useTranslation()
  const label = t('companySites.form.company')

  return (
    <RecordSection title={t('companySites.form.sections.company.title')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="company_id"
          label={label}
          inline={inline}
          editor={
            <RelationSelectField
              control={form.control}
              name="company_id"
              metaKey="company_id"
              label={label}
              resource={COMPANIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('companySites.form.companySearch')}
              selected={company ? { id: company.id, name: company.label } : null}
              placeholder={t('companySites.form.companyPlaceholder')}
              emptyLabel={t('companySites.form.companyEmpty')}
              errorLabel={t('companySites.form.companyError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
            />
          }
        >
          {company ? (
            <RecordLink domain="companies" id={company.id}>
              {company.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface CompanySiteBanksSectionProps {
  inline: InlineEdit
  banks: BankDraft[]
  onChange: (next: BankDraft[]) => void
}

/**
 * "Banche": the inline 1-to-N collection as ONE in-place row opening on the
 * banks manager. The list is one buffered object, saved whole as the
 * authoritative `banks[]` (spec 0020).
 */
export function CompanySiteBanksSection({ inline, banks, onChange }: CompanySiteBanksSectionProps) {
  const { t } = useTranslation()
  const title = t('companySites.form.sections.banks.title')

  return (
    <RecordSection title={title} icon={<Landmark />} action={<BanksCountBadge count={banks.length} />} full>
      <RecordInlineField
        field={BANKS_FIELD}
        label={title}
        inline={inline}
        layout="block"
        editor={<BanksManager value={banks} onChange={onChange} />}
      >
        {banks.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {banks.map((bank) => (
              <li key={bank._key} className="flex flex-col gap-0.5 text-sm">
                <span className="font-medium text-foreground">{bank.name}</span>
                {bank.iban ? <span className="text-muted-foreground">{bank.iban}</span> : null}
              </li>
            ))}
          </ul>
        ) : (
          <DetailEmpty />
        )}
      </RecordInlineField>
    </RecordSection>
  )
}
