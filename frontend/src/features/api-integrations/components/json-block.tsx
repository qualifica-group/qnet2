interface JsonBlockProps {
  label: string
  value: unknown
}

/** Pretty-printed JSON in a block that scrolls inside its own box, never the page. */
export function JsonBlock({ label, value }: JsonBlockProps) {
  return (
    <pre
      role="region"
      aria-label={label}
      tabIndex={0}
      className="max-h-72 max-w-full overflow-auto rounded-md border border-border bg-surface p-3 font-mono text-xs"
    >
      {JSON.stringify(value, null, 2)}
    </pre>
  )
}
