"use client";

import { Image as ImageIcon, X } from "lucide-react";
import Image from "next/image";

import { AdminPanel } from "@/components/admin/admin-page";
import { Button } from "@/components/ui/button";
import { Field, Input, Select, Textarea } from "@/components/ui/field";
import type { SeoOverrides } from "@/lib/admin/types";

/**
 * The SEO overrides for one entity, wherever it lives.
 *
 * Every field is optional, and blank is the right answer for most rows: the public
 * page already falls back to the entity's own name and description, then to the
 * site template. This panel exists for the handful worth hand-tuning, so it shows
 * what will actually be published — the search snippet and the social card —
 * rather than a column of inputs whose effect you have to imagine.
 *
 * Shared by the tool editor and the ranking editor. It was the tool editor's until
 * the rankings needed the same thing, and copying it would have meant two panels
 * drifting apart over exactly the fields whose consistency is the point.
 *
 * `idPrefix` keeps the field ids unique per screen; `breadcrumb` is the URL shape
 * the search preview draws, which differs per entity and is the only thing about
 * this form that does.
 */
export function SeoPanel({
  seo,
  patch,
  editable,
  idPrefix,
  noun = "page",
  breadcrumb,
  canonicalPlaceholder,
  fallbackTitle,
  fallbackDescription,
  imageUrl,
  onPickImage,
  onClearImage,
}: {
  seo: SeoOverrides;
  patch: (next: Partial<SeoOverrides>) => void;
  editable: boolean;
  idPrefix: string;
  /**
   * What one of these is called — "tool", "ranking". Used in the hints, which
   * would otherwise tell an editor on a ranking page about tools.
   */
  noun?: string;
  /** e.g. `metacreator.dev › tools › slug-here` */
  breadcrumb: string;
  canonicalPlaceholder: string;
  fallbackTitle: string;
  fallbackDescription: string;
  imageUrl: string | null;
  onPickImage: () => void;
  onClearImage: () => void;
}) {
  const title = seo.title || fallbackTitle;
  const description = seo.description || fallbackDescription;
  const socialTitle = seo.og_title || title;
  const socialDescription = seo.og_description || description;

  return (
    <AdminPanel
      title="SEO & sharing"
      description={`How this ${noun} appears in search results and when its link is shared`}
    >
      <fieldset disabled={!editable} className="flex flex-col gap-4">
        {/* Counters tell you a number. This tells you what actually gets
            truncated, which is the thing being decided. */}
        <div className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3">
          <p className="mb-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Search preview
          </p>
          <p className="truncate text-xs text-[var(--color-foreground-subtle)]">
            {breadcrumb}
          </p>
          <p className="mt-0.5 line-clamp-1 text-sm font-medium text-[var(--color-primary)]">
            {title}
          </p>
          <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-[var(--color-foreground-muted)]">
            {description ||
              "No description — search engines will pick a snippet from the page themselves."}
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id={`${idPrefix}-seo-title`}
            label="Meta title"
            hint={`Left blank, the ${noun} title is used with the site template.`}
            counter={`${(seo.title ?? "").length}/60`}
          >
            {(props) => (
              <Input
                {...props}
                maxLength={255}
                value={seo.title ?? ""}
                placeholder={fallbackTitle}
                onChange={(event) => patch({ title: event.target.value })}
              />
            )}
          </Field>

          <Field
            id={`${idPrefix}-seo-keyword`}
            label="Focus keyword"
            hint={`What this ${noun} is meant to rank for. Recorded, not enforced.`}
          >
            {(props) => (
              <Input
                {...props}
                maxLength={120}
                value={seo.focus_keyword ?? ""}
                onChange={(event) =>
                  patch({ focus_keyword: event.target.value })
                }
              />
            )}
          </Field>
        </div>

        <Field
          id={`${idPrefix}-seo-description`}
          label="Meta description"
          hint="Aim for 140–160 characters. Longer is truncated; shorter wastes the slot."
          counter={`${(seo.description ?? "").length}/160`}
        >
          {(props) => (
            <Textarea
              {...props}
              maxLength={500}
              value={seo.description ?? ""}
              placeholder={fallbackDescription}
              onChange={(event) => patch({ description: event.target.value })}
              className="min-h-20 text-sm"
            />
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id={`${idPrefix}-seo-canonical`}
            label="Canonical URL"
            hint="Only when this page duplicates one that lives elsewhere."
          >
            {(props) => (
              <Input
                {...props}
                type="url"
                value={seo.canonical_url ?? ""}
                placeholder={canonicalPlaceholder}
                onChange={(event) =>
                  patch({ canonical_url: event.target.value })
                }
                className="font-mono text-xs"
              />
            )}
          </Field>

          <Field
            id={`${idPrefix}-seo-robots`}
            label="Robots"
            hint={`A ${noun} set to no-index is still reachable — it just stops competing in search.`}
          >
            {(props) => (
              <Select
                {...props}
                value={seo.robots ?? "index,follow"}
                onChange={(event) => patch({ robots: event.target.value })}
              >
                <option value="index,follow">Index and follow</option>
                <option value="noindex,follow">
                  Do not index, follow links
                </option>
                <option value="index,nofollow">
                  Index, do not follow links
                </option>
                <option value="noindex,nofollow">Do not index or follow</option>
              </Select>
            )}
          </Field>
        </div>

        <hr className="border-[var(--color-border-subtle)]" />

        {/* The same fields Open Graph and Twitter both read, because both cards
            should say the same thing and nobody wants to type it twice. */}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id={`${idPrefix}-seo-og-title`}
            label="Social title"
            hint="Shown when the link is shared. Falls back to the meta title."
          >
            {(props) => (
              <Input
                {...props}
                maxLength={255}
                value={seo.og_title ?? ""}
                placeholder={title}
                onChange={(event) => patch({ og_title: event.target.value })}
              />
            )}
          </Field>

          <Field id={`${idPrefix}-seo-card`} label="Card type">
            {(props) => (
              <Select
                {...props}
                value={seo.twitter_card ?? "summary_large_image"}
                onChange={(event) =>
                  patch({ twitter_card: event.target.value })
                }
              >
                <option value="summary_large_image">Large image</option>
                <option value="summary">Summary</option>
              </Select>
            )}
          </Field>
        </div>

        <Field
          id={`${idPrefix}-seo-og-description`}
          label="Social description"
          hint="Falls back to the meta description."
        >
          {(props) => (
            <Textarea
              {...props}
              maxLength={500}
              value={seo.og_description ?? ""}
              placeholder={description}
              onChange={(event) =>
                patch({ og_description: event.target.value })
              }
              className="min-h-16 text-sm"
            />
          )}
        </Field>

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium text-[var(--color-foreground)]">
            Share image
          </span>

          <div className="flex flex-wrap items-start gap-3">
            <div className="relative h-[6.5rem] w-[12.4rem] shrink-0 overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]">
              {imageUrl ? (
                <Image
                  src={imageUrl}
                  alt=""
                  fill
                  sizes="200px"
                  className="object-cover"
                  unoptimized
                />
              ) : (
                <div className="flex h-full flex-col items-center justify-center gap-1 text-[var(--color-foreground-subtle)]">
                  <ImageIcon className="size-5" aria-hidden="true" />
                  <span className="text-[0.6875rem]">Site default</span>
                </div>
              )}
            </div>

            <div className="flex min-w-[12rem] flex-1 flex-col gap-2">
              <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
                1200×630 is the size every network crops to. With none set, the
                site-wide card is used — never nothing, so a share is never a
                grey box.
              </p>

              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={onPickImage}
                >
                  <ImageIcon className="size-4" aria-hidden="true" />
                  {imageUrl ? "Replace" : "Choose image"}
                </Button>

                {imageUrl && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClearImage}
                  >
                    <X className="size-4" aria-hidden="true" />
                    Clear
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* What the card will actually look like, at the width the networks use. */}
        <div className="rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)] p-3">
          <p className="mb-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            Social preview
          </p>
          <p className="line-clamp-1 text-sm font-medium text-[var(--color-foreground)]">
            {socialTitle}
          </p>
          <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-[var(--color-foreground-muted)]">
            {socialDescription || "No description set."}
          </p>
          <p className="mt-1 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            metacreator.dev
          </p>
        </div>
      </fieldset>
    </AdminPanel>
  );
}
