<?php

declare(strict_types=1);

namespace Switch\Foundation\Image\Facade;

use Switch\Foundation\Image\Image as ImageInstance;
use Switch\Foundation\Image\ImageManager;

/**
 * Static Image Facade.
 *
 * @method static ImageInstance load(string $path)
 * @method static ImageInstance create(int $width, int $height, string $bgColor = '#ffffff')
 */
class Image
{
    public static function load(string $path): ImageInstance
    {
        return ImageManager::getInstance()->load($path);
    }

    public static function create(int $width, int $height, string $bgColor = '#ffffff'): ImageInstance
    {
        return ImageManager::getInstance()->create($width, $height, $bgColor);
    }
}
