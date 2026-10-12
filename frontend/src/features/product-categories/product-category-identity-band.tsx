import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChartNoAxesColumn, EyeOff, FolderTree, Power } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import type { ProductCategoryRecordValues } from '@/features/product-categories/product-category-record'

interface ProductCategoryIdentityBandProps {
  values: ProductCategoryRecordValues
  /** The title when the name is still blank (the create draft). */
  fallbackTitle?: string
  subtitle?: string
  legacyId?: number | null
  actions?: ReactNode
}

/**
 * Identity band of the category record, shared by the detail and the create
 * draft (spec 0229 D-4): monogram, name, where the category sits and the pills
 * answering what governs it. No edit action of its own — the record edits in
 * place, and the create form hands in its Annulla/Salva.
 */
export function ProductCategoryIdentityBand({
  values,
  fallbackTitle,
  subtitle,
  legacyId,
  actions,
}: ProductCategoryIdentityBandProps) {
  const { t } = useTranslation()
  const title = values.name.trim() || fallbackTitle || values.name

  return (
    <RecordCardHeader
      media={<DetailMonogram name={title} icon={<FolderTree />} className="size-10 text-base [&>svg]:size-5" />}
      title={title}
      legacyId={legacyId}
      subtitle={subtitle}
      badges={
        <>
          {values.parent === null ? <Badge variant="outline">{t('productCategories.badges.root')}</Badge> : null}
          {/* A pure container is not otherwise visible without scrolling down
              to the rules, and it changes where the category may be used at all. */}
          {!values.is_selectable ? (
            <Badge variant="outline">
              <EyeOff aria-hidden="true" />
              {t('productCategories.badges.notSelectable')}
            </Badge>
          ) : null}
          {!values.is_active ? (
            <Badge variant="outline">
              <Power aria-hidden="true" />
              {t('productCategories.badges.notActive')}
            </Badge>
          ) : null}
          {values.effective_is_reportable ? (
            <Badge variant="outline">
              <ChartNoAxesColumn aria-hidden="true" />
              {t('productCategories.badges.reportable')}
            </Badge>
          ) : null}
        </>
      }
      actions={actions}
    />
  )
}
