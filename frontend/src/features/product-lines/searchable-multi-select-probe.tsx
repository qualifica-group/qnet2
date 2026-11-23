/**
 * Test double for `SearchableMultiSelect` in the product-line suites (spec
 * 0415): exposes the props as `data-testid` probes, like the
 * `SearchableSelect` doubles of the same suites. A group header
 * (`selectable: false`) shows as `:disabled`; every selectable option is a
 * toggle button. The real listing is covered in `searchable-multi-select.test.tsx`.
 *
 * Usage:
 *   vi.mock('@/components/ui/searchable-multi-select', async () =>
 *     await import('@/features/product-lines/searchable-multi-select-probe'))
 */
interface ProbeProps {
  value: string[]
  onChange: (next: string[]) => void
  options: { value: string; selectable?: boolean }[]
  disabled?: boolean
  'aria-label'?: string
}

export function SearchableMultiSelect({ value, onChange, options, disabled, 'aria-label': label }: ProbeProps) {
  const toggle = (optionValue: string) =>
    onChange(value.includes(optionValue) ? value.filter((picked) => picked !== optionValue) : [...value, optionValue])

  return (
    <div data-testid={`select-${label}`} data-kind="multi">
      <span data-testid={`value-${label}`}>{value.join(',')}</span>
      <span data-testid={`disabled-${label}`}>{String(Boolean(disabled))}</span>
      <span data-testid={`options-${label}`}>
        {options.map((option) => `${option.value}${option.selectable === false ? ':disabled' : ''}`).join(',')}
      </span>
      {options
        .filter((option) => option.selectable !== false)
        .map((option) => (
          <button key={option.value} type="button" onClick={() => toggle(option.value)}>
            {`select ${label} ${option.value}`}
          </button>
        ))}
    </div>
  )
}
