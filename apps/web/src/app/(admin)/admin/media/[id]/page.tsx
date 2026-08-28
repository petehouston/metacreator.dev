import type { Metadata } from "next";

import { MediaEditorScreen } from "@/components/admin/screens/media-editor-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Edit file" };

export default async function AdminEditMediaPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["media.view_any"]}>
      <MediaEditorScreen id={id} />
    </RequireStaff>
  );
}
