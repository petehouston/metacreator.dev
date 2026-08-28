"use client";

import { Sparkles, RotateCcw } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import { actionPending, actionVerb } from "@/lib/tool-action";
import type { JsonSchema, JsonSchemaProperty } from "@/lib/types";

/**
 * Renders a working, accessible form from a tool's JSON Schema.
 *
 * This component is why adding a tool costs zero frontend work: the same schema the
 * server validates against is the one that produces the form, so the two cannot
 * describe different inputs (see docs/08).
 *
 * Layout is a 12-column grid and every field claims a span sized to the *content it
 * will hold*, not to a fixed two-up rhythm. A title field that accepts 300
 * characters gets the full width, because an input the user cannot read their own
 * text in is a broken input regardless of how tidy the grid looks.
 */
export function ToolForm({
  schema,
  example,
  fieldErrors,
  pending,
  toolName,
  toolSlug,
  onSubmit,
}: {
  schema: JsonSchema;
  example?: { input: Record<string, unknown>; note?: string } | null;
  fieldErrors?: Record<string, string[]>;
  pending: boolean;
  /** Used to label the submit button with the tool's own verb. */
  toolName?: string;
  toolSlug?: string;
  onSubmit: (values: Record<string, unknown>, source?: string) => void;
}) {
  const [values, setValues] = React.useState<Record<string, unknown>>(() => defaults(schema));

  const set = React.useCallback((key: string, value: unknown) => {
    setValues((current) => ({ ...current, [key]: value }));
  }, []);

  const fields = Object.entries(schema.properties ?? {});
  const required = new Set(schema.required ?? []);

  const verb = toolName ? actionVerb(toolName, toolSlug) : "Run tool";
  const pendingLabel = toolName ? actionPending(toolName, toolSlug) : "Running…";

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    onSubmit(values);
  }

  /**
   * "Try it with sample data" is the highest-converting element on a tool page:
   * it removes the blank-form problem entirely and shows the output in one click.
   */
  function fillExample() {
    if (!example) return;
    setValues({ ...defaults(schema), ...example.input });
    onSubmit({ ...defaults(schema), ...example.input }, "example");
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6" noValidate>
      <div className="grid grid-cols-1 gap-x-5 gap-y-5 md:grid-cols-12">
        {fields.map(([key, property]) => (
          <SchemaField
            key={key}
            name={key}
            property={property}
            value={values[key]}
            required={required.has(key)}
            error={fieldErrors?.[key]?.[0]}
            onChange={(value) => set(key, value)}
          />
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-3 border-t border-[var(--color-border-subtle)] pt-5">
        <Button type="submit" size="lg" loading={pending}>
          {pending ? pendingLabel : verb}
        </Button>

        {example && (
          <Button
            type="button"
            variant="secondary"
            size="lg"
            onClick={fillExample}
            disabled={pending}
          >
            <Sparkles />
            Try with sample data
          </Button>
        )}

        <Button
          type="button"
          variant="ghost"
          size="lg"
          onClick={() => setValues(defaults(schema))}
          disabled={pending}
        >
          <RotateCcw />
          Reset
        </Button>
      </div>

      {example?.note && (
        <p className="text-xs text-[var(--color-foreground-subtle)]">{example.note}</p>
      )}
    </form>
  );
}

/** Tailwind needs the full class name in the source, so these are spelled out. */
const SPANS: Record<number, string> = {
  3: "md:col-span-3",
  4: "md:col-span-4",
  6: "md:col-span-6",
  12: "md:col-span-12",
};

/**
 * How wide a field needs to be, in twelfths.
 *
 * The rule is "how much text will actually sit in here": a 300-character title
 * needs the full row; a two-digit number needs a quarter. Everything narrower than
 * full width still stacks to full width below `md`, where there is no room to
 * share a row at all.
 */
function spanFor(property: JsonSchemaProperty, kind: FieldKind): number {
  if (kind === "textarea" || kind === "checkbox") return 12;
  if (kind === "select") return 4;
  if (kind === "number") return 3;

  // Strings: a URL is always long, and an unbounded field has to be assumed long.
  if (property.format === "uri" || property.format === "url") return 12;

  const max = property.maxLength;
  if (max === undefined) return 12;
  if (max > 90) return 12;
  if (max > 32) return 6;

  return 4;
}

type FieldKind = "checkbox" | "select" | "textarea" | "number" | "text";

function kindOf(property: JsonSchemaProperty): FieldKind {
  if (property.type === "boolean") return "checkbox";
  if (Array.isArray(property.enum) && property.enum.length > 0) return "select";
  // A one-line field can still need a generous `maxLength` — a YouTube link with
  // a playlist and a timestamp is long, and so is a URL somebody pasted from an
  // ad platform. `x-control` lets a schema say so, because the alternative signal
  // (`format: "uri"`) is validated by Opis on the server and would reject the bare
  // video ID these same fields are built to accept.
  if (property["x-control"] === "text") return "text";
  if (property.format === "uri" || property.format === "url") return "text";
  // A date is a date picker, never a paragraph, whatever its length says.
  if (property.format === "date") return "text";
  // Anything else that can hold a paragraph gets a box you can see a paragraph in.
  if (property.type === "string" && (property.maxLength ?? Infinity) > 300) return "textarea";
  if (property.type === "integer" || property.type === "number") return "number";

  return "text";
}

function SchemaField({
  name,
  property,
  value,
  required,
  error,
  onChange,
}: {
  name: string;
  property: JsonSchemaProperty;
  value: unknown;
  required: boolean;
  error?: string;
  onChange: (value: unknown) => void;
}) {
  const id = `tool-field-${name}`;
  const label = property.title ?? humanise(name);
  const placeholder = property.examples?.[0] != null ? String(property.examples[0]) : undefined;
  const kind = kindOf(property);
  // `min-w-0` is what actually lets a grid child shrink instead of forcing the
  // whole row wider than the container — without it a long placeholder overflows.
  const span = `min-w-0 ${SPANS[spanFor(property, kind)]}`;

  // A boolean is a checkbox with its own inline label; wrapping it in Field would
  // produce two labels for one control.
  if (kind === "checkbox") {
    return (
      <div className={span}>
        <Checkbox
          id={id}
          label={label}
          hint={property.description}
          checked={Boolean(value)}
          onChange={(event) => onChange(event.target.checked)}
        />
      </div>
    );
  }

  const text = typeof value === "string" ? value : "";
  // Only show a counter once it is worth watching — a count that starts at 0/300
  // and never matters is just noise next to the label.
  const counter =
    (kind === "text" || kind === "textarea") && property.maxLength && text.length > 0
      ? `${text.length}/${property.maxLength}`
      : undefined;

  return (
    <Field
      id={id}
      label={label}
      hint={property.description}
      error={error}
      required={required}
      counter={counter}
      className={span}
    >
      {(aria) => {
        if (kind === "select") {
          return (
            <Select
              {...aria}
              value={String(value ?? "")}
              onChange={(event) => onChange(event.target.value)}
            >
              {!required && <option value="">Any</option>}
              {property.enum!.map((option) => (
                <option key={option} value={option}>
                  {humanise(option)}
                </option>
              ))}
            </Select>
          );
        }

        if (kind === "textarea") {
          return (
            <Textarea
              {...aria}
              rows={8}
              placeholder={placeholder}
              maxLength={property.maxLength}
              value={text}
              onChange={(event) => onChange(event.target.value)}
            />
          );
        }

        const numeric = kind === "number";

        return (
          <Input
            {...aria}
            type={
              numeric
                ? "number"
                : property.format === "uri"
                  ? "url"
                  : property.format === "date"
                    ? "date"
                    : "text"
            }
            inputMode={numeric ? "numeric" : undefined}
            step={property.type === "integer" ? 1 : "any"}
            min={property.minimum}
            max={property.maximum}
            maxLength={property.maxLength}
            placeholder={placeholder}
            value={value === undefined || value === null ? "" : String(value)}
            onChange={(event) => {
              const raw = event.target.value;
              // Keep empty as empty rather than coercing to 0 — "0 followers" and
              // "I haven't typed anything yet" are different states.
              onChange(numeric && raw !== "" ? Number(raw) : raw);
            }}
          />
        );
      }}
    </Field>
  );
}

function defaults(schema: JsonSchema): Record<string, unknown> {
  const values: Record<string, unknown> = {};

  for (const [key, property] of Object.entries(schema.properties ?? {})) {
    if (property.default !== undefined) {
      values[key] = property.default;
    } else if (property.type === "boolean") {
      values[key] = false;
    } else {
      values[key] = "";
    }
  }

  return values;
}

function humanise(value: string): string {
  return value
    .replace(/[_.]/g, " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}
