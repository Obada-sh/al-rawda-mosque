<?php

namespace App\Services\Export\Pptx;

/**
 * How the finished presentation plays.
 */
final readonly class SlideShowOptions
{
    /**
     * @param  int|null  $secondsPerSlide  seconds before the next slide comes by itself; null keeps the timing of the template
     * @param  bool  $loop  start again after the last slide, until Esc is pressed
     * @param  bool  $opensAsShow  a file that starts playing when it is opened (.ppsx) instead of one opened for editing (.pptx)
     */
    public function __construct(
        public ?int $secondsPerSlide = null,
        public bool $loop = true,
        public bool $opensAsShow = false,
    ) {}

    public function extension(): string
    {
        return $this->opensAsShow ? 'ppsx' : 'pptx';
    }

    public function mimeType(): string
    {
        return $this->opensAsShow
            ? 'application/vnd.openxmlformats-officedocument.presentationml.slideshow'
            : 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    }
}
