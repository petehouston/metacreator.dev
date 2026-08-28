import type { TaxonomyKind } from "@/components/admin/screens/taxonomy-editor-screen";

/**
 * The URL says `categories` or `tags`; the screen and the permissions think in
 * the singular. One table, so the two route files cannot disagree about which
 * permission guards which taxonomy.
 */
export const TAXONOMIES: Record<string, { kind: TaxonomyKind; view: string; write: string }> = {
  categories: {
    kind: "category",
    view: "post_categories.view_any",
    write: "post_categories.create",
  },
  tags: { kind: "tag", view: "tags.view_any", write: "tags.create" },
};
