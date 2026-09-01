"use client";

import { ArrowLeft, Save } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { LoadError } from "@/components/admin/load-error";
import { Button } from "@/components/ui/button";
import { Field, Input, Textarea } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import type { Taxonomy } from "@/lib/admin/types";
import type { ApiResult } from "@/lib/http";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

export type TaxonomyKind = "category" | "tag";

/**
 * One label — a category or a tag — on its own page.
 *
 * Both taxonomies share this screen for the same reason they share a list: the
 * fields are identical, and two near-copies is how they end up behaving
 * differently. The kind only decides which endpoint the save goes to and what the
 * page calls itself.
 */
export function TaxonomyEditorScreen({ kind, slug }: { kind: TaxonomyKind; slug?: string }) {
  // "New" is modelled as a resource that resolves to nothing rather than as a
  // separate branch, so the hook order is identical on both paths.
  const { data, error, loading, reload } = useAdminResource<Taxonomy | null>(
    () =>
      slug === undefined
        ? Promise.resolve<ApiResult<Taxonomy | null>>({ ok: true, data: null })
        : kind === "category"
          ? adminApi.taxonomy.category(slug)
          : adminApi.taxonomy.tag(slug),
    [kind, slug],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div
        className="h-72 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  if (slug !== undefined && !data) return null;

  // Keyed on the loaded item so the form state is built once the values exist.
  return <TaxonomyForm key={data?.id ?? "new"} kind={kind} item={data} />;
}

function TaxonomyForm({ kind, item }: { kind: TaxonomyKind; item: Taxonomy | null }) {
  const router = useRouter();
  const { notify, reportError } = useToast();

  const label = kind === "category" ? "category" : "tag";

  const [name, setName] = React.useState(item?.name ?? "");
  const [slug, setSlug] = React.useState(item?.slug ?? "");
  const [description, setDescription] = React.useState(item?.description ?? "");
  const [saving, setSaving] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});

  async function save() {
    setSaving(true);
    setErrors({});

    const body = { name, slug: slug || undefined, description };

    const result =
      kind === "category"
        ? await adminApi.taxonomy.saveCategory(body, item?.slug)
        : await adminApi.taxonomy.saveTag(body, item?.slug);

    setSaving(false);

    if (result.ok) {
      notify(item ? `${name} updated.` : `${name} created.`);
      router.push("/c0ns0le/taxonomy");
    } else {
      setErrors(result.error.fieldErrors ?? {});
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Content · Categories & tags"
        title={item ? `Edit ${item.name}` : `New ${label}`}
        description={
          kind === "category"
            ? "One per post: where the writing lives. Renaming is safe; changing the slug is not."
            : "Many per post: what the writing is about. Tags drive the related-posts suggestions."
        }
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/taxonomy">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to categories &amp; tags
              </Link>
            </Button>

            <Button
              size="sm"
              onClick={() => void save()}
              loading={saving}
              disabled={name.trim() === ""}
            >
              <Save className="size-4" aria-hidden="true" />
              {item ? "Save" : `Create ${label}`}
            </Button>
          </>
        }
      />

      <AdminPanel
        title={item ? item.name : `New ${label}`}
        description={
          item ? `${item.posts_count} ${item.posts_count === 1 ? "post" : "posts"}` : undefined
        }
      >
        <div className="flex max-w-xl flex-col gap-4">
          <Field id="tax-name" label="Name" error={errors.name?.[0]} required>
            {(props) => (
              <Input
                {...props}
                value={name}
                onChange={(event) => setName(event.target.value)}
                autoFocus
              />
            )}
          </Field>

          <Field
            id="tax-slug"
            label="Slug"
            hint={
              item
                ? "Changing this breaks any link pointing at the old one."
                : "Generated from the name if you leave it blank."
            }
            error={errors.slug?.[0]}
          >
            {(props) => (
              <Input
                {...props}
                value={slug}
                onChange={(event) => setSlug(event.target.value)}
                placeholder="generated-from-the-name"
                className="font-mono text-xs"
              />
            )}
          </Field>

          <Field
            id="tax-description"
            label="Description"
            hint="Shown on the archive page and used as its meta description."
          >
            {(props) => (
              <Textarea
                {...props}
                value={description ?? ""}
                onChange={(event) => setDescription(event.target.value)}
                className="min-h-24 text-sm"
              />
            )}
          </Field>
        </div>
      </AdminPanel>
    </>
  );
}
