import { useTranslation } from 'react-i18next'
import { Info, Layers, PencilLine } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import type { LayoutFormScope } from '@/features/attributes/attribute-layout-types'

interface AttributeLayoutScopeNoticeProps {
  scope: LayoutFormScope
  /** The scope has its own persisted row (an override of the shared layout). */
  hasOverride: boolean
  /** The draft is this scope's own layout rather than a read-only inherited preview. */
  isCustomizing: boolean
  disabled: boolean
  onCustomize: () => void
  onResetToShared: () => void
}

/**
 * The inheritance banner of the layout configurator (spec 0062, D3 revised):
 * on a per-mode scope it states whether that mode is showing the SHARED
 * layout (read-only, with the action that turns it into an override) or its
 * own customized one (with the action that drops the override). Renders
 * nothing on the shared scope itself, which has nothing to inherit.
 */
export function AttributeLayoutScopeNotice({
  scope,
  hasOverride,
  isCustomizing,
  disabled,
  onCustomize,
  onResetToShared,
}: AttributeLayoutScopeNoticeProps) {
  const { t } = useTranslation('attributeLayout')
  const confirm = useConfirm()

  if (scope === 'all') {
    return null
  }

  const modeName = t(`section.mode.${scope}`)

  const handleReset = async () => {
    const confirmed = await confirm({
      title: t('section.resetTitle'),
      description: t('section.resetDescription', { mode: modeName }),
      confirmLabel: t('section.resetConfirm'),
      tone: 'destructive',
    })
    if (confirmed) onResetToShared()
  }

  const { icon: Icon, message, action } = isCustomizing
    ? {
        icon: hasOverride ? PencilLine : Info,
        message: hasOverride ? t('section.overrideActive', { mode: modeName }) : t('section.overrideUnsaved'),
        action: hasOverride ? (
          <Button variant="outline" size="sm" className="bg-card" disabled={disabled} onClick={() => void handleReset()}>
            {t('section.resetToShared')}
          </Button>
        ) : null,
      }
    : {
        icon: Layers,
        message: t('section.inheritsShared'),
        action: (
          <Button variant="secondary" size="sm" disabled={disabled} onClick={onCustomize}>
            {t('section.customize')}
          </Button>
        ),
      }

  return (
    <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2">
      <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
      <p className="flex-1 text-xs text-muted-foreground">{message}</p>
      {action}
    </div>
  )
}
