import { useState } from 'react'
import { Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

interface VariablePickerProps {
  catalog: DocumentLayoutVariablesCatalog | undefined
  isLoading: boolean
  /** True when no text run is focused to receive an insertion (AC-122). */
  disabled: boolean
  onInsert: (variable: string) => void
}

function matches(text: string, search: string): boolean {
  return text.toLowerCase().includes(search)
}

/**
 * Searchable, category-grouped variable catalog (AC-122): filters by both
 * `variable` (the raw `{category.key}` token) and `label` (the localized
 * name), and inserts at the active run's caret via `onInsert` — the caret
 * placement itself is `use-caret-insertion.ts`'s job, this component only
 * reports WHICH variable was picked.
 */
export function VariablePicker({ catalog, isLoading, disabled, onInsert }: VariablePickerProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const normalizedSearch = search.trim().toLowerCase()

  const categories = (catalog?.categories ?? [])
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

  return (
    <div className="flex h-full flex-col gap-2 rounded-md border border-border bg-card p-3">
      <h3 className="text-xs font-semibold text-foreground">{t('documentLayouts.editor.variables.title')}</h3>
      <div className="relative">
        <Search
          className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('documentLayouts.editor.variables.searchPlaceholder')}
          aria-label={t('documentLayouts.editor.variables.searchLabel')}
          className="h-7 pl-7 text-xs"
        />
      </div>
      {disabled && (
        <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.variables.selectRunHint')}</p>
      )}
      <div className="flex flex-1 flex-col gap-3 overflow-y-auto">
        {isLoading && <Skeleton className="h-20 w-full" />}
        {!isLoading && categories.length === 0 && (
          <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.variables.empty')}</p>
        )}
        {categories.map((category) => (
          <div key={category.key} className="flex flex-col gap-1">
            <h4 className="text-xs font-medium text-muted-foreground">{category.label}</h4>
            <ul className="flex flex-col gap-0.5">
              {category.variables.map((variable) => (
                <li key={variable.variable}>
                  <button
                    type="button"
                    disabled={disabled}
                    onClick={() => onInsert(variable.variable)}
                    title={variable.variable}
                    className="flex w-full min-w-0 flex-col items-start rounded-sm px-1.5 py-1 text-left text-xs outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
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
    </div>
  )
}
