import type { Metadata } from "next";

import { PostEditorScreen } from "@/components/admin/screens/post-editor-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "New post" };

export default function AdminNewPostPage() {
  return (
    <RequireStaff permissions={["posts.create"]}>
      <PostEditorScreen />
    </RequireStaff>
  );
}
