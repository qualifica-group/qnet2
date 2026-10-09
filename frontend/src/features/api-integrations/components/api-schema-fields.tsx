import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { JsonBlock } from '@/features/api-integrations/components/json-block'
import type { JsonValue } from '@/features/api-integrations/openapi-types'
import { flattenSchemaFields, type SchemaField } from '@/features/api-integrations/schema-fields'

/** Indent per nesting level, in px (dynamic, so inline rather than a utility class). */
const INDENT_PX = 14
const HEADER_CELL = 'px-2 py-1.5 font-medium'

function FieldRow({ field, showLocation }: { field: SchemaField; showLocation: boolean }) {
  const { t } = useTranslation()

  return (
    <tr className="border-t border-border align-top">
      <td className="px-2 py-1.5" style={{ paddingLeft: `${0.5 + (field.depth * INDENT_PX) / 16}rem` }}>
        <div className="flex flex-wrap items-center gap-1.5">
          <code className="font-mono font-medium break-all">{field.name}</code>
          {field.required ? (
            <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
              {t('apiIntegrations.docs.fieldRequired')}
            </Badge>
          ) : null}
        </div>
      </td>
      {showLocation ? <td className="px-2 py-1.5 text-muted-foreground">{field.location}</td> : null}
      <td className="px-2 py-1.5">
        <code className="font-mono break-all">{field.type}</code>
        {field.nullable ? (
          <span className="ml-1 text-muted-foreground">{t('apiIntegrations.docs.fieldNullable')}</span>
        ) : null}
        {field.enumValues ? (
          <div className="mt-1 flex flex-wrap gap-1">
            {field.enumValues.map((value) => (
              <code key={String(value)} className="rounded bg-muted px-1 font-mono text-[11px]">
                {String(value)}
              </code>
            ))}
          </div>
        ) : null}
      </td>
      <td className="px-2 py-1.5 text-muted-foreground">{field.description}</td>
    </tr>
  )
}

interface ApiFieldsTableProps {
  fields: SchemaField[]
  caption: string
}

/** Fields as a compact table: name (indented when nested), type, description. */
export function ApiFieldsTable({ fields, caption }: ApiFieldsTableProps) {
  const { t } = useTranslation()
  const showLocation = fields.some((field) => field.location !== null)

  if (fields.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.noFields')}</p>
  }

  return (
    <div className="max-w-full overflow-x-auto rounded-md border border-border">
      <table className="w-full text-left text-xs">
        <caption className="sr-only">{caption}</caption>
        <thead className="bg-surface text-muted-foreground">
          <tr>
            <th scope="col" className={HEADER_CELL}>{t('apiIntegrations.docs.fieldName')}</th>
            {showLocation ? <th scope="col" className={HEADER_CELL}>{t('apiIntegrations.docs.fieldLocation')}</th> : null}
            <th scope="col" className={HEADER_CELL}>{t('apiIntegrations.docs.fieldType')}</th>
            <th scope="col" className={HEADER_CELL}>{t('apiIntegrations.docs.fieldDescription')}</th>
          </tr>
        </thead>
        <tbody>
          {fields.map((field) => (
            <FieldRow key={field.path} field={field} showLocation={showLocation} />
          ))}
        </tbody>
      </table>
    </div>
  )
}

interface ApiSchemaPanelProps {
  schema: JsonValue
  caption: string
}

/** A body or response schema as a field table, with a switch to the raw JSON schema. */
export function ApiSchemaPanel({ schema, caption }: ApiSchemaPanelProps) {
  const { t } = useTranslation()
  const [showJson, setShowJson] = useState(false)
  const fields = useMemo(() => flattenSchemaFields(schema), [schema])

  return (
    <div className="flex flex-col gap-2">
      <div className="flex justify-end">
        <Button
          type="button"
          size="xs"
          variant="outline"
          aria-pressed={showJson}
          onClick={() => setShowJson((current) => !current)}
        >
          {t('apiIntegrations.docs.jsonSchema')}
        </Button>
      </div>
      {showJson ? (
        <JsonBlock label={caption} value={schema} />
      ) : (
        <ApiFieldsTable fields={fields} caption={caption} />
      )}
    </div>
  )
}
