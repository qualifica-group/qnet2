import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { StickyNote } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { cn } from '@/lib/utils'
import { GENERAL_NOTES_MAX_LENGTH } from '@/features/opportunities/opportunity-schema'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityGeneralNotesSectionProps {
  control: Control<OpportunityFormValues>
  collapsible?: boolean
  open?: boolean
  onOpenChange?: (open: boolean) => void
  className?: string
}

/**
 * The opportunity's "Note generali" (user directive 2026-07-27): a single
 * free-text field, prefilled from the originating lead's own notes at
 * conversion and always editable afterwards (never BR-2-locked). Mirrors the
 * lead form's notes section, live counter included.
 */
export function OpportunityGeneralNotesSection({
  control,
  collapsible,
  open,
  onOpenChange,
  className,
}: OpportunityGeneralNotesSectionProps) {
  const { t } = useTranslation()
  const notesValue = useWatch({ control, name: 'general_notes' })
  const notesLength = notesValue?.length ?? 0

  return (
    <FormSection
      icon={StickyNote}
      title={t('opportunities.form.sections.generalNotes.title')}
      description={t('opportunities.form.sections.generalNotes.description')}
      collapsible={collapsible}
      open={open}
      onOpenChange={onOpenChange}
      className={className}
    >
      <MetaField
        control={control}
        name="general_notes"
        metaKey="general_notes"
        label={t('opportunities.form.generalNotes')}
      >
        {({ field, disabled, readOnly }) => (
          <>
            <FormControl>
              <Textarea
                className="min-h-24"
                placeholder={t('opportunities.form.generalNotesPlaceholder')}
                disabled={disabled}
                readOnly={readOnly}
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value || null)}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
            <span
              aria-hidden="true"
              className={cn(
                'justify-self-end text-xs tabular-nums',
                notesLength > GENERAL_NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
              )}
            >
              {notesLength}/{GENERAL_NOTES_MAX_LENGTH}
            </span>
          </>
        )}
      </MetaField>
    </FormSection>
  )
}
