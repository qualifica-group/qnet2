import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { GraduationCap, Pencil } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { CategorySettingsEditor } from '@/integrations/formalab/components/category-settings-editor'
import type { FormalabCategorySettings } from '@/integrations/formalab/types'
import { useFormalabCategorySettings } from '@/integrations/formalab/use-formalab-category-settings'

/**
 * The "Formalab" section of the product category detail (spec 0423): whether
 * the branch is linked to Formalab and which work order field each Formalab
 * field is written into.
 */
export function FormalabCategorySection({ categoryId }: { categoryId: number }) {
  const { t } = useTranslation('formalab')
  const { data, isLoading, isError } = useFormalabCategorySettings(categoryId)
  const [editing, setEditing] = useState(false)

  const action =
    data?.can_manage && !editing ? (
      <Button type="button" size="xs" variant="outline" onClick={() => setEditing(true)}>
        <Pencil aria-hidden="true" />
        {t('category.edit')}
      </Button>
    ) : null

  return (
    <RecordSection title={t('category.title')} icon={<GraduationCap />} action={action} full>
      {isLoading ? <Skeleton className="h-16" aria-busy="true" /> : null}
      {isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('category.loadError')}
        </p>
      ) : null}
      {data && editing ? (
        <CategorySettingsEditor categoryId={categoryId} settings={data} onClose={() => setEditing(false)} />
      ) : null}
      {data && !editing ? <CategorySettingsOverview settings={data} /> : null}
    </RecordSection>
  )
}

function CategorySettingsOverview({ settings }: { settings: FormalabCategorySettings }) {
  const { t } = useTranslation('formalab')
  const attributeNames = new Map(settings.attributes.map((attribute) => [attribute.code, attribute.name]))

  return (
    <div className="flex flex-col gap-3 text-sm">
      <div className="flex flex-col gap-0.5">
        <span>
          <span className="text-muted-foreground">{t('category.enabled')}:</span>{' '}
          <span className="font-medium">{settings.enabled ? t('category.yes') : t('category.no')}</span>
        </span>
        {settings.enabled_source_category ? (
          <span className="text-xs text-muted-foreground">
            {t('category.inheritedFrom', { category: settings.enabled_source_category.name })}
          </span>
        ) : null}
      </div>

      <div className="flex flex-col gap-1">
        <span className="text-muted-foreground">{t('category.fieldMap')}</span>
        {settings.field_map_source_category ? (
          <span className="text-xs text-muted-foreground">
            {t('category.mapInheritedFrom', { category: settings.field_map_source_category.name })}
          </span>
        ) : null}
        {settings.field_map.length === 0 ? (
          <span className="text-xs text-muted-foreground">{t('category.noFieldMap')}</span>
        ) : (
          <dl className="grid grid-cols-1 gap-x-3 gap-y-1 text-xs sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
            {settings.field_map.map((pair) => (
              <div key={pair.formalab_field} className="contents">
                <dt className="text-muted-foreground">{t(`category.fields.${pair.formalab_field}`)}</dt>
                <dd className="min-w-0 truncate">
                  {attributeNames.get(pair.attribute_code) ?? pair.attribute_code}{' '}
                  <span className="text-muted-foreground">({pair.attribute_code})</span>
                </dd>
              </div>
            ))}
          </dl>
        )}
      </div>
    </div>
  )
}
