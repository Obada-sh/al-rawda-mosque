<?php

namespace App\Services\Export\Pptx;

use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * A slide that shows a list as a board: one cell is drawn in the
 * template, and it is repeated once for every item, made as large as the
 * area of the board allows (see BoardLayout).
 *
 * - The cell is every shape whose text holds one of the cell's
 *   placeholders ({{name}}...), and every shape whose name starts with
 *   "cell" ("cell frame", "cell 2"...): a frame or a picture without text
 *   can belong to the cell too.
 * - The area is the shape named "cells", which is not drawn. Without it,
 *   the board runs from the top of the cell to the bottom of the slide,
 *   keeping on every side the margin the cell keeps from the nearer edge.
 * - Each copy is filled with its item, then its text is made smaller
 *   where it would not fit (see TextFitter).
 */
final class SlideBoard
{
    /** "cell", "cell name", "Cell 2 - frame"... but not "cells". */
    private const CELL_SHAPE = '/^\s*cell(?![a-z])/i';

    private const AREA_SHAPE = 'cells';

    /** The space PowerPoint leaves around the text of a shape when the shape does not say. */
    private const DEFAULT_INSETS = ['lIns' => 91440, 'tIns' => 45720, 'rIns' => 91440, 'bIns' => 45720];

    /** The size of a text that does not give its own (18 pt). */
    private const DEFAULT_TEXT_SIZE = 1800;

    private const TOP_LEVEL_SHAPES = '/p:sld/p:cSld/p:spTree/*[self::p:sp or self::p:pic or self::p:grpSp or self::p:cxnSp or self::p:graphicFrame]';

    /**
     * @param  list<DOMElement>  $shapes  the shapes of the cell, in the order they are drawn
     */
    private function __construct(
        private readonly SlideEditor $editor,
        private readonly DOMXPath $xpath,
        private readonly array $shapes,
        private readonly Box $cell,
        private readonly Box $area,
        private readonly ?DOMElement $areaShape,
    ) {}

    /**
     * The board of the slide, or null when the slide has no cell.
     *
     * @param  list<string>  $cellPlaceholders  lower-cased, without braces
     * @param  Box  $slide  the size of the slides of the presentation
     *
     * @throws InvalidSlideTemplate when the cell has no place of its own on the slide, or the area has no room
     *
     * @internal made by SlideEditor::board()
     */
    public static function find(SlideEditor $editor, DOMXPath $xpath, array $cellPlaceholders, Box $slide): ?self
    {
        $shapes = [];
        $areaShape = null;
        $holdsText = false;

        foreach (iterator_to_array($xpath->query(self::TOP_LEVEL_SHAPES) ?: [], false) as $shape) {
            if (! $shape instanceof DOMElement) {
                continue;
            }

            $name = trim((string) $xpath->evaluate('string(./*[1]/p:cNvPr/@name)', $shape));

            if (mb_strtolower($name) === self::AREA_SHAPE) {
                $areaShape ??= $shape;

                continue;
            }

            $holdsCellText = array_intersect($editor->placeholdersWithin($shape), $cellPlaceholders) !== [];
            $holdsText = $holdsText || $holdsCellText;

            if ($holdsCellText || preg_match(self::CELL_SHAPE, $name) === 1) {
                $shapes[] = $shape;
            }
        }

        // Shapes named "cell" without any of the cell's text are only decoration: no cell, no board.
        if (! $holdsText) {
            return null;
        }

        $cell = null;

        foreach ($shapes as $shape) {
            $box = self::boxOf($xpath, $shape) ?? throw InvalidSlideTemplate::noRoom();
            $cell = $cell === null ? $box : $cell->union($box);
        }

        $area = $areaShape === null ? self::areaBelow($cell, $slide) : self::boxOf($xpath, $areaShape);

        if ($cell === null || ! $cell->hasRoom() || $area === null || ! $area->hasRoom()) {
            throw InvalidSlideTemplate::noRoom();
        }

        return new self($editor, $xpath, array_values($shapes), $cell, $area, $areaShape);
    }

    /**
     * How many cells at most one slide can show.
     */
    public function capacity(): int
    {
        return BoardLayout::capacity($this->area, $this->cell->width, $this->cell->height);
    }

    /**
     * Draws one copy of the cell for each item, filled with it, and takes
     * the cell of the template and the area away.
     *
     * @param  list<array<string, string>>  $cells  the values of each copy, the first item first
     * @param  array<string, string>  $shared  values a cell may show too (the name of the circle...)
     *
     * @throws InvalidArgumentException when the items do not fit on one slide (see capacity())
     */
    public function layOut(array $cells, array $shared): void
    {
        $layout = BoardLayout::fit($this->area, $this->cell->width, $this->cell->height, count($cells))
            ?? throw new InvalidArgumentException('There are more cells than one slide can show.');

        $anchor = $this->shapes[0];
        $parent = $anchor->parentNode ?? throw new InvalidArgumentException('The cell is not on the slide.');
        $fitter = new TextFitter($this->xpath);
        $nextId = $this->highestId() + 1;

        foreach ($layout->cells as $index => $box) {
            foreach ($this->shapes as $shape) {
                $copy = $shape->cloneNode(true);

                if (! $copy instanceof DOMElement) {
                    continue;
                }

                $this->place($copy, $box, $layout->scale);
                $this->resize($copy, $layout->scale);
                $nextId = $this->renumber($copy, $nextId);

                $parent->insertBefore($copy, $anchor);

                $this->editor->fillWithin($copy, $cells[$index] + $shared);
                $fitter->shrink($copy);
            }
        }

        foreach ([...$this->shapes, $this->areaShape] as $shape) {
            $shape?->parentNode?->removeChild($shape);
        }
    }

