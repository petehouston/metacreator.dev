import type { FaqEntry } from "@/lib/types";

/**
 * FAQ entries in one shape, whatever shape they were stored in.
 *
 * Tools seed `{ question, answer }` — the field names the admin editor writes —
 * while some older block documents use the `{ q, a }` short form. Both are in the
 * database already, so reading either is cheaper and safer than a migration, and
 * one normaliser means a page cannot silently render three empty accordions
 * because it guessed the wrong pair of field names.
 */
export function faqEntries(items: FaqEntry[] | null | undefined): { question: string; answer: string }[] {
  return (items ?? [])
    .map((item) => ({
      question: item.question ?? item.q ?? "",
      answer: item.answer ?? item.a ?? "",
    }))
    .filter((item) => item.question !== "" || item.answer !== "");
}
