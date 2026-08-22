<?php

declare(strict_types=1);

namespace Switch\Foundation\Image;

class ImageManager
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function load(string $path): Image
    {
        return Image::load($path);
    }

    public function create(int $width, int $height, string $bgColor = '#ffffff'): Image
    {
        return Image::create($width, $height, $bgColor);
    }
}
