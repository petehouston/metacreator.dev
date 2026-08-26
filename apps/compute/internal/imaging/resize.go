// Package imaging produces the media-library variants described in docs/10.
package imaging

import (
	"bytes"
	"encoding/base64"
	"fmt"
	"image"
	_ "image/gif"
	"image/jpeg"
	"image/png"
	"io"

	"github.com/disintegration/imaging"
)

// Preset describes one variant to produce.
type Preset struct {
	Label  string `json:"label"`
	Width  int    `json:"width"`
	Height int    `json:"height"`
	// Fit "contain" preserves aspect ratio; "cover" crops to exact dimensions,
	// which is what social cards require.
	Fit     string `json:"fit"`
	Format  string `json:"format"`
	Quality int    `json:"quality"`
}

// Variant is one produced image.
type Variant struct {
	Label  string `json:"label"`
	Format string `json:"format"`
	Width  int    `json:"width"`
	Height int    `json:"height"`
	Bytes  int    `json:"bytes"`
	Data   string `json:"data"`
}

// DefaultPresets mirrors the variant table in docs/10-media-library.md.
func DefaultPresets() []Preset {
	return []Preset{
		{Label: "thumb", Width: 240, Fit: "contain", Format: "jpeg", Quality: 78},
		{Label: "card", Width: 720, Fit: "contain", Format: "jpeg", Quality: 82},
		{Label: "hero", Width: 1600, Fit: "contain", Format: "jpeg", Quality: 84},
		// The OG image is a hard 1200×630 crop and stays JPEG: it is the format every
		// social crawler reliably understands.
		{Label: "og", Width: 1200, Height: 630, Fit: "cover", Format: "jpeg", Quality: 85},
	}
}

// Generate decodes the source once and derives every requested variant from it.
func Generate(source io.Reader, presets []Preset) ([]Variant, error) {
	decoded, _, err := image.Decode(source)
	if err != nil {
		return nil, fmt.Errorf("could not decode image: %w", err)
	}

	variants := make([]Variant, 0, len(presets))

	for _, preset := range presets {
		resized := apply(decoded, preset)

		encoded, err := encode(resized, preset)
		if err != nil {
			return nil, err
		}

		bounds := resized.Bounds()

		variants = append(variants, Variant{
			Label:  preset.Label,
			Format: preset.Format,
			Width:  bounds.Dx(),
			Height: bounds.Dy(),
			Bytes:  len(encoded),
			Data:   base64.StdEncoding.EncodeToString(encoded),
		})
	}

	return variants, nil
}

func apply(source image.Image, preset Preset) image.Image {
	if preset.Fit == "cover" && preset.Width > 0 && preset.Height > 0 {
		return imaging.Fill(source, preset.Width, preset.Height, imaging.Center, imaging.Lanczos)
	}

	bounds := source.Bounds()

	// Never upscale. Enlarging a small image produces a bigger file that looks worse
	// — the caller almost certainly wants the original at that point.
	if preset.Width > 0 && bounds.Dx() <= preset.Width {
		return source
	}

	return imaging.Resize(source, preset.Width, 0, imaging.Lanczos)
}

func encode(img image.Image, preset Preset) ([]byte, error) {
	var buffer bytes.Buffer

	quality := preset.Quality
	if quality <= 0 || quality > 100 {
		quality = 82
	}

	switch preset.Format {
	case "png":
		encoder := png.Encoder{CompressionLevel: png.BestCompression}

		if err := encoder.Encode(&buffer, img); err != nil {
			return nil, fmt.Errorf("png encode failed: %w", err)
		}
	default:
		if err := jpeg.Encode(&buffer, img, &jpeg.Options{Quality: quality}); err != nil {
			return nil, fmt.Errorf("jpeg encode failed: %w", err)
		}
	}

	return buffer.Bytes(), nil
}
