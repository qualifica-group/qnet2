import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Info, Layers, PencilLine } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import type { LayoutFormScope } from '@/features/attributes/attribute-layout-types'

interface AttributeLayoutScopeNoticeProps {
  scope: LayoutFormScope
  /** The scope has its own persisted row (an override of the shared layout). */
  hasOverride: boolean
  /** The draft is this scope's own layout rather than a read-only inherited preview. */
  isCustomizing: boolean
  /** Name of the ancestor category whose layout this one renders, null when it answers for itself (spec 0115). */
  inheritedFromCategory: string | null
  disabled: boolean
  onCustomize: () => void
  onResetToShared: () => void
}

/**
 * The inheritance banner of the layout configurator, on both axes.
 *
 * CATEGORY axis (spec 0115), which takes precedence when both apply: the
 * category has no layout of its own and renders an ancestor's, so the banner
 * names that ancestor — on every scope, the shared one included. Saying
 * "inherits the shared layout" there would name the wrong source.
 *
 * SCOPE axis (spec 0062, D3 revised): on a per-mode scope of a category that
 * DOES answer for itself, it states whether that mode shows the shared layout
 * (read-only, with the action that turns it into an override) or its own
 * customized one (with the action that drops the override).
 *
 * Renders nothing only when there is nothing to inherit on either axis.
 */
export function AttributeLayoutScopeNotice({
  scope,
  hasOverride,
  isCustomizing,
  inheritedFromCategory,
  disabled,
  onCustomize,
  onResetToShared,
}: AttributeLayoutScopeNoticeProps) {
  const { t } = useTranslation('attributeLayout')
  const confirm = useConfirm()

  const confirmReset = async (description: string) => {
    const confirmed = await confirm({
      title: t('section.resetTitle'),
      description,
      confirmLabel: t('section.resetConfirm'),
      tone: 'destructive',
    })
    if (confirmed) onResetToShared()
  }

  if (inheritedFromCategory !== null) {
    if (hasOverride) {
      return (
        <AttributeLayoutNoticeShell
          icon={PencilLine}
          message={t('section.categoryOverrideActive', { category: inheritedFromCategory })}
          action={
            <Button
              variant="outline"
              size="sm"
              className="bg-card"
              disabled={disabled}
              onClick={() => void confirmReset(t('section.resetCategoryDescription', { category: inheritedFromCategory }))}
            >
              {t('section.resetToInherited')}
            </Button>
          }
        />
      )
    }

    return (
      <AttributeLayoutNoticeShell
        icon={isCustomizing ? Info : Layers}
        message={
          isCustomizing
            ? t('section.overrideUnsaved')
            : t('section.inheritsCategory', { category: inheritedFromCategory })
        }
        action={
          isCustomizing ? null : (
            <Button variant="secondary" size="sm" disabled={disabled} onClick={onCustomize}>
              {t('section.customizeCategory')}
            </Button>
          )
        }
      />
    )
  }

  if (scope === 'all') {
    return null
  }

  const modeName = t(`section.mode.${scope}`)

  const handleReset = () => void confirmReset(t('section.resetDescription', { mode: modeName }))

  const { icon: Icon, message, action } = isCustomizing
    ? {
        icon: hasOverride ? PencilLine : Info,
        message: hasOverride ? t('section.overrideActive', { mode: modeName }) : t('section.overrideUnsaved'),
        action: hasOverride ? (
          <Button variant="outline" size="sm" className="bg-card" disabled={disabled} onClick={handleReset}>
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

  return <AttributeLayoutNoticeShell icon={Icon} message={message} action={action} />
}

interface AttributeLayoutNoticeShellProps {
  icon: LucideIcon
  message: string
  action: ReactNode
}

/** The banner's single presentation, shared by both inheritance axes. */
function AttributeLayoutNoticeShell({ icon: Icon, message, action }: AttributeLayoutNoticeShellProps) {
  return (
    <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2">
      <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
      <p className="flex-1 text-xs text-muted-foreground">{message}</p>
      {action}
    </div>
  )
}
