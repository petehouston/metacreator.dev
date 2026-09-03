/**
 * The page numbers to draw, with `null` where a run is elided.
 *
 * The blog renders every page it has, which is right for a listing whose length an
 * editor controls. A search does not have that ceiling — a broad query can fill
 * dozens of pages — and sixty numbered chips wrapping over four rows is not
 * navigation, it is a wall. First, last, and a window around the current page is
 * all anyone uses.
 */
export function pageWindow(current: number, last: number): (number | null)[] {
  const SPAN = 2;

  const wanted = new Set<number>([1, last]);

  for (let page = current - SPAN; page <= current + SPAN; page += 1) {
    if (page >= 1 && page <= last) wanted.add(page);
  }

  const pages = [...wanted].sort((a, b) => a - b);
  const entries: (number | null)[] = [];

  pages.forEach((page, index) => {
    const previous = pages[index - 1];

    // A gap of exactly one page is drawn as that page: "1 … 3" is longer than
    // "1 2 3" and hides something the reader could have clicked.
    if (previous !== undefined && page - previous > 1) {
      entries.push(page - previous === 2 ? page - 1 : null);
    }

    entries.push(page);
  });

  return entries;
}
