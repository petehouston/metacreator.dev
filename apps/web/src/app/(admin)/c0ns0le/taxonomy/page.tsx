import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { TaxonomyScreen } from "@/components/admin/screens/taxonomy-screen";

export const metadata: Metadata = { title: "Categories & tags" };

export default function AdminTaxonomyPage() {
  return (
    <RequireStaff permissions={["post_categories.view_any", "tags.view_any"]}>
      <TaxonomyScreen />
    </RequireStaff>
  );
}
