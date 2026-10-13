import { useController, type Control, type FieldValues, type Path } from 'react-hook-form'

interface RelationDoubleProps {
  control: Control<FieldValues>
  name: Path<FieldValues>
  label: string
  resource: string
  selected: { id: number; name: string } | null
}

/**
 * Test double of `RelationSelectField`: still bound to the real RHF field, so a
 * test reads the current value, simulates a pick (id 99) and sees the field's
 * refusal, without the network data the real `AsyncPaginatedSelect` needs.
 */
export function RelationSelectFieldDouble({ control, name, label, resource, selected }: RelationDoubleProps) {
  const { field, fieldState } = useController({ control, name })
  return (
    <div>
      <span>{`${label}: ${resource}: ${String(field.value)}`}</span>
      {selected ? <span>{`${name} selected: ${selected.name}`}</span> : null}
      {fieldState.error?.message ? <p role="alert">{fieldState.error.message}</p> : null}
      <button type="button" onClick={() => field.onChange(99)}>{`pick ${name}`}</button>
    </div>
  )
}
