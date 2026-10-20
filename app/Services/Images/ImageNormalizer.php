<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Re-encodes member uploads: HEIC/PNG/WebP → JPEG, EXIF auto-orient, max
 * 2000 px long edge, and all metadata (incl. GPS) stripped.
 */
class ImageNormalizer
{
    public const MAX_EDGE = 2000;

    /** Detected via finfo on the file contents, never the extension. */
    public const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    public function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $finfo->file($path);
    }

    public function isAllowed(UploadedFile $file): bool
    {
        return in_array($this->detectMime($file->getRealPath()), self::ALLOWED_MIME, true);
    }

    /**
     * @return array{binary: string, width: int, height: int}
     */
    public function normalize(string $path): array
    {
        $manager = new ImageManager(new Driver, autoOrientation: true);

        try {
            $image = $manager->read($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unreadable image', 0, $e);
        }

        $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE);

        /** @var \Imagick $native */
        $native = $image->core()->native();
        $native->stripImage();
        // Flatten transparency (PNG/WebP) onto white for JPEG.
        if ($native->getImageAlphaChannel()) {
            $native->setImageBackgroundColor('white');
            $native->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        }
        $native->setImageColorspace(\Imagick::COLORSPACE_SRGB);

        $binary = (string) $image->encode(new JpegEncoder(quality: 88));

        return ['binary' => $binary, 'width' => $image->width(), 'height' => $image->height()];
    }
}
