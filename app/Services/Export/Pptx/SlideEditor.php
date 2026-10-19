<?php

namespace App\Services\Export\Pptx;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Edits the XML of one slide: fills its placeholders, repeats the cell of
 * a board, removes the shapes that have nothing to show and sets when the
 * next slide comes.
 */
final class SlideEditor
{
    private const PRESENTATION = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    public const DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const COMPATIBILITY = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** {{name}}, {{ name }}, {{name3}}, {{supervisor_line}} */
    private const PLACEHOLDER = '/\{\{\s*([A-Za-z][A-Za-z_]*[0-9]*)\s*\}\}/';

    /**
     * A shape named "slot3", "slot3 name", "Slot 3 - card"... belongs to
     * the third slot; "slot3@1 medal" is its medal for a first place.
     */
    private const SLOT_SHAPE = '/^\s*slot\s*([0-9]+)(?:\s*@\s*([0-9]+))?(?![0-9])(.*)$/isu';

    /** In "slot3@0 medal": the look of the places that have no medal of their own. */
    private const ANY_OTHER_PLACE = 0;

    private DOMDocument $document;

    private DOMXPath $xpath;

    /**
     * @throws InvalidSlideTemplate when the XML cannot be read
     */
    public function __construct(string $xml)
    {
        $this->document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: nothing outside the file is ever fetched.
        $loaded = $this->document->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw InvalidSlideTemplate::notAPresentation();
        }

