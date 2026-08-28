import * as React from "react";

/**
 * Give a forwarded children array the keys React expects.
 *
 * Children written inline compile to `jsxs`, which marks the list static so React
 * does not ask for keys. A wrapper that receives them as a `children` prop and
 * renders `{children}` loses that marker: React sees a plain array and warns
 * "each child in a list should have a unique key" — pointing at the wrapper's own
 * element, which is why the warning is so hard to trace back to the call site that
 * caused it, and why no call site can fix it.
 *
 * `Children.toArray` assigns the keys itself. It is the API that exists for this,
 * and it is a no-op for a single child.
 */
export function keyed(children: React.ReactNode): React.ReactNode {
  return React.Children.toArray(children);
}
