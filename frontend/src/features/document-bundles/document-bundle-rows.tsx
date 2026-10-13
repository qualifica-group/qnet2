import { useTranslation } from 'react-i18next'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { DocumentBundleFormValues } from '@/features/document-bundles/use-document-bundle-form'

interface DocumentBundleRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The bundle's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function DocumentBundleRows({ inline, canEdit }: DocumentBundleRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<DocumentBundleFormValues>
        name="name"
        label={t('documentBundles.form.name')}
        inline={inline}
        canEdit={canEdit}
      />
      <TextInlineRow<DocumentBundleFormValues>
        name="description"
        label={t('documentBundles.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <SwitchInlineRow<DocumentBundleFormValues>
        name="is_active"
        label={t('documentBundles.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
