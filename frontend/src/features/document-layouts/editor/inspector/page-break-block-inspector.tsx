import { useTranslation } from 'react-i18next'

/** `page_break` has no properties (spec 0069 `config_schema`: `{ id, type: 'page_break' }`); this is a description-only inspector. */
export function PageBreakBlockInspector() {
  const { t } = useTranslation()
  return <p className="text-xs text-muted-foreground">{t('documentLayouts.editor.pageBreak.description')}</p>
}
