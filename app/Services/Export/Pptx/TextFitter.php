<?php

namespace App\Services\Export\Pptx;

use DOMElement;
use DOMXPath;

/**
 * Makes a text that was written into a shape small enough for it: a long
 * name is drawn a little smaller instead of running out of its box.
 *
 * - Only text that would not fit is touched, and it is never made
 *   smaller than SMALLEST of its size.
 * - A text that may wrap takes a second line when the box is tall enough
 *   for it at the new size, if that lets it stay larger than on one line.
 * - PowerPoint does not shrink text by itself when it opens a file, so
 *   the new sizes are written into the slide.
 */
final class TextFitter
{
    /** The text is never made smaller than this share of its size. */
    public const SMALLEST = 0.6;

    /** How much smaller each try is, as a share of the size. */
    private const STEP = 0.01;

    private const EMU_PER_POINT = 12700;

    /** The height of a line, in ems: the Arabic fonts in use draw their lines this tall. */
    private const LINE_HEIGHT = 1.35;

    /** The space PowerPoint leaves around the text when the shape does not say. */
    private const DEFAULT_INSETS = ['lIns' => 91440, 'tIns' => 45720, 'rIns' => 91440, 'bIns' => 45720];

    /** What is said to fit keeps a little room to spare. */
    private const SAFETY = 1.05;

    private const FULL_SCALE = 100000;

    public function __construct(private readonly DOMXPath $xpath) {}

    /**
     * Fits the text of the shape, or of each shape inside it when it is a group.
     */
    public function shrink(DOMElement $element): void
    {
        foreach ($this->nodes('descendant-or-self::p:sp[p:txBody]', $element) as $shape) {
            $this->shrinkShape($shape);
        }
    }

    private function shrinkShape(DOMElement $shape): void
    {
        $size = $this->sizeOf($shape);
        $body = $this->nodes('./p:txBody/a:bodyPr', $shape)[0] ?? null;

        if ($size === null || $body === null) {
            return;
        }

        $width = $size[0] - $this->inset($body, 'lIns') - $this->inset($body, 'rIns');
        $height = $size[1] - $this->inset($body, 'tIns') - $this->inset($body, 'bIns');

        // A run without a size of its own takes it from the layout: its size is not known here.
        if ($width <= 0 || $this->nodes('./p:txBody//a:r[not(a:rPr/@sz)]', $shape) !== []) {
            return;
        }

        $autofit = $this->nodes('./a:normAutofit', $body)[0] ?? null;
        $fontScale = $autofit !== null && $autofit->hasAttribute('fontScale')
            ? (int) $autofit->getAttribute('fontScale') / self::FULL_SCALE
            : 1.0;

        // Each written paragraph: its text and the size (in points) it is drawn at now.
        $paragraphs = [];

        foreach ($this->nodes('./p:txBody/a:p', $shape) as $paragraph) {
            $runs = $this->nodes('./a:r', $paragraph);
            $text = trim(implode('', array_map(fn (DOMElement $run): string => $run->textContent, $runs)));

            if ($text !== '') {
                $points = max(array_map(fn (DOMElement $run): int => (int) $this->nodes('./a:rPr', $run)[0]->getAttribute('sz'), $runs)) / 100;
                $paragraphs[] = [$text, $points * $fontScale];
            }
        }

        $wraps = $body->getAttribute('wrap') !== 'none';
        $factor = $this->largestFitting($paragraphs, $width, $height, $wraps);

        if ($factor >= 1.0) {
            return;
        }

        // The shrinking PowerPoint stored in the template becomes part of the written sizes.
        if ($autofit !== null) {
            $autofit->removeAttribute('fontScale');
            $autofit->removeAttribute('lnSpcReduction');
        }

        foreach ($this->nodes('./p:txBody//*[self::a:rPr or self::a:endParaRPr or self::a:defRPr][@sz]', $shape) as $properties) {
            $properties->setAttribute('sz', (string) max(100, (int) round((int) $properties->getAttribute('sz') * $fontScale * $factor)));
        }
    }

