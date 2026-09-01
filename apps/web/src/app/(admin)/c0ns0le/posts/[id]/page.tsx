import type { Metadata } from "next";

import { PostEditorScreen } from "@/components/admin/screens/post-editor-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Edit post" };

export default async function AdminEditPostPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["posts.view", "posts.view_any"]}>
      <PostEditorScreen id={id} />
    </RequireStaff>
  );
}
