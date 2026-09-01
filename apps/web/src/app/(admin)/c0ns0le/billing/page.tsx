import { redirect } from "next/navigation";

/**
 * Billing has four screens now, not one with tabs — so `/c0ns0le/billing` is a
 * section, not a page. Old links and bookmarks land on the plans list rather than
 * on a 404.
 */
export default function AdminBillingPage() {
  redirect("/c0ns0le/billing/plans");
}
