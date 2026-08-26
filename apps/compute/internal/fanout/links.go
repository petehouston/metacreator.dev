// Package fanout performs bounded-concurrency network work.
package fanout

import (
	"context"
	"net"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"

	"golang.org/x/sync/errgroup"
)

// Result is one checked URL.
type Result struct {
	URL         string `json:"url"`
	Status      int    `json:"status"`
	OK          bool   `json:"ok"`
	FinalURL    string `json:"final_url,omitempty"`
	Redirects   int    `json:"redirects"`
	DurationMs  int64  `json:"duration_ms"`
	Error       string `json:"error,omitempty"`
	ContentType string `json:"content_type,omitempty"`
}

// CheckURLs resolves every URL concurrently, bounded by `concurrency`.
//
// Results come back in the caller's original order — callers are displaying these in
// a table next to the input list, and a shuffled response would force them to
// re-sort.
func CheckURLs(ctx context.Context, urls []string, concurrency int, timeout time.Duration) []Result {
	results := make([]Result, len(urls))

	group, ctx := errgroup.WithContext(ctx)
	group.SetLimit(concurrency)

	// One client for the whole batch so connections are pooled; creating a client
	// per request defeats keep-alive entirely.
	client := newClient(timeout)

	var mu sync.Mutex

	for index, raw := range urls {
		index, raw := index, raw

		group.Go(func() error {
			result := check(ctx, client, raw, timeout)

			mu.Lock()
			results[index] = result
			mu.Unlock()

			// A dead link is data, not an error — returning one here would cancel
			// the whole batch and discard every other result.
			return nil
		})
	}

	_ = group.Wait()

	return results
}

func check(ctx context.Context, client *http.Client, raw string, timeout time.Duration) Result {
	result := Result{URL: raw}
	started := time.Now()

	parsed, err := url.Parse(strings.TrimSpace(raw))
	if err != nil || (parsed.Scheme != "http" && parsed.Scheme != "https") {
		result.Error = "Not a valid http(s) URL"

		return result
	}

	// Same SSRF rule the PHP side enforces: a user-supplied URL must never be able
	// to point this service at internal infrastructure. A DNS failure is reported as
	// itself rather than as "private address" — telling someone their typo'd domain
	// is a security violation is both wrong and confusing.
	if reason := hostBlockReason(parsed.Hostname()); reason != "" {
		result.Error = reason

		return result
	}

	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	// HEAD first: most servers answer it, and it avoids downloading bodies we do not
	// want. Servers that reject HEAD get a GET retry.
	request, _ := http.NewRequestWithContext(ctx, http.MethodHead, parsed.String(), nil)
	request.Header.Set("User-Agent", "MetaCreatorLinkChecker/1.0 (+https://metacreator.dev/bot)")

	response, err := client.Do(request)

	if err != nil || response.StatusCode == http.StatusMethodNotAllowed || response.StatusCode >= 500 {
		if response != nil {
			_ = response.Body.Close()
		}

		request, _ = http.NewRequestWithContext(ctx, http.MethodGet, parsed.String(), nil)
		request.Header.Set("User-Agent", "MetaCreatorLinkChecker/1.0 (+https://metacreator.dev/bot)")
		response, err = client.Do(request)
	}

	result.DurationMs = time.Since(started).Milliseconds()

	if err != nil {
		result.Error = classify(err)

		return result
	}

	defer func() { _ = response.Body.Close() }()

	result.Status = response.StatusCode
	result.OK = response.StatusCode >= 200 && response.StatusCode < 400
	result.ContentType = response.Header.Get("Content-Type")

	if final := response.Request.URL.String(); final != parsed.String() {
		result.FinalURL = final
	}

	return result
}

func newClient(timeout time.Duration) *http.Client {
	return &http.Client{
		Timeout: timeout,
		Transport: &http.Transport{
			MaxIdleConns:        200,
			MaxIdleConnsPerHost: 8,
			IdleConnTimeout:     30 * time.Second,
			DisableCompression:  false,
			DialContext: (&net.Dialer{
				Timeout:   5 * time.Second,
				KeepAlive: 30 * time.Second,
			}).DialContext,
		},
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 5 {
				return http.ErrUseLastResponse
			}

			// Re-check on every hop: a public URL that redirects to 127.0.0.1 is the
			// standard SSRF bypass, and checking only the first host misses it.
			if hostBlockReason(req.URL.Hostname()) != "" {
				return http.ErrUseLastResponse
			}

			return nil
		},
	}
}

// hostBlockReason returns a human-readable reason the host must not be contacted,
// or an empty string when it is safe.
//
// Every resolved address is checked, not just the first: a hostname can legitimately
// return one public and one private address, and checking only one is a bypass.
func hostBlockReason(host string) string {
	lower := strings.ToLower(host)

	if lower == "localhost" || strings.HasSuffix(lower, ".localhost") ||
		strings.HasSuffix(lower, ".internal") || strings.HasSuffix(lower, ".local") {
		return "Refused: private or loopback address"
	}

	addresses, err := net.LookupIP(host)
	if err != nil {
		return "Domain does not resolve"
	}

	if len(addresses) == 0 {
		return "Domain does not resolve"
	}

	for _, address := range addresses {
		if address.IsLoopback() || address.IsPrivate() || address.IsLinkLocalUnicast() ||
			address.IsLinkLocalMulticast() || address.IsUnspecified() {
			return "Refused: private or loopback address"
		}
	}

	return ""
}

func classify(err error) string {
	message := err.Error()

	switch {
	case strings.Contains(message, "context deadline exceeded"), strings.Contains(message, "Timeout"):
		return "Timed out"
	case strings.Contains(message, "no such host"):
		return "Domain does not resolve"
	case strings.Contains(message, "connection refused"):
		return "Connection refused"
	case strings.Contains(message, "certificate"):
		return "TLS certificate problem"
	default:
		return "Request failed"
	}
}
