"use client";

import { ArrowLeft, Sparkles } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import * as React from "react";

import { AdminPageHeader, AdminPanel } from "@/components/admin/admin-page";
import { useToast } from "@/components/admin/feedback";
import { Button } from "@/components/ui/button";
import { Field, Input, Select } from "@/components/ui/field";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";

/**
 * Comping one person one tool, on its own page.
 *
 * Deep-linked from a user's detail screen as `/c0ns0le/grants/new?user=…`, so "grant
 * this person something" is one click from the conversation that prompted it — and
 * a link that survives being pasted into a ticket, which a panel over the list
 * never was.
 */
export function GrantEditorScreen() {
  const params = useSearchParams();
  const router = useRouter();
  const { notify, reportError } = useToast();

  const initialUser = params.get("user") ?? "";

  const tools = useAdminResource(
    () => adminApi.tools.list({ "filter[status]": "published", per_page: 100 }),
    [],
  );

  const [user, setUser] = React.useState(initialUser);
  const [tool, setTool] = React.useState("");
  const [reason, setReason] = React.useState("");
  // Defaulted to 30 days rather than to "never": the default is the one everyone
  // takes, and a permanent comp should be a deliberate choice.
  const [duration, setDuration] = React.useState("30");
  const [saving, setSaving] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});

  async function save() {
    setSaving(true);
    setErrors({});

    const expires =
      duration === "never"
        ? null
        : new Date(Date.now() + Number(duration) * 86_400_000).toISOString();

    const result = await adminApi.grants.create({ user, tool, reason, expires_at: expires });

    setSaving(false);

    if (result.ok) {
      notify(`${user} now has access. They have been emailed.`);
      router.push("/c0ns0le/grants");
    } else {
      setErrors(result.error.fieldErrors ?? {});
      reportError(result.error);
    }
  }

  return (
    <>
      <AdminPageHeader
        eyebrow="Product · Tool grants"
        title="Grant access to a tool"
        description="Access given by hand, to one person, for one tool. They are emailed as soon as you save, and the reason is written to the audit log."
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/c0ns0le/grants">
                <ArrowLeft className="size-4" aria-hidden="true" />
                Back to grants
              </Link>
            </Button>

            <Button
              size="sm"
              onClick={() => void save()}
              loading={saving}
              disabled={user === "" || tool === "" || reason.trim() === ""}
            >
              <Sparkles className="size-4" aria-hidden="true" />
              Grant access
            </Button>
          </>
        }
      />

      <AdminPanel title="The grant" description="Every field here ends up on the list">
        <div className="flex max-w-xl flex-col gap-4">
          <Field
            id="grant-user"
            label="Person"
            hint="Their email address, or the public id from a support thread."
            error={errors.user?.[0]}
            required
          >
            {(props) => (
              <Input
                {...props}
                value={user}
                onChange={(event) => setUser(event.target.value)}
                placeholder="someone@example.com"
                autoFocus={initialUser === ""}
              />
            )}
          </Field>

          <Field id="grant-tool" label="Tool" error={errors.tool?.[0]} required>
            {(props) => (
              <Select {...props} value={tool} onChange={(event) => setTool(event.target.value)}>
                <option key="none" value="">
                  Choose a tool…
                </option>
                {(tools.data?.data ?? []).map((entry) => (
                  <option key={entry.id} value={entry.slug}>
                    {entry.name} ({entry.tier})
                  </option>
                ))}
              </Select>
            )}
          </Field>

          <Field
            id="grant-reason"
            label="Reason"
            hint="Written to the audit log and shown on the grants list. Write it for whoever reads it in six months."
            error={errors.reason?.[0]}
            required
          >
            {(props) => (
              <Input
                {...props}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="Apology for the outage on the 14th"
                maxLength={255}
                autoFocus={initialUser !== ""}
              />
            )}
          </Field>

          <Field
            id="grant-duration"
            label="Expires after"
            hint="A grant with no expiry is a subscription nobody is paying for. Prefer a date."
          >
            {(props) => (
              <Select
                {...props}
                value={duration}
                onChange={(event) => setDuration(event.target.value)}
              >
                <option value="7">7 days</option>
                <option value="30">30 days</option>
                <option value="90">90 days</option>
                <option value="365">1 year</option>
                <option value="never">Never — permanent</option>
              </Select>
            )}
          </Field>
        </div>
      </AdminPanel>
    </>
  );
}