        $this->xpath = new DOMXPath($this->document);
        $this->xpath->registerNamespace('p', self::PRESENTATION);
        $this->xpath->registerNamespace('a', self::DRAWING);
        $this->xpath->registerNamespace('mc', self::COMPATIBILITY);
    }

    /**
     * The placeholders written on the slide, lower-cased and without braces.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        return $this->placeholdersWithin($this->root());
    }

    /**
     * The placeholders written in one shape (or anywhere under a node).
     *
     * @return list<string> lower-cased, without braces
     */
    public function placeholdersWithin(DOMElement $node): array
    {
        $names = [];

        foreach ($this->paragraphs($node) as $paragraph) {
            $text = implode('', array_map(fn (DOMElement $run): string => $run->textContent, $this->runsOf($paragraph)));

            if (preg_match_all(self::PLACEHOLDER, $text, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $names[strtolower($name)] = true;
                }
            }
        }

        return array_map('strval', array_keys($names));
    }

    /**
     * The board of the slide: the cell that is repeated for every item of a
     * list. Null when the slide has no cell.
     *
     * @param  list<string>  $cellPlaceholders  the placeholders that belong to the cell ({{name}}...), lower-cased
     * @param  Box  $slide  the size of the slides of the presentation
     *
     * @throws InvalidSlideTemplate when the cell cannot be repeated
     */
    public function board(array $cellPlaceholders, Box $slide): ?SlideBoard
    {
        return $cellPlaceholders === [] ? null : SlideBoard::find($this, $this->xpath, $cellPlaceholders, $slide);
    }

    /**
     * Replaces every placeholder by its value. A placeholder without a
     * value becomes empty.
     *
     * PowerPoint often cuts a word into several pieces ("runs") that look
     * the same, so a placeholder may be spread over a few of them: it is
     * found in the text of the whole paragraph, and its value takes the
     * look of the piece in which it starts.
     *
     * @param  array<string, string>  $values  placeholder name => text
     * @param  bool  $shrinkToFit  make the text of each filled shape small enough to stand on one line (see TextFitter)
     */
    public function fill(array $values, bool $shrinkToFit = false): void
    {
        $filled = $this->fillWithin($this->root(), $values);

        if ($shrinkToFit) {
            $fitter = new TextFitter($this->xpath);

            foreach ($filled as $shape) {
                $fitter->shrink($shape);
            }
        }
    }

    /**
     * Fills the placeholders under one node only (one copy of a cell).
     *
     * @param  array<string, string>  $values  placeholder name => text
     * @return list<DOMElement> the shapes whose text was filled
     */
    public function fillWithin(DOMElement $node, array $values): array
    {
        $values = array_change_key_case($values, CASE_LOWER);
        $filled = [];

        foreach ($this->paragraphs($node) as $paragraph) {
            $runs = $this->runsOf($paragraph);
            $texts = array_map(fn (DOMElement $run): string => $run->textContent, $runs);
            $whole = implode('', $texts);

            if (preg_match_all(self::PLACEHOLDER, $whole, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                continue;
            }

            // From the last placeholder to the first, so that the positions of the earlier ones stay true.
            foreach (array_reverse($matches) as $match) {
                [$found, $start] = $match[0];
                $value = (string) ($values[strtolower($match[1][0])] ?? '');

                $texts = $this->replaceSpan($texts, $start, $start + strlen($found), $value);
            }

            foreach ($runs as $index => $run) {
                if ($run->textContent !== $texts[$index]) {
                    $this->setText($run, $texts[$index]);
                }
            }

            $shape = $this->xpath->query('ancestor::p:sp[1]', $paragraph)?->item(0);

            if ($shape instanceof DOMElement && ! in_array($shape, $filled, true)) {
                $filled[] = $shape;
            }
        }

        return $filled;
    }

    /**
     * Keeps, of the shapes named after a slot, only those that have
     * something to show:
     *
     * - "slot3 name" stays when the third slot is filled;
     * - "slot3@1 medal" stays when the third slot holds a first place, as
     *   happens when students are tied;
     * - "slot3@0 medal" stays when the place of the third slot has no medal
     *   of its own ("slot3@... medal").
     *
     * Shapes with any other name are never touched.
     *
     * @param  array<int, int>  $places  slot number => the place it holds; a slot that is not listed is empty
     */
    public function keepSlots(array $places): void
    {
        $shapes = [];
        $looks = [];

        foreach ($this->shapes() as $shape) {
            $name = (string) $this->xpath->evaluate('string(./*[1]/p:cNvPr/@name)', $shape);

            if (preg_match(self::SLOT_SHAPE, $name, $match) !== 1) {
                continue;
            }

            $slot = (int) $match[1];
            $madeFor = $match[2] === '' ? null : (int) $match[2];
            // What the shape is ("medal"): shapes that differ only by their place are looks of the same thing.
            $role = $slot.' '.mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $match[3])));

            $shapes[] = [$shape, $slot, $madeFor, $role];

            if ($madeFor !== null && $madeFor !== self::ANY_OTHER_PLACE) {
                $looks[$role][$madeFor] = true;
            }
        }

        foreach ($shapes as [$shape, $slot, $madeFor, $role]) {
            $place = $places[$slot] ?? null;

            $kept = match (true) {
                $place === null => false,
                $madeFor === null => true,
                $madeFor === self::ANY_OTHER_PLACE => ! isset($looks[$role][$place]),
                default => $madeFor === $place,
            };

            if (! $kept) {
                $shape->parentNode?->removeChild($shape);
            }
        }
    }

    /**
     * Makes the next slide come by itself after the given seconds (a
     * click still works), with a gentle fade.
     */
    public function advanceAfter(int $seconds): void
    {
        $existing = $this->xpath->query('/p:sld/p:transition | /p:sld/mc:AlternateContent[.//p:transition]');

        foreach (iterator_to_array($existing) as $transition) {
            $transition->parentNode?->removeChild($transition);
        }

        $slide = $this->document->documentElement;

        // The schema wants the transition right after the colour map (or after the slide content).
        $before = $this->xpath->query('/p:sld/p:clrMapOvr')->item(0)
            ?? $this->xpath->query('/p:sld/p:cSld')->item(0);

        if ($slide === null || $before === null) {
            return;
        }

        $transition = $this->document->createElementNS(self::PRESENTATION, 'p:transition');
        $transition->setAttribute('spd', 'med');
        $transition->setAttribute('advClick', '1');
        $transition->setAttribute('advTm', (string) ($seconds * 1000));
        $transition->appendChild($this->document->createElementNS(self::PRESENTATION, 'p:fade'));

        $slide->insertBefore($transition, $before->nextSibling);
    }

    public function toXml(): string
    {
        return (string) $this->document->saveXML();
    }

    /**
     * Everything drawn on the slide: shapes, pictures, lines, tables, and
     * the groups of them.
     *
     * @return list<DOMElement>
     */
    private function shapes(): array
    {
        return iterator_to_array($this->xpath->query(
            '//p:cSld/p:spTree//*[p:nvSpPr or p:nvPicPr or p:nvGrpSpPr or p:nvCxnSpPr or p:nvGraphicFramePr]',
        ), false);
    }

    /**
     * @return list<DOMElement>
     */
    private function paragraphs(DOMElement $node): array
    {
        return iterator_to_array($this->xpath->query('descendant-or-self::a:p', $node), false);
    }

    private function root(): DOMElement
    {
        return $this->document->documentElement ?? throw InvalidSlideTemplate::notAPresentation();
    }

    /**
     * The pieces of text of a paragraph, in reading order.
     *
     * @return list<DOMElement>
     */
    private function runsOf(DOMElement $paragraph): array
    {
        return iterator_to_array($this->xpath->query('.//a:t', $paragraph), false);
    }

    /**
     * Replaces the bytes [$start, $end) of the joined text by a value,
     * touching only the pieces the span runs through.
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    private function replaceSpan(array $texts, int $start, int $end, string $value): array
    {
        $offset = 0;
        $written = false;

        foreach ($texts as $index => $text) {
            $from = $offset;
            $to = $offset + strlen($text);
            $offset = $to;

            // The piece lies wholly before or wholly after the span.
            if ($to <= $start || $from >= $end) {
                continue;
            }

            $kept = substr($text, 0, max(0, $start - $from));
            $rest = $to > $end ? substr($text, $end - $from) : '';

            $texts[$index] = $written ? $rest : $kept.$value.$rest;
            $written = true;
        }

        return $texts;
    }

    private function setText(DOMElement $run, string $text): void
    {
        while ($run->firstChild !== null) {
            $run->removeChild($run->firstChild);
        }

        $run->appendChild($this->document->createTextNode($text));

        // Without this, PowerPoint drops spaces at the ends of a piece of text.
        if ($text !== trim($text)) {
            $run->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
    }
}
