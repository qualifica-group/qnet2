const PATH_PARAMETER = /(\{[^}]+\})/

/** Monospace path with its `{parameters}` highlighted. */
export function ApiPathText({ path }: { path: string }) {
  return (
    <code className="min-w-0 break-all font-mono text-xs">
      {path.split(PATH_PARAMETER).map((part, index) =>
        part.startsWith('{') ? (
          <span key={index} className="rounded bg-muted px-0.5 font-medium text-primary">
            {part}
          </span>
        ) : (
          part
        ),
      )}
    </code>
  )
}
