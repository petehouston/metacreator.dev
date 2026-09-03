import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { TopRankingEditorScreen } from "@/components/admin/screens/top-ranking-editor-screen";

export const metadata: Metadata = { title: "Edit ranking" };

export default async function EditTopRankingPage({
  params,
}: PageProps<"/c0ns0le/top-rankings/[id]">) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["top_rankings.update", "top_rankings.view"]}>
      <TopRankingEditorScreen id={id} />
    </RequireStaff>
  );
}
