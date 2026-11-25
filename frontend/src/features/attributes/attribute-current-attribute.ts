/** Resource segment of `GET /api/attributes/for-select`: the "Campo attuale" options (spec 0429). */
export const ATTRIBUTES_FOR_SELECT_RESOURCE = 'attributes'

/** "Nome (codice)": the same label the for-select gives, so the trigger and the closed row match. */
export function currentAttributeLabel(attribute: { code: string; name: string }): string {
  return `${attribute.name} (${attribute.code})`
}
