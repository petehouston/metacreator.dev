package handler

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/metacreator/compute/internal/fanout"
)

type checkLinksRequest struct {
	URLs []string `json:"urls"`
	// Concurrency is clamped server-side; a caller cannot ask us to open 5,000
	// sockets because it passed a large number.
	Concurrency int `json:"concurrency"`
	TimeoutMs   int `json:"timeout_ms"`
}

// CheckLinks is the reason this service exists.
//
// Checking a few hundred URLs is trivial with goroutines and a semaphore, and
// genuinely awkward in PHP, where the equivalent concurrency means a process per
// request. See ADR 0005.
func CheckLinks(w http.ResponseWriter, r *http.Request) {
	var request checkLinksRequest

	if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(&request); err != nil {
		writeError(w, http.StatusBadRequest, "compute.invalid_request", "Malformed JSON body.")

		return
	}

	if len(request.URLs) == 0 {
		writeError(w, http.StatusUnprocessableEntity, "compute.invalid_request", "urls must not be empty.")

		return
	}

	if len(request.URLs) > 500 {
		writeError(w, http.StatusUnprocessableEntity, "compute.too_many", "At most 500 URLs per request.")

		return
	}

	concurrency := clamp(request.Concurrency, 1, 50, 16)
	timeout := time.Duration(clamp(request.TimeoutMs, 500, 15000, 5000)) * time.Millisecond

	results := fanout.CheckURLs(r.Context(), request.URLs, concurrency, timeout)

	writeJSON(w, http.StatusOK, map[string]any{
		"data": map[string]any{
			"results": results,
			"checked": len(results),
		},
	})
}

func clamp(value, min, max, fallback int) int {
	if value <= 0 {
		return fallback
	}

	if value < min {
		return min
	}

	if value > max {
		return max
	}

	return value
}
