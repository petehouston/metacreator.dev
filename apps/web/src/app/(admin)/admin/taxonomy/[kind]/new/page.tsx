import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { RequireStaff } from "@/components/admin/require-staff";
import { TaxonomyEditorScreen } from "@/components/admin/screens/taxonomy-editor-screen";

import { TAXONOMIES } from "../kind";

export const metadata: Metadata = { title: "New label" };

export default async function AdminNewTaxonomyPage({
  params,
}: {
  params: Promise<{ kind: string }>;
}) {
  const { kind } = await params;
  const taxonomy = TAXONOMIES[kind];

  if (!taxonomy) notFound();

  return (
    <RequireStaff permissions={[taxonomy.write]}>
      <TaxonomyEditorScreen kind={taxonomy.kind} />
    </RequireStaff>
  );
}
