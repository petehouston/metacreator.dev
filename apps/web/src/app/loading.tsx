export default function Loading() {
  return (
    <div className="mx-auto w-full max-w-[75rem] px-4 py-16 sm:px-6">
      <div className="flex flex-col gap-4">
        <div className="h-10 w-1/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="h-5 w-2/3 animate-pulse rounded bg-[var(--color-surface-sunken)]" />
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 8 }, (_, index) => (
            <div
              key={index}
              className="h-36 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
              style={{ animationDelay: `${index * 60}ms` }}
            />
          ))}
        </div>
      </div>
      <span className="sr-only">Loading…</span>
    </div>
  );
}
