import type { Metadata } from "next";

import { PostPreviewScreen } from "@/components/admin/screens/post-preview-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Preview" };

export default function AdminPostPreviewPage() {
  return (
    <RequireStaff permissions={["posts.view", "posts.view_any"]}>
      <PostPreviewScreen />
    </RequireStaff>
  );
}
