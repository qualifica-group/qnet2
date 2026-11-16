import { Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ALL_CATEGORIES, NO_CATEGORY, type ProgramLineCategory } from '@/features/contracts/use-contract-program-lines-filter'

interface ContractProgramLinesFilterBarProps {
  search: string
  onSearchChange: (next: string) => void
  category: string
  onCategoryChange: (next: string) => void
  categories: ProgramLineCategory[]
  hasUncategorized: boolean
}

/** Search box and category filter above the "Programma" lines table. */
export function ContractProgramLinesFilterBar({
  search,
  onSearchChange,
  category,
  onCategoryChange,
  categories,
  hasUncategorized,
}: ContractProgramLinesFilterBarProps) {
  const { t } = useTranslation()
  const prefix = 'contracts.actions.programDialog'

  return (
    <div className="flex flex-col gap-2 sm:flex-row">
      <div className="relative min-w-0 flex-1">
        <Search
          className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <Input
          type="search"
          value={search}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => onSearchChange(event.target.value)}
          placeholder={t(`${prefix}.linesSearch`)}
          aria-label={t(`${prefix}.linesSearch`)}
          className="h-8 pl-8 text-xs"
        />
      </div>
      <Select value={category} onValueChange={onCategoryChange}>
        <SelectTrigger size="sm" aria-label={t(`${prefix}.linesCategoryFilter`)} className="w-full text-xs sm:w-56">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL_CATEGORIES} className="text-xs">
            {t(`${prefix}.linesCategoryAll`)}
          </SelectItem>
          {categories.map((option) => (
            <SelectItem key={option.value} value={option.value} className="text-xs">
              {option.name}
            </SelectItem>
          ))}
          {hasUncategorized ? (
            <SelectItem value={NO_CATEGORY} className="text-xs">
              {t(`${prefix}.linesCategoryNone`)}
            </SelectItem>
          ) : null}
        </SelectContent>
      </Select>
    </div>
  )
}
