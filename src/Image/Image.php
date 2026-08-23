<?php

declare(strict_types=1);

namespace Switch\Foundation\Image;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

class Image
{
    private ?GdImage $resource = null;
    private int $width = 0;
    private int $height = 0;
    private string $mime = 'image/jpeg';
    private ?string $sourcePath = null;

    public function __construct(?GdImage $resource = null, ?string $sourcePath = null)
    {
        if ($resource !== null) {
            $this->resource = $resource;
            $this->width = imagesx($resource);
            $this->height = imagesy($resource);
        }
        $this->sourcePath = $sourcePath;
    }

    public static function load(mixed $source): self
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD PHP extension is required for Image processing.");
        }

        // 1. PSR-7 UploadedFileInterface
        if ($source instanceof \Psr\Http\Message\UploadedFileInterface) {
            return self::fromUploadedFile($source);
        }

        // 2. PSR-7 StreamInterface
        if ($source instanceof \Psr\Http\Message\StreamInterface) {
            return self::fromString((string) $source);
        }

        // 3. String file path
        if (is_string($source) && file_exists($source)) {
            $info = @getimagesize($source);
            if ($info === false) {
                throw new InvalidArgumentException("File at [{$source}] is not a valid readable image.");
            }

            $mime = $info['mime'];
            $resource = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($source),
                'image/png' => imagecreatefrompng($source),
                'image/gif' => imagecreatefromgif($source),
                'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false,
                'image/avif' => function_exists('imagecreatefromavif') ? imagecreatefromavif($source) : false,
                default => false,
            };

            if ($resource === false) {
                throw new RuntimeException("Failed to read image from [{$source}] with mime [{$mime}].");
            }

            // Preserve PNG / WebP alpha channel
            imagealphablending($resource, true);
            imagesavealpha($resource, true);

            $image = new self($resource, $source);
            $image->mime = $mime;
            return $image;
        }

        // 4. Binary image string
        if (is_string($source)) {
            return self::fromString($source);
        }

        throw new InvalidArgumentException("Invalid image source provided. Expected file path, UploadedFile, or image binary data.");
    }

    public static function fromUploadedFile(\Psr\Http\Message\UploadedFileInterface $file): self
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Cannot load image due to upload error code: " . $file->getError());
        }

        $stream = $file->getStream();
        $stream->rewind();
        $binary = $stream->getContents();

        $image = self::fromString($binary);
        if ($file->getClientMediaType()) {
            $image->mime = $file->getClientMediaType();
        }
        return $image;
    }

    public static function fromString(string $binary): self
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD PHP extension is required for Image processing.");
        }

        $resource = @imagecreatefromstring($binary);
        if ($resource === false) {
            throw new InvalidArgumentException("Failed to decode image from binary data.");
        }

        imagealphablending($resource, true);
        imagesavealpha($resource, true);

        return new self($resource);
    }

    public static function create(int $width, int $height, string $bgColor = '#ffffff'): self
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD PHP extension is required for Image processing.");
        }

        $resource = imagecreatetruecolor($width, $height);
        if ($resource === false) {
            throw new RuntimeException("Failed to create blank image canvas.");
        }

        $rgb = self::hexToRgb($bgColor);
        $color = imagecolorallocate($resource, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($resource, 0, 0, $color);

        return new self($resource);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function resize(int $width, int $height, bool $keepAspectRatio = true): static
    {
        if ($this->resource === null) return $this;

        $targetWidth = $width;
        $targetHeight = $height;

        if ($keepAspectRatio) {
            $ratio = min($width / $this->width, $height / $this->height);
            $targetWidth = (int) round($this->width * $ratio);
            $targetHeight = (int) round($this->height * $ratio);
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $this->preserveAlpha($canvas);

        imagecopyresampled(
            $canvas,
            $this->resource,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $this->width, $this->height
        );

        $this->resource = $canvas;
        $this->width = $targetWidth;
        $this->height = $targetHeight;

        return $this;
    }

    public function crop(int $x, int $y, int $width, int $height): static
    {
        if ($this->resource === null) return $this;

        $canvas = imagecreatetruecolor($width, $height);
        $this->preserveAlpha($canvas);

        imagecopyresampled(
            $canvas,
            $this->resource,
            0, 0, $x, $y,
            $width, $height,
            $width, $height
        );

        $this->resource = $canvas;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function fit(int $width, int $height): static
    {
        if ($this->resource === null) return $this;

        $srcRatio = $this->width / $this->height;
        $targetRatio = $width / $height;

        if ($srcRatio > $targetRatio) {
            // Source is wider, crop sides
            $cropWidth = (int) round($this->height * $targetRatio);
            $cropHeight = $this->height;
            $x = (int) round(($this->width - $cropWidth) / 2);
            $y = 0;
        } else {
            // Source is taller, crop top/bottom
            $cropWidth = $this->width;
            $cropHeight = (int) round($this->width / $targetRatio);
            $x = 0;
            $y = (int) round(($this->height - $cropHeight) / 2);
        }

        $this->crop($x, $y, $cropWidth, $cropHeight);
        $this->resize($width, $height, false);

        return $this;
    }

    public function rotate(float $degrees, string $bgColor = '#000000'): static
    {
        if ($this->resource === null) return $this;

        $rgb = self::hexToRgb($bgColor);
        $color = imagecolorallocatealpha($this->resource, $rgb[0], $rgb[1], $rgb[2], 127);
        $rotated = imagerotate($this->resource, $degrees * -1, $color);

        if ($rotated !== false) {
            imagealphablending($rotated, true);
            imagesavealpha($rotated, true);
            $this->resource = $rotated;
            $this->width = imagesx($rotated);
            $this->height = imagesy($rotated);
        }

        return $this;
    }

    public function flip(string $mode = 'horizontal'): static
    {
        if ($this->resource === null) return $this;

        $gdMode = match (strtolower($mode)) {
            'vertical', 'v' => IMG_FLIP_VERTICAL,
            'both' => IMG_FLIP_BOTH,
            default => IMG_FLIP_HORIZONTAL,
        };

        imageflip($this->resource, $gdMode);
        return $this;
    }

    public function grayscale(): static
    {
        if ($this->resource !== null) {
            imagefilter($this->resource, IMG_FILTER_GRAYSCALE);
        }
        return $this;
    }

    public function brightness(int $level): static
    {
        if ($this->resource !== null) {
            imagefilter($this->resource, IMG_FILTER_BRIGHTNESS, max(-255, min(255, $level)));
        }
        return $this;
    }

    public function contrast(int $level): static
    {
        if ($this->resource !== null) {
            imagefilter($this->resource, IMG_FILTER_CONTRAST, max(-100, min(100, $level * -1)));
        }
        return $this;
    }

    public function watermark(string|self $watermark, string $position = 'bottom-right', int $opacity = 80, int $padding = 15): static
    {
        if ($this->resource === null) return $this;

        $wm = is_string($watermark) ? self::load($watermark) : $watermark;
        if ($wm->resource === null) return $this;

        $wmW = $wm->getWidth();
        $wmH = $wm->getHeight();

        [$x, $y] = match ($position) {
            'top-left' => [$padding, $padding],
            'top-right' => [$this->width - $wmW - $padding, $padding],
            'bottom-left' => [$padding, $this->height - $wmH - $padding],
            'center' => [(int) round(($this->width - $wmW) / 2), (int) round(($this->height - $wmH) / 2)],
            default => [$this->width - $wmW - $padding, $this->height - $wmH - $padding],
        };

        imagecopymerge($this->resource, $wm->resource, $x, $y, 0, 0, $wmW, $wmH, $opacity);

        return $this;
    }

    public function save(string $targetPath, int $quality = 85): bool
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        return match ($ext) {
            'webp' => imagewebp($this->resource, $targetPath, $quality),
            'avif' => function_exists('imageavif') ? imageavif($this->resource, $targetPath, $quality) : imagewebp($this->resource, $targetPath, $quality),
            'png' => imagepng($this->resource, $targetPath, (int) round((100 - $quality) / 10)),
            'gif' => imagegif($this->resource, $targetPath),
            default => imagejpeg($this->resource, $targetPath, $quality),
        };
    }

    public function encode(string $format = 'webp', int $quality = 85): string
    {
        ob_start();
        match (strtolower($format)) {
            'webp' => imagewebp($this->resource, null, $quality),
            'png' => imagepng($this->resource, null, (int) round((100 - $quality) / 10)),
            'gif' => imagegif($this->resource),
            default => imagejpeg($this->resource, null, $quality),
        };
        return (string) ob_get_clean();
    }

    private function preserveAlpha(GdImage $canvas): void
    {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
