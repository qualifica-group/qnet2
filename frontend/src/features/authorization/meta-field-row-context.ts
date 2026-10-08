import { createContext } from 'react'

/**
 * The form field whose label an enclosing record ROW already shows in its own
 * label column (spec 0195: the task detail's in-place editors and the create
 * form's rows). The `MetaField` bound to exactly that field keeps its label
 * for assistive tech only and sets its hint beside the control; every other
 * field inside the row (a recurrence rule's sub-fields, the Fase picked with
 * a Commessa) keeps its visible label. `null` outside such a row.
 */
export const MetaFieldRowContext = createContext<string | null>(null)