    /**
     * The largest share of their size the paragraphs fit at, from 1 down
     * to SMALLEST; SMALLEST when even that is too large.
     *
     * @param  list<array{string, float}>  $paragraphs  text and size in points
     */
    private function largestFitting(array $paragraphs, float $width, float $height, bool $wraps): float
    {
        if ($paragraphs === []) {
            return 1.0;
        }

        for ($factor = 1.0; $factor > self::SMALLEST; $factor -= self::STEP) {
            if ($this->fits($paragraphs, $factor, $width, $height, $wraps)) {
                return $factor;
            }
        }

        return self::SMALLEST;
    }

    /**
     * @param  list<array{string, float}>  $paragraphs
     */
    private function fits(array $paragraphs, float $factor, float $width, float $height, bool $wraps): bool
    {
        $largest = max(array_map(fn (array $paragraph): float => $paragraph[1], $paragraphs)) * $factor;

        // The lines the box is tall enough for; one line is always allowed, however tight the box.
        $lines = max(1, (int) floor($height / ($largest * self::LINE_HEIGHT * self::EMU_PER_POINT)));
        $linesEach = $wraps ? max(1, $lines - count($paragraphs) + 1) : 1;

        foreach ($paragraphs as [$text, $points]) {
            $room = $width / ($points * $factor * self::EMU_PER_POINT * self::SAFETY);

            if ($this->linesNeeded($text, $room) > $linesEach) {
                return false;
            }
        }

        return true;
    }

    /**
     * How many lines a text takes in a box of the given width (in ems),
     * broken between words the way PowerPoint breaks it. A word wider
     * than the box never fits.
     */
    private function linesNeeded(string $text, float $room): int
    {
        $space = TextWidth::ems(' ');
        $lines = 1;
        $line = null;

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            $wide = TextWidth::ems($word);

            if ($wide > $room) {
                return PHP_INT_MAX;
            }

            if ($line === null) {
                $line = $wide;
            } elseif ($line + $space + $wide <= $room) {
                $line += $space + $wide;
            } else {
                $lines++;
                $line = $wide;
            }
        }

        return $lines;
    }

    /**
     * The size of the shape on the slide: a shape inside a group is drawn
     * at the size of the group.
     *
     * @return array{float, float}|null width and height
     */
    private function sizeOf(DOMElement $shape): ?array
    {
        $extent = $this->nodes('./p:spPr/a:xfrm/a:ext', $shape)[0] ?? null;

        if ($extent === null) {
            return null;
        }

        $width = (float) $extent->getAttribute('cx');
        $height = (float) $extent->getAttribute('cy');

        foreach ($this->nodes('ancestor::p:grpSp', $shape) as $group) {
            $outer = $this->nodes('./p:grpSpPr/a:xfrm/a:ext', $group)[0] ?? null;
            $inner = $this->nodes('./p:grpSpPr/a:xfrm/a:chExt', $group)[0] ?? null;

            if ($outer !== null && $inner !== null && (int) $inner->getAttribute('cx') > 0 && (int) $inner->getAttribute('cy') > 0) {
                $width *= (int) $outer->getAttribute('cx') / (int) $inner->getAttribute('cx');
                $height *= (int) $outer->getAttribute('cy') / (int) $inner->getAttribute('cy');
            }
        }

        return [$width, $height];
    }

    private function inset(DOMElement $body, string $side): int
    {
        return $body->hasAttribute($side) ? (int) $body->getAttribute($side) : self::DEFAULT_INSETS[$side];
    }

    /**
     * @return list<DOMElement>
     */
    private function nodes(string $expression, DOMElement $context): array
    {
        return array_values(array_filter(
            iterator_to_array($this->xpath->query($expression, $context) ?: [], false),
            fn ($node): bool => $node instanceof DOMElement,
        ));
    }
}
