import { SearchableSelect } from '@/components/ui/searchable-select'

/** A pickable place at one level of the cascade. */
export interface GeoOption {
  id: number
  name: string
}

export interface GeoFieldProps {
  label: string
  /** Renders a required marker (asterisk) next to the label, mirroring `FormLabel required`. */
  required?: boolean
  placeholder: string
  value: number | null
  options: GeoOption[]
  isPending: boolean
  isError: boolean
  disabled: boolean
  emptyLabel: string
  errorLabel: string
  retryLabel: string
  searchPlaceholder: string
  noMatchLabel: string
  onChange: (id: number) => void
  onRetry: () => void
  /** Set false when the caller narrows the list server-side (city level). */
  filter?: boolean
  /** Debounced search term, for the server-searched city level. */
  onSearchChange?: (term: string) => void
  hasNextPage?: boolean
  isFetchingNextPage?: boolean
  onLoadMore?: () => void
}

/**
 * A single dependent geo select: a searchable dropdown that owns its own
 * loading/error/empty states inside the popover, so a re-search never unmounts
 * it. Disabled until its parent has been chosen.
 */
export function GeoField({
  label,
  required = false,
  placeholder,
  value,
  options,
  isPending,
  isError,
  disabled,
  emptyLabel,
  errorLabel,
  retryLabel,
  searchPlaceholder,
  noMatchLabel,
  onChange,
  onRetry,
  filter,
  onSearchChange,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
}: GeoFieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-sm font-medium">
        {label}
        {required && (
          <span className="ml-1 text-destructive" aria-hidden="true">
            *
          </span>
        )}
      </span>
      <SearchableSelect
        value={value}
        onChange={onChange}
        options={options}
        disabled={disabled}
        isPending={!disabled && isPending}
        isError={!disabled && isError}
        onRetry={onRetry}
        filter={filter}
        onSearchChange={onSearchChange}
        hasNextPage={hasNextPage}
        isFetchingNextPage={isFetchingNextPage}
        onLoadMore={onLoadMore}
        labels={{
          placeholder,
          searchPlaceholder,
          empty: emptyLabel,
          noMatch: noMatchLabel,
          error: errorLabel,
          retry: retryLabel,
        }}
      />
    </div>
  )
}
