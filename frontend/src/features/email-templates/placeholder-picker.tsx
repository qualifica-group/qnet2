import { useState } from 'react'
import { Braces, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import type { EmailTemplateVariableCategory } from '@/features/email-templates/variables-api'

interface PlaceholderPickerProps {
  categories: EmailTemplateVariableCategory[]
  isLoading: boolean
  isError?: boolean
  disabled?: boolean
  /** Inserts `variable.variable` (`{category.key}`) at the caller's own insertion point. */
  onInsert: (variable: string) => void
}

function matches(text: string, search: string): boolean {
  return text.toLowerCase().includes(search)
}

/**
 * Compact popover trigger listing the placeholder catalog (AC-022): search by
 * both the raw `{category.key}` token and its localized label, click to
 * insert. Purely presentational — WHERE the token lands (subject caret vs.
 * body editor selection) is entirely the caller's `onInsert`. Two independent
 * instances sit next to the subject input and above the body editor, each
 * bound to its own target, rather than one shared "active field" tracker.
 */
export function PlaceholderPicker({
  categories,
  isLoading,
  isError = false,
  disabled,
  onInsert,
}: PlaceholderPickerProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const normalizedSearch = search.trim().toLowerCase()

  const filtered = categories
    .map((category) => ({
      ...category,
      variables: category.variables.filter(
        (variable) =>
          !normalizedSearch ||
          matches(variable.variable, normalizedSearch) ||
          matches(variable.label, normalizedSearch),
      ),
    }))
    .filter((category) => category.variables.length > 0)

  const handleInsert = (variable: string) => {
    onInsert(variable)
    setOpen(false)
    setSearch('')
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" size="sm" className="h-8 gap-1.5 bg-card px-2.5 text-xs" disabled={disabled}>
          <Braces className="size-3.5" aria-hidden="true" />
          {t('emailTemplates.form.variablesPicker')}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="flex max-h-80 w-72 flex-col gap-2 p-2">
        <div className="relative">
          <Search
            className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('common.search')}
            aria-label={t('common.search')}
            className="h-7 pl-7 text-xs"
          />
        </div>
        <div className="flex flex-1 flex-col gap-3 overflow-y-auto">
          {isLoading && <Skeleton className="h-20 w-full" />}
          {!isLoading && isError && (
            <p className="px-1 text-xs text-destructive" role="alert">
              {t('emailTemplates.form.variablesPickerError')}
            </p>
          )}
          {!isLoading && !isError && filtered.length === 0 && (
            <p className="px-1 text-xs text-muted-foreground italic">{t('emailTemplates.form.variablesPickerEmpty')}</p>
          )}
          {filtered.map((category) => (
            <div key={category.key} className="flex flex-col gap-1">
              <h4 className="px-1 text-xs font-medium text-muted-foreground">{category.label}</h4>
              <ul className="flex flex-col gap-0.5">
                {category.variables.map((variable) => (
                  <li key={variable.variable}>
                    <button
                      type="button"
                      onClick={() => handleInsert(variable.variable)}
                      title={variable.variable}
                      className="flex w-full min-w-0 flex-col items-start rounded-sm px-1.5 py-1 text-left text-xs outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span className="min-w-0 truncate font-medium text-foreground">{variable.label}</span>
                      <span className="min-w-0 truncate text-muted-foreground">{variable.variable}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  )
}
