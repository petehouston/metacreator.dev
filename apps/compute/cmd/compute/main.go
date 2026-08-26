// Command compute is the CPU- and IO-bound sidecar for the Laravel API.
//
// It exists because some tool work is wasteful in PHP: image processing, and
// especially high-concurrency HTTP fan-out where checking 200 links would otherwise
// need 200 PHP processes. See docs/adr/0005-go-service-for-cpu-bound-work.md.
//
// It is deliberately stateless and speaks a tiny HTTP contract, so it can be scaled,
// replaced or removed without touching any domain logic.
package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/go-chi/chi/v5"

	"github.com/metacreator/compute/internal/handler"
	"github.com/metacreator/compute/internal/middleware"
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	addr := env("COMPUTE_ADDR", ":8090")
	secret := os.Getenv("COMPUTE_SHARED_SECRET")

	if secret == "" {
		// The service binds to a private interface, but "private" is a deployment
		// assumption, not a guarantee. Refusing to start without a secret makes a
		// misconfiguration loud instead of silently exposing an SSRF proxy.
		logger.Error("COMPUTE_SHARED_SECRET is required")
		os.Exit(1)
	}

	router := chi.NewRouter()
	router.Use(middleware.RequestID, middleware.Logging, middleware.Recover)

	// Unauthenticated: the orchestrator needs to probe this before it has anything
	// to send, and it exposes nothing.
	router.Get("/healthz", handler.Health)

	router.Group(func(r chi.Router) {
		r.Use(middleware.SharedSecret(secret))
		r.Post("/v1/images/resize", handler.ResizeImage)
		r.Post("/v1/images/probe", handler.ProbeImage)
		r.Post("/v1/links/check", handler.CheckLinks)
	})

	server := &http.Server{
		Addr:              addr,
		Handler:           router,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       30 * time.Second,
		WriteTimeout:      120 * time.Second,
		IdleTimeout:       90 * time.Second,
		MaxHeaderBytes:    1 << 16,
	}

	go func() {
		logger.Info("compute service listening", "addr", addr)

		if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Error("server failed", "error", err)
			os.Exit(1)
		}
	}()

	// Graceful shutdown: a deploy must not sever in-flight image processing.
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop

	logger.Info("shutting down")

	ctx, cancel := context.WithTimeout(context.Background(), 25*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		logger.Error("graceful shutdown failed", "error", err)
	}
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}
