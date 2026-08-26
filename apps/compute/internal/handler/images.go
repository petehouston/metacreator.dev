package handler

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"image"
	"net/http"

	"github.com/metacreator/compute/internal/imaging"
)

type resizeRequest struct {
	// Base64 image bytes. Files are small enough (media library caps at 128 MB, and
	// realistic images are far smaller) that streaming would add complexity for no
	// practical gain.
	Image   string           `json:"image"`
	Presets []imaging.Preset `json:"presets"`
}

// ResizeImage generates every variant of an upload in one pass.
//
// One request rather than one per variant: decoding a large JPEG is the expensive
// part, and doing it four times to produce four sizes is simply wasteful.
func ResizeImage(w http.ResponseWriter, r *http.Request) {
	var request resizeRequest

	if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 64<<20)).Decode(&request); err != nil {
		writeError(w, http.StatusBadRequest, "compute.invalid_request", "Malformed JSON body.")

		return
	}

	raw, err := base64.StdEncoding.DecodeString(request.Image)
	if err != nil {
		writeError(w, http.StatusUnprocessableEntity, "compute.invalid_image", "image must be base64-encoded.")

		return
	}

	if len(request.Presets) == 0 {
		request.Presets = imaging.DefaultPresets()
	}

	variants, err := imaging.Generate(bytes.NewReader(raw), request.Presets)
	if err != nil {
		writeError(w, http.StatusUnprocessableEntity, "compute.decode_failed", err.Error())

		return
	}

	writeJSON(w, http.StatusOK, map[string]any{"data": map[string]any{"variants": variants}})
}

type probeRequest struct {
	Image string `json:"image"`
}

// ProbeImage reads dimensions without decoding the whole image.
//
// image.DecodeConfig reads only the header, which is orders of magnitude cheaper
// than a full decode when all we need is width and height.
func ProbeImage(w http.ResponseWriter, r *http.Request) {
	var request probeRequest

	if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 64<<20)).Decode(&request); err != nil {
		writeError(w, http.StatusBadRequest, "compute.invalid_request", "Malformed JSON body.")

		return
	}

	raw, err := base64.StdEncoding.DecodeString(request.Image)
	if err != nil {
		writeError(w, http.StatusUnprocessableEntity, "compute.invalid_image", "image must be base64-encoded.")

		return
	}

	config, format, err := image.DecodeConfig(bytes.NewReader(raw))
	if err != nil {
		writeError(w, http.StatusUnprocessableEntity, "compute.decode_failed", "Unrecognised image format.")

		return
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"data": map[string]any{
			"width":  config.Width,
			"height": config.Height,
			"format": format,
			"bytes":  len(raw),
		},
	})
}
