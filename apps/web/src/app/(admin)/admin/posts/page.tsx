import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { PostsScreen } from "@/components/admin/screens/posts-screen";

export const metadata: Metadata = { title: "Posts" };

export default function AdminPostsPage() {
  return (
    <RequireStaff permissions={["posts.view_any"]}>
      <PostsScreen />
    </RequireStaff>
  );
}
