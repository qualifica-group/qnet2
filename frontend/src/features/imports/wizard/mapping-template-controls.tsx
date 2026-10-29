import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { LayoutTemplate, Trash2, Wand2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useAuth } from '@/features/auth/use-auth'
import { useDeleteMappingTemplate, useMappingTemplates } from '@/features/imports/wizard/use-mapping-templates'
import type { ImportMappingTemplate } from '@/features/imports/wizard/types'

/** Server-side max length for a saved mapping template's name (spec 0035). */
const TEMPLATE_NAME_MAX_LENGTH = 100

interface MatchingTemplateBannerProps {
  templateName: string
  onApply: () => void
}

/**
 * "Structure recognized" banner (AC-009): shown only when the run's
 * server-computed `matching_template` is non-null. Applying pre-fills the
 * mapping form but never submits it — the values stay fully editable.
 */
export function MatchingTemplateBanner({ templateName, onApply }: MatchingTemplateBannerProps) {
  const { t } = useTranslation('importWizard')
  return (
    <div
      role="status"
      className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2.5 text-sm text-foreground"
    >
      <div className="flex min-w-0 items-center gap-2">
        <Wand2 className="size-4 shrink-0 text-primary" aria-hidden="true" />
        <span className="truncate">{t('mapping.templates.matchBanner', { name: templateName })}</span>
      </div>
      <Button type="button" size="sm" variant="secondary" onClick={onApply} className="shrink-0">
        {t('mapping.templates.apply')}
      </Button>
    </div>
  )
}

interface SaveAsTemplateToggleProps {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  name: string
  onNameChange: (name: string) => void
}

/** "Save as template" footer control (name required only once checked). */
export function SaveAsTemplateToggle({ checked, onCheckedChange, name, onNameChange }: SaveAsTemplateToggleProps) {
  const { t } = useTranslation('importWizard')
  return (
    <div className="flex flex-col gap-2 rounded-lg border bg-muted/30 px-3 py-2.5 sm:flex-row sm:items-center sm:gap-3">
      <label className="flex items-center gap-2 text-sm font-medium">
        <Checkbox
          checked={checked}
          onCheckedChange={(value) => onCheckedChange(value === true)}
        />
        {t('mapping.templates.saveToggle')}
      </label>
      {checked ? (
        <Input
          value={name}
          onChange={(event) => onNameChange(event.target.value)}
          placeholder={t('mapping.templates.namePlaceholder')}
          aria-label={t('mapping.templates.namePlaceholder')}
          maxLength={TEMPLATE_NAME_MAX_LENGTH}
          autoComplete="off"
          className="h-8 sm:max-w-64"
        />
      ) : null}
    </div>
  )
}

interface MappingTemplateRowProps {
  template: ImportMappingTemplate
  ownedByCurrentUser: boolean
  onDelete: (template: ImportMappingTemplate) => void
}

/** One saved template row: name + creator, with an owner-only delete action. */
function MappingTemplateRow({ template, ownedByCurrentUser, onDelete }: MappingTemplateRowProps) {
  const { t } = useTranslation('importWizard')
  return (
    <div className="group/row flex items-center gap-1">
      <div className="min-w-0 flex-1 px-2 py-1.5">
        <p className="truncate text-sm">{template.name}</p>
        <p className="truncate text-xs text-muted-foreground">
          {t('mapping.templates.createdBy', { name: template.created_by.name })}
        </p>
      </div>
      {ownedByCurrentUser ? (
        <DropdownMenuItem
          variant="destructive"
          className="shrink-0 px-2 opacity-0 transition-opacity group-hover/row:opacity-100 focus:opacity-100"
          aria-label={t('mapping.templates.delete')}
          onSelect={(event) => {
            event.preventDefault()
            onDelete(template)
          }}
        >
          <Trash2 aria-hidden="true" />
        </DropdownMenuItem>
      ) : null}
    </div>
  )
}

interface SavedTemplatesMenuProps {
  /** Resource key selecting the backend `ImportDefinition` (e.g. `leads`). */
  domain: string
}

/**
 * Management popover (AC-012): lists every team-shared mapping template of
 * the domain; only the actor's own templates expose a delete action, guarded
 * by a confirm dialog. No apply-from-here — applying a NON-matching template
 * would violate the structure-identity guarantee (spec 0035 `<out>`).
 */
export function SavedTemplatesMenu({ domain }: SavedTemplatesMenuProps) {
  const { t, i18n } = useTranslation('importWizard')
  const { user } = useAuth()
  const confirm = useConfirm()
  const { data: templates } = useMappingTemplates(domain)
  const deleteTemplate = useDeleteMappingTemplate(domain)
  const [open, setOpen] = useState(false)

  const items = templates ?? []

  const handleDelete = async (template: ImportMappingTemplate) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('mapping.templates.delete'),
      description: t('mapping.templates.deleteConfirm', { name: template.name }),
    })
    if (!confirmed) {
      return
    }
    try {
      await deleteTemplate.mutateAsync(template.id)
      toast.success(t('mapping.templates.deleteSuccess'))
    } catch (error) {
      toast.error(apiErrorMessage(error, t('mapping.templates.deleteError'), i18n))
    }
  }

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="outline" size="sm" className="gap-1.5">
          <LayoutTemplate aria-hidden="true" className="size-3.5" />
          {t('mapping.templates.manage')}
          {items.length > 0 ? (
            <span className="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
              {items.length}
            </span>
          ) : null}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-72 p-1">
        {items.length === 0 ? (
          <p className="px-2 py-4 text-center text-xs text-muted-foreground">{t('mapping.templates.empty')}</p>
        ) : (
          items.map((template) => (
            <MappingTemplateRow
              key={template.id}
              template={template}
              ownedByCurrentUser={template.created_by.id === user?.id}
              onDelete={(target) => void handleDelete(target)}
            />
          ))
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
