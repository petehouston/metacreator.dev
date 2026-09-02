# Archetypes

Seven shapes. The archetype is set in `plan/posts.json` before writing starts,
because the shape is decided by what the search result needs, not by how the subject
feels once you are at the keyboard. Every generated brief repeats its archetype's
outline; this file is the reasoning behind each.

---

## `explainer` - for the reader who is checking a fact

The most common archetype here, because most creator queries are questions with an
answer.

1. **The answer, in the first 40 words.** The number, the limit, the yes or no.
2. **Why it is that number.** The mechanism - what the platform is doing and why.
   This is the part that competitors skip and the reason to read us instead.
3. **What it means for your posts.** Translate the fact into a decision.
4. **Edge cases**, in a table where possible.
5. **The tool** that applies the fact, placed at the moment it becomes useful.
6. **FAQ.**

Failure mode: defining the platform before answering the question.

---

## `howto` - for the reader who is stuck

1. **The one-sentence answer**, so someone who already knows can stop reading.
2. **What you need** before starting - and be honest if the answer is "nothing".
3. **Numbered steps.** One action per step. Screenshots where a UI is involved.
4. **The tool that does it for you.** Not a plug: our tools exist because the manual
   route is tedious, so show the manual route first and then save them from it.
5. **What goes wrong** - the two or three failures that actually happen.
6. **FAQ.**

Failure mode: eleven steps where four would do, padded to hit a word count.

---

## `troubleshooting` - for the reader whose thing is broken

1. **The ordered list of causes, most likely first.** This is the whole value: a
   reader in this state wants a triage order, not an essay.
2. **One H2 per cause**, each ending in the check that confirms or eliminates it.
3. **When none of them apply** - what to do next, honestly, including "wait, this is
   a platform-side cache and it clears in N hours".
4. **The tool** that performs the diagnosis.
5. **FAQ.**

Failure mode: listing causes in the order they occurred to the writer.

---

## `benchmarks` - for the reader comparing themselves

1. **The headline numbers**, with the date and the method, above the fold.
2. **Method** - what was measured, sample, and explicitly what was *not* measured.
3. **By tier** - a benchmark that ignores follower count is noise.
4. **How to compare yourself honestly**, then the calculator.
5. **FAQ.**

Failure mode: a number with no source. Cut the post before publishing one.

---

## `comparison` - for the reader choosing

1. **The recommendation, first.** Bury it and the reader leaves to find a page that
   does not.
2. **The criteria, stated before the comparison** - so the verdict is auditable.
3. **Option by option**, same structure each time.
4. **A table.**
5. **When each one wins**, including the case where the reader should pick neither.
6. **FAQ.**

Failure mode: comparing our tool with competitors. We do not write those.

---

## `templates` - for the reader who wants something to copy

1. **The template, immediately**, in a code block or a table, before any preamble.
2. **How to fill each part in**, part by part.
3. **A worked example** with real content.
4. **The generator** that produces it.
5. **FAQ.**

Failure mode: 600 words of philosophy before the thing the title promised.

---

## `pillar` - for the reader mapping a subject

Written after two or three of its spokes exist, so it has something to link to, and
edited every time a new spoke lands.

1. **Scope** - what this covers and what it does not, in two sentences.
2. **How the system actually works.** The one section a spoke cannot carry.
3. **One H2 per spoke**, each a genuine summary (150-250 words) that ends in a link
   down to the full post. A pillar that is only a link list ranks like a link list.
4. **What to do first** - an ordered starting sequence for someone with nothing.
5. **FAQ.**

Failure mode: writing the pillar first, on day one, with nothing to link to.
