<?php

namespace App\Services\Export\Pptx;

/**
 * A rectangle on a slide, in EMU (914400 to the inch), measured from the
 * top-left corner of the slide.
 */
final readonly class Box
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    public function right(): int
    {
        return $this->x + $this->width;
    }

    public function bottom(): int
    {
        return $this->y + $this->height;
    }

    public function hasRoom(): bool
    {
        return $this->width > 0 && $this->height > 0;
    }

    /**
     * The smallest box that holds both.
     */
    public function union(self $other): self
    {
        $x = min($this->x, $other->x);
        $y = min($this->y, $other->y);

        return new self($x, $y, max($this->right(), $other->right()) - $x, max($this->bottom(), $other->bottom()) - $y);
    }
}
