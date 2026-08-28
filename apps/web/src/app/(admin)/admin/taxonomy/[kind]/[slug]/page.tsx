import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { RequireStaff } from "@/components/admin/require-staff";
import { TaxonomyEditorScreen } from "@/components/admin/screens/taxonomy-editor-screen";

import { TAXONOMIES } from "../kind";

export const metadata: Metadata = { title: "Edit label" };

export default async function AdminEditTaxonomyPage({
  params,
}: {
  params: Promise<{ kind: string; slug: string }>;
}) {
  const { kind, slug } = await params;
  const taxonomy = TAXONOMIES[kind];

  if (!taxonomy) notFound();

  return (
    <RequireStaff permissions={[taxonomy.view]}>
      <TaxonomyEditorScreen kind={taxonomy.kind} slug={slug} />
    </RequireStaff>
  );
}
