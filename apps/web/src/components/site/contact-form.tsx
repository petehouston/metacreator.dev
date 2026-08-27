"use client";

import { Check } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import { Field, Input, Select, Textarea } from "@/components/ui/field";

const TOPICS = [
  { value: "general", label: "General question" },
  { value: "tool_request", label: "Request a tool" },
  { value: "bug", label: "Report a bug" },
  { value: "billing", label: "Billing" },
  { value: "partnership", label: "Partnership or press" },
];

export function ContactForm() {
  const [state, setState] = React.useState<"idle" | "pending" | "done" | "error">("idle");
  const [message, setMessage] = React.useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    if (form.get("company")) return; // honeypot

    setState("pending");

    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(Object.fromEntries(form)),
      });

      if (!response.ok) {
        const payload = await response.json().catch(() => null);
        setState("error");
        setMessage(payload?.error?.message ?? "That didn't send. Please try again.");
        return;
      }

      setState("done");
    } catch {
      setState("error");
      setMessage("We couldn't reach the server. Please email us instead.");
    }
  }

  if (state === "done") {
    return (
      <div role="status" className="flex flex-col items-center gap-3 py-10 text-center">
        <span className="flex size-11 items-center justify-center rounded-full bg-[var(--color-success)]/12 text-[var(--color-success)]">
          <Check className="size-5" />
        </span>
        <h2 className="text-heading-3">Message sent</h2>
        <p className="max-w-sm text-sm text-[var(--color-foreground-muted)]">
          We read everything and normally reply within one working day.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field id="contact-name" label="Your name" required>
          {(aria) => <Input {...aria} name="name" required autoComplete="name" maxLength={120} />}
        </Field>

        <Field id="contact-email" label="Email" required>
          {(aria) => (
            <Input {...aria} name="email" type="email" required autoComplete="email" />
          )}
        </Field>
      </div>

      <Field id="contact-topic" label="What is this about?">
        {(aria) => (
          <Select {...aria} name="topic" defaultValue="general">
            {TOPICS.map((topic) => (
              <option key={topic.value} value={topic.value}>
                {topic.label}
              </option>
            ))}
          </Select>
        )}
      </Field>

      <Field id="contact-subject" label="Subject">
        {(aria) => <Input {...aria} name="subject" maxLength={200} />}
      </Field>

      <Field
        id="contact-message"
        label="Message"
        required
        hint="If you are reporting a bug, the tool name and what you entered helps enormously."
      >
        {(aria) => <Textarea {...aria} name="message" required rows={6} maxLength={5000} />}
      </Field>

      <input
        type="text"
        name="company"
        tabIndex={-1}
        autoComplete="off"
        aria-hidden="true"
        className="absolute size-0 opacity-0"
      />

      {state === "error" && message && (
        <p role="alert" className="text-sm text-[var(--color-danger)]">
          {message}
        </p>
      )}

      <Button type="submit" size="lg" loading={state === "pending"} className="self-start">
        Send message
      </Button>
    </form>
  );
}
