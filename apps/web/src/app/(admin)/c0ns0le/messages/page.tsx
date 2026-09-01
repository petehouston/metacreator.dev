import type { Metadata } from "next";

import { MessagesScreen } from "@/components/admin/screens/messages-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Contact inbox" };

export default function AdminMessagesPage() {
  return (
    <RequireStaff permissions={["tickets.view_any"]}>
      <MessagesScreen />
    </RequireStaff>
  );
}
