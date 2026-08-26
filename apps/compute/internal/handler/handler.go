// Package handler holds the HTTP surface of the compute service.
//
// The contract is deliberately tiny — JSON in, JSON out — so the coupling between
// PHP and Go stays shallow and either side can be rewritten independently.
package handler

import (
	"encoding/json"
	"net/http"
)

type errorBody struct {
	Code    string `json:"code"`
	Message string `json:"message"`
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func writeError(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, map[string]errorBody{
		"error": {Code: code, Message: message},
	})
}

// Health reports liveness. Intentionally cheap and dependency-free: the orchestrator
// polls it every 30 seconds, and a health check that can fail for reasons unrelated
// to this process is worse than none.
func Health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok", "service": "compute"})
}
