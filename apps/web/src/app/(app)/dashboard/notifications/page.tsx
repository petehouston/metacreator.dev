import type { Metadata } from "next";

import { NotificationHistory } from "@/components/account/notification-history";
import { AppPageHeader } from "@/components/app/page-header";

export const metadata: Metadata = { title: "Notifications" };

export default function NotificationsPage() {
  return (
    <>
      <AppPageHeader
        eyebrow="Account"
        title="Notifications"
        description="Everything we've told you about."
      />
      <NotificationHistory />
    </>
  );
}
