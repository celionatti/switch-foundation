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

    public function load(mixed $source): Image
    {
        return Image::load($source);
    }

    public function create(int $width, int $height, string $bgColor = '#ffffff'): Image
    {
        return Image::create($width, $height, $bgColor);
    }
}