    /**
     * Puts a copy of one shape of the cell where its part of the cell goes.
     */
    private function place(DOMElement $copy, Box $cell, float $scale): void
    {
        $own = self::boxOf($this->xpath, $copy);
        $transform = self::transformOf($this->xpath, $copy);

        if ($own === null || $transform === null) {
            return;
        }

        [$offset, $extent] = $transform;

        $offset->setAttribute('x', (string) (int) round($cell->x + ($own->x - $this->cell->x) * $scale));
        $offset->setAttribute('y', (string) (int) round($cell->y + ($own->y - $this->cell->y) * $scale));
        $extent->setAttribute('cx', (string) max(1, (int) round($own->width * $scale)));
        $extent->setAttribute('cy', (string) max(1, (int) round($own->height * $scale)));
    }

    /**
     * Makes what a group or a frame does not scale by itself as much
     * larger or smaller as the cell: the text and the space around it,
     * the spacing of its lines and the width of its outlines.
     */
    private function resize(DOMElement $copy, float $scale): void
    {
        $scaled = fn (string $value, int $smallest = 0): string => (string) max($smallest, (int) round((int) $value * $scale));

        // A text of an ordinary shape without a size of its own is drawn at 18 pt.
        if ($this->xpath->query('.//p:nvSpPr/p:nvPr/p:ph', $copy)?->length === 0) {
            foreach ($this->elements('.//a:r[not(a:rPr)]', $copy) as $run) {
                $properties = $copy->ownerDocument?->createElementNS(SlideEditor::DRAWING, 'a:rPr');

                if ($properties !== null) {
                    $run->insertBefore($properties, $run->firstChild);
                }
            }

            foreach ($this->elements('.//*[self::a:rPr or self::a:endParaRPr][not(@sz)]', $copy) as $properties) {
                $properties->setAttribute('sz', (string) self::DEFAULT_TEXT_SIZE);
            }
        }

        foreach ($this->elements('.//*[self::a:rPr or self::a:endParaRPr or self::a:defRPr][@sz]', $copy) as $properties) {
            $properties->setAttribute('sz', $scaled($properties->getAttribute('sz'), 100));
        }

        foreach ($this->elements('.//a:bodyPr', $copy) as $body) {
            foreach (self::DEFAULT_INSETS as $side => $default) {
                $body->setAttribute($side, $scaled($body->hasAttribute($side) ? $body->getAttribute($side) : (string) $default));
            }
        }

        foreach ($this->elements('.//a:spcPts[@val]', $copy) as $spacing) {
            $spacing->setAttribute('val', $scaled($spacing->getAttribute('val')));
        }

        foreach ($this->elements('.//a:ln[@w]', $copy) as $line) {
            $line->setAttribute('w', $scaled($line->getAttribute('w'), 1));
        }
    }

    /**
     * Gives every shape of the copy an id of its own on the slide.
     */
    private function renumber(DOMElement $copy, int $nextId): int
    {
        foreach ($this->elements('descendant-or-self::*/p:cNvPr', $copy) as $properties) {
            $properties->setAttribute('id', (string) $nextId++);

            // The id PowerPoint keeps for each shape of the template must stay unique too.
            foreach ($this->elements('./a:extLst', $properties) as $extensions) {
                $properties->removeChild($extensions);
            }
        }

        return $nextId;
    }

    private function highestId(): int
    {
        $highest = 0;

        foreach ($this->elements('//p:cNvPr[@id]', null) as $properties) {
            $highest = max($highest, (int) $properties->getAttribute('id'));
        }

        return $highest;
    }

    /**
     * The area under the cell, down to the bottom of the slide, with the
     * margin the cell keeps from the nearer side of the slide.
     */
    private static function areaBelow(?Box $cell, Box $slide): ?Box
    {
        if ($cell === null) {
            return null;
        }

        $margin = max(0, min($cell->x - $slide->x, $slide->right() - $cell->right()));

        return new Box($slide->x + $margin, $cell->y, $slide->width - 2 * $margin, $slide->bottom() - $margin - $cell->y);
    }

    /**
     * Where a shape stands on the slide; null when it takes its place from
     * the layout instead of giving one of its own.
     */
    private static function boxOf(DOMXPath $xpath, DOMElement $shape): ?Box
    {
        $transform = self::transformOf($xpath, $shape);

        if ($transform === null) {
            return null;
        }

        [$offset, $extent] = $transform;

        return new Box(
            (int) $offset->getAttribute('x'),
            (int) $offset->getAttribute('y'),
            (int) $extent->getAttribute('cx'),
            (int) $extent->getAttribute('cy'),
        );
    }

    /**
     * @return array{DOMElement, DOMElement}|null the offset and the extent of the shape
     */
    private static function transformOf(DOMXPath $xpath, DOMElement $shape): ?array
    {
        $transform = $xpath->query('./p:spPr/a:xfrm | ./p:grpSpPr/a:xfrm | ./p:xfrm', $shape)?->item(0);

        if (! $transform instanceof DOMElement) {
            return null;
        }

        $offset = $xpath->query('./a:off', $transform)?->item(0);
        $extent = $xpath->query('./a:ext', $transform)?->item(0);

        return $offset instanceof DOMElement && $extent instanceof DOMElement ? [$offset, $extent] : null;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(string $expression, ?DOMElement $context): array
    {
        $nodes = $context === null ? $this->xpath->query($expression) : $this->xpath->query($expression, $context);

        return array_values(array_filter(iterator_to_array($nodes ?: [], false), fn ($node): bool => $node instanceof DOMElement));
    }
}
