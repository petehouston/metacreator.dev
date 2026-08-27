"use client";

import { ExternalLink, Eye, EyeOff, Save, Star } from "lucide-react";
import Link from "next/link";
import * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { useCan } from "@/components/admin/can";
import { Drawer, useToast } from "@/components/admin/feedback";
import { DataTable, Pagination, type Column } from "@/components/admin/data-table";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { FilterSelect, SearchInput } from "@/components/admin/toolbar";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { AdminTool } from "@/lib/admin/types";
import { usePagedFilters } from "@/lib/admin/use-paged-filters";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatNumber } from "@/lib/utils";

/**
 * The catalog, as an admin changes it.
 *
 * What is editable here is what a tool *is* — its tier, its visibility, its copy.
 * What it *does* lives in the runner bound to its key, and the key is not editable
 * from any screen: a catalog row whose key drifted from its runner is a 500 on the
 * next run, which is exactly the failure the architecture test exists to prevent.
 */
export function ToolsScreen() {
  const can = useCan();

  const [{ query, tier, status, category }, setFilters, page, setPage] = usePagedFilters({
    query: "",
    tier: "",
    status: "",
    category: "",
  });

  const [editing, setEditing] = React.useState<AdminTool | null>(null);

  const { data, error, loading, reload } = useAdminResource(
    () =>
      adminApi.tools.list({
        q: query || undefined,
        "filter[tier]": tier || undefined,
        "filter[status]": status || undefined,
        "filter[category]": category || undefined,
        page,
        per_page: 40,
      }),
    [query, tier, status, category, page],
  );

  const categories = useAdminResource(() => adminApi.tools.categories(), []);

  if (error) return <LoadError error={error} onRetry={reload} />;

  const editable = can("tools.update");

  const columns: Column<AdminTool>[] = [
    {
      key: "name",
      header: "Tool",
      cell: (row) => (
        <span className="flex min-w-0 flex-col">
          <span className="flex items-center gap-1.5">
            <span className="truncate font-medium text-[var(--color-foreground)]">{row.name}</span>
            {row.is_featured && (
              <Star
                className="size-3.5 shrink-0 fill-[var(--color-warning)] text-[var(--color-warning)]"
                aria-label="Featured"
              />
            )}
          </span>
          <span className="truncate font-mono text-[0.6875rem] text-[var(--color-foreground-subtle)]">
            {row.key}
          </span>
        </span>
      ),
    },
    {
      key: "category",
      header: "Category",
      hideBelow: "lg",
      cell: (row) => row.category?.name ?? "—",
    },
    {
      key: "tier",
      header: "Tier",
      cell: (row) => <StatusPill label={row.tier} tone={tone.tier(row.tier)} />,
    },
    {
      key: "status",
      header: "Status",
      cell: (row) => (
        <span className="flex flex-wrap items-center gap-1">
          <StatusPill label={humanise(row.status)} tone={tone.tool(row.status)} />
          {!row.is_visible && (
            <span title="Hidden from the catalog">
              <EyeOff className="size-3.5 text-[var(--color-foreground-subtle)]" aria-hidden="true" />
              <span className="sr-only">Hidden</span>
            </span>
          )}
        </span>
      ),
    },
    {
      key: "runs",
      header: "Lifetime runs",
      numeric: true,
      hideBelow: "sm",
      cell: (row) => formatNumber(row.stats.runs),
    },
    {
      key: "success",
      header: "Success",
      numeric: true,
      hideBelow: "md",
      cell: (row) => (
        <span
          style={{ color: row.stats.success_rate < 95 ? "var(--color-danger)" : undefined }}
        >
          {row.stats.success_rate}%
        </span>
      ),
    },
    {
      key: "grants",
      header: "Comped",
      numeric: true,
      hideBelow: "xl",
      cell: (row) =>
        row.stats.grants ? (
          <Link
            href="/admin/grants"
            className="text-[var(--color-primary)] hover:underline"
            onClick={(event) => event.stopPropagation()}
          >
            {row.stats.grants}
          </Link>
        ) : (
          <span className="text-[var(--color-foreground-subtle)]">—</span>
        ),
    },
    {
      key: "actions",
      header: "",
      width: "5rem",
      cell: (row) => (
        <span className="flex justify-end gap-1">
          <Link
            href={`/tools/${row.slug}`}
            target="_blank"
            rel="noreferrer"
            onClick={(event) => event.stopPropagation()}
            aria-label={`Open ${row.name} on the site`}
            className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-subtle)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
          >
            <ExternalLink className="size-3.5" aria-hidden="true" />
          </Link>
        </span>
      ),
    },
  ];

  return (
    <>
      <AdminPageHeader
        eyebrow="Product"
        title="Tools"
        description="Tiering, visibility and catalog copy. Behaviour lives in the runner bound to each tool's key, which is fixed at deploy time."
        actions={
          <Button asChild variant="secondary" size="sm">
            <Link href="/admin/analytics">Tool analytics</Link>
          </Button>
        }
      />

      <AdminPanel
        title="Catalog"
        description={data ? `${data.meta.page.total} tools` : "Loading…"}
        bodyClassName="p-0"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <SearchInput
              value={query}
              onChange={(next) => setFilters({ query: next })}
              placeholder="Find a tool…"
              className="w-48"
            />
            <FilterSelect
              label="Tier"
              value={tier}
              onChange={(next) => setFilters({ tier: next })}
              options={[
                { value: "", label: "All" },
                { value: "free", label: "Free" },
                { value: "account", label: "Account" },
                { value: "premium", label: "Premium" },
              ]}
            />
            <FilterSelect
              label="Status"
              value={status}
              onChange={(next) => setFilters({ status: next })}
              options={[
                { value: "", label: "All" },
                { value: "published", label: "Published" },
                { value: "draft", label: "Draft" },
                { value: "hidden", label: "Hidden" },
                { value: "deprecated", label: "Deprecated" },
              ]}
            />
            <FilterSelect
              label="Category"
              value={category}
              onChange={(next) => setFilters({ category: next })}
              options={[
                { value: "", label: "All" },
                ...(categories.data?.data ?? []).map((entry) => ({
                  value: entry.slug,
                  label: entry.name,
                })),
              ]}
            />
          </div>
        }
      >
        <DataTable
          rows={data?.data ?? []}
          columns={columns}
          rowKey={(row) => row.id}
          loading={loading}
          onRowClick={editable ? (row) => setEditing(row) : undefined}
          empty={
            <p className="px-4 py-12 text-center text-sm text-[var(--color-foreground-subtle)]">
              No tool matches those filters.
            </p>
          }
        />

        {data && (
          <Pagination
            page={data.meta.page.current}
            lastPage={data.meta.page.last_page}
            total={data.meta.page.total}
            perPage={data.meta.page.per_page}
            onChange={setPage}
          />
        )}
      </AdminPanel>

      {editing && (
        <ToolEditor
          tool={editing}
          categories={(categories.data?.data ?? []).map((entry) => ({
            id: entry.id,
            name: entry.name,
          }))}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            reload();
          }}
        />
      )}
    </>
  );
}

