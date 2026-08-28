import { describe, expect, it } from "vitest";

import { faqEntries } from "@/lib/faq";

describe("faqEntries", () => {
  // The regression this exists for: tools seed `question`/`answer`, the tool page
  // read `q`/`a`, and every tool in the catalog rendered three empty accordions
  // and a FAQPage schema full of `undefined` — silently, because empty strings
  // render as nothing rather than as an error.
  it("reads the long field names tools are stored with", () => {
    expect(faqEntries([{ question: "Why?", answer: "Because." }])).toEqual([
      { question: "Why?", answer: "Because." },
    ]);
  });

  it("reads the short field names older block documents use", () => {
    expect(faqEntries([{ q: "Why?", a: "Because." }])).toEqual([
      { question: "Why?", answer: "Because." },
    ]);
  });

  it("drops an entry with neither, rather than rendering an empty accordion", () => {
    expect(faqEntries([{}, { question: "Kept" }])).toEqual([{ question: "Kept", answer: "" }]);
  });

  it("treats null and undefined as no entries", () => {
    expect(faqEntries(null)).toEqual([]);
    expect(faqEntries(undefined)).toEqual([]);
  });
});
