import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type {
  AttributeLayoutFormShape,
  LayoutBlob,
  LayoutFormMode,
} from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutPreviewPanelProps {
  blob: LayoutBlob
  attributes: EffectiveAttribute[]
  mode: LayoutFormMode
}

/**
 * Live preview (spec 0062 AC-011): renders the SAME `AttributeLayoutRenderer`
 * the runtime Product form/detail and Opportunity work-panel use, fed
 * directly by the configurator's in-editing `blob` — no debounce, no extra
 * state, every keystroke/drag re-renders it. Read-only: a throwaway local
 * `useForm` supplies the `control` the renderer requires; nothing here is
 * ever submitted.
 *
 * Hosted inside the category edit form's `bg-card` FormSection (ui-design.md
 * §1-bis rung 3): the `--muted` tint sets the preview apart from the card as
 * a distinct "output" tray, one visual language with the palette/section
 * boxes it sits alongside. The `ConfigSection`s the renderer produces stay
 * `bg-card` (frontmost rung) inside it — a raised block floating on the
 * tinted tray, never a repeated rung.
 */
export function AttributeLayoutPreviewPanel({ blob, attributes, mode }: AttributeLayoutPreviewPanelProps) {
  const { t } = useTranslation('attributeLayout')
  const form = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: {} } })

  return (
    <div className="rounded-lg border bg-muted/40 p-3">
      <h4 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t('configurator.preview.title')}
      </h4>
      <p className="mt-0.5 mb-3 text-xs text-muted-foreground">{t('configurator.preview.description')}</p>
      <Form {...form}>
        <form>
          <AttributeLayoutRenderer
            layout={blob}
            attributes={attributes}
            control={form.control}
            mode={mode}
            readOnly
          />
        </form>
      </Form>
    </div>
  )
}