function ToolEditor({
  tool,
  categories,
  onClose,
  onSaved,
}: {
  tool: AdminTool;
  categories: { id: number; name: string }[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const { notify, reportError } = useToast();

  const [form, setForm] = React.useState({
    name: tool.name,
    tagline: tool.tagline ?? "",
    description: tool.description ?? "",
    tier: tool.tier,
    status: tool.status,
    is_visible: tool.is_visible,
    is_featured: tool.is_featured,
    sort_order: tool.sort_order,
    category_id: tool.category?.id ?? categories[0]?.id ?? 0,
  });

  const [saving, setSaving] = React.useState(false);

  async function save() {
    setSaving(true);

    const result = await adminApi.tools.update(tool.slug, {
      ...form,
      tagline: form.tagline || null,
      description: form.description || null,
    } as Partial<AdminTool>);

    setSaving(false);

    if (result.ok) {
      notify(`${form.name} saved.`);
      onSaved();
    } else {
      reportError(result.error);
    }
  }

  return (
    <Drawer
      open
      title={tool.name}
      description={`v${tool.version} · ${tool.key}`}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" size="sm" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => void save()} loading={saving}>
            <Save className="size-4" aria-hidden="true" />
            Save tool
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <Field id="tool-name" label="Name" required>
          {(props) => (
            <Input
              {...props}
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
            />
          )}
        </Field>

        <Field
          id="tool-tagline"
          label="Tagline"
          hint="One line, shown on the catalog card and in search results."
          counter={`${form.tagline.length}/220`}
        >
          {(props) => (
            <Input
              {...props}
              maxLength={220}
              value={form.tagline}
              onChange={(event) => setForm({ ...form, tagline: event.target.value })}
            />
          )}
        </Field>

        <Field id="tool-description" label="Description">
          {(props) => (
            <Textarea
              {...props}
              value={form.description}
              onChange={(event) => setForm({ ...form, description: event.target.value })}
            />
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="tool-tier"
            label="Access tier"
            hint="Who can run this without paying."
          >
            {(props) => (
              <Select
                {...props}
                value={form.tier}
                onChange={(event) => setForm({ ...form, tier: event.target.value })}
              >
                <option value="free">Free — anyone</option>
                <option value="account">Account — signed in</option>
                <option value="premium">Premium — subscribers</option>
              </Select>
            )}
          </Field>

          <Field id="tool-status" label="Status">
            {(props) => (
              <Select
                {...props}
                value={form.status}
                onChange={(event) => setForm({ ...form, status: event.target.value })}
              >
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="hidden">Hidden</option>
                <option value="deprecated">Deprecated</option>
              </Select>
            )}
          </Field>

          <Field id="tool-category" label="Category">
            {(props) => (
              <Select
                {...props}
                value={String(form.category_id)}
                onChange={(event) =>
                  setForm({ ...form, category_id: Number(event.target.value) })
                }
              >
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </Select>
            )}
          </Field>

          <Field
            id="tool-sort"
            label="Sort order"
            hint="Lower sorts first within its category."
          >
            {(props) => (
              <Input
                {...props}
                type="number"
                min={0}
                max={9999}
                value={form.sort_order}
                onChange={(event) =>
                  setForm({ ...form, sort_order: Number(event.target.value) })
                }
              />
            )}
          </Field>
        </div>

        <div className="flex flex-col gap-1 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] p-3">
          <Checkbox
            label="Visible in the catalog"
            hint="Turning this off removes it from listings and search without unpublishing it. Anyone with a direct link still gets the page."
            checked={form.is_visible}
            onChange={(event) => setForm({ ...form, is_visible: event.target.checked })}
          />

          <Checkbox
            label="Feature on the catalog"
            hint="Featured tools sort to the top of the listing."
            checked={form.is_featured}
            onChange={(event) => setForm({ ...form, is_featured: event.target.checked })}
          />
        </div>

        <dl className="grid grid-cols-2 gap-3 rounded-[var(--radius-md)] bg-[var(--color-surface-sunken)] p-3 text-sm">
          <div>
            <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              Lifetime runs
            </dt>
            <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
              {formatNumber(tool.stats.runs)}
            </dd>
          </div>
          <div>
            <dt className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
              Average duration
            </dt>
            <dd className="tabular mt-0.5 font-medium text-[var(--color-foreground)]">
              {formatNumber(tool.stats.avg_duration_ms)}ms
            </dd>
          </div>
        </dl>

        <p className="flex items-start gap-2 text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
          <Eye className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          The slug, the key, the version and the input schema are fixed here on
          purpose. Changing a slug breaks every link pointing at it, and the other
          three belong to the runner — they move with a deploy, not with a form.
        </p>
      </div>
    </Drawer>
  );
}
