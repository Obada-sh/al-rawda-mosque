<?php

namespace App\Services\Export\Pptx;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * A presentation used as a template: one of its slides carries
 * placeholders such as {{circle}} and is repeated, filled in, once for
 * every item to show. The other slides stay where they are.
 *
 * Rendering never changes the template itself, so one template can
 * produce many presentations.
 */
final class SlideDeckTemplate
{
    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const CONTENT_TYPES = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const PRESENTATION = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    private const OFFICE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const SLIDE_RELATIONSHIP = self::OFFICE_RELATIONSHIPS.'/slide';

    private const NOTES_RELATIONSHIP = self::OFFICE_RELATIONSHIPS.'/notesSlide';

    private const PROPERTIES_RELATIONSHIP = self::OFFICE_RELATIONSHIPS.'/presProps';

    private const SLIDE_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.slide+xml';

    private const PROPERTIES_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.presProps+xml';

    private const SHOW_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.slideshow.main+xml';

    private const DECK_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml';

    /** Slots are numbered placeholders: {{name1}}, {{score1}}, {{place1}}, {{name2}}... */
    private const SLOT_PLACEHOLDER = '/^[a-z_]+([0-9]+)$/';

    /**
     * @param  string  $repeatedSlide  part name of the slide that carries the placeholders
     * @param  list<string>  $placeholders  the placeholders of that slide
     */
    private function __construct(
        private readonly PptxPackage $package,
        private readonly string $repeatedSlide,
        private readonly array $placeholders,
    ) {}

    /**
     * @throws InvalidSlideTemplate when the file is not a presentation, or has no slide with placeholders
     */
    public static function open(string $path): self
    {
        $package = PptxPackage::open($path);

        foreach (self::slidesInOrder($package) as $slide) {
            $placeholders = (new SlideEditor($package->get($slide)))->placeholders();

            if ($placeholders !== []) {
                return new self($package, $slide, $placeholders);
            }
        }

        throw InvalidSlideTemplate::noPlaceholders();
    }

    /**
     * @return list<string> lower-cased, without braces
     */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /**
     * How many numbered slots the repeated slide has: 3 when its highest
     * numbered placeholder is {{name3}} or {{score3}}.
     */
    public function slots(): int
    {
        $slots = 0;

        foreach ($this->placeholders as $placeholder) {
            if (preg_match(self::SLOT_PLACEHOLDER, $placeholder, $number) === 1) {
                $slots = max($slots, (int) $number[1]);
            }
        }

        return $slots;
    }

    /**
     * Builds a presentation with one filled copy of the repeated slide per
     * item, in the given order.
     *
     * @param  list<SlideContent>  $slides
     * @return string the bytes of the .pptx (or .ppsx) file
     */
    public function render(array $slides, SlideShowOptions $options): string
    {
        if ($slides === []) {
            throw new InvalidArgumentException('A presentation needs at least one slide.');
        }

        $package = clone $this->package;

        $presentation = self::load($package->get(PptxPackage::PRESENTATION));
        $relationships = self::load($package->get(PptxPackage::PRESENTATION_RELATIONSHIPS));
        $contentTypes = self::load($package->get(PptxPackage::CONTENT_TYPES));

        $templateRelationship = $this->relationshipOf($relationships, $this->repeatedSlide);
        $templateEntry = $this->slideEntry($presentation, $templateRelationship->getAttribute('Id'));

        $templateXml = $package->get($this->repeatedSlide);
        $templateRelationshipsPart = self::relationshipsPartOf($this->repeatedSlide);
        $slideRelationships = $package->has($templateRelationshipsPart)
            ? $this->withoutNotes($package->get($templateRelationshipsPart))
            : null;

        $slideNumber = $this->highestNumber($package->names(), '#^ppt/slides/slide([0-9]+)\.xml$#');
        $relationshipNumber = $this->highestRelationshipNumber($relationships);
        $slideId = $this->highestSlideId($presentation);

        foreach ($slides as $content) {
            $editor = new SlideEditor($templateXml);
            $editor->fill($content->values);

            if ($content->slotPlaces !== null) {
                $editor->keepSlots($content->slotPlaces);
            }

            $part = 'ppt/slides/slide'.(++$slideNumber).'.xml';
            $package->put($part, $editor->toXml());

            if ($slideRelationships !== null) {
                $package->put(self::relationshipsPartOf($part), $slideRelationships);
            }

            $this->declareContentType($contentTypes, $part, self::SLIDE_TYPE);

            $relationship = $relationships->createElementNS(self::RELATIONSHIPS, 'Relationship');
            $relationship->setAttribute('Id', 'rId'.(++$relationshipNumber));
            $relationship->setAttribute('Type', self::SLIDE_RELATIONSHIP);
            $relationship->setAttribute('Target', 'slides/'.basename($part));
            $relationships->documentElement->appendChild($relationship);

            // The copies stand where the template slide stood.
            $entry = $presentation->createElementNS(self::PRESENTATION, 'p:sldId');
            $entry->setAttribute('id', (string) (++$slideId));
            $entry->setAttributeNS(self::OFFICE_RELATIONSHIPS, 'r:id', $relationship->getAttribute('Id'));
            $templateEntry->parentNode->insertBefore($entry, $templateEntry);
        }

        $this->removeTemplateSlide($package, $contentTypes, $templateRelationship, $templateEntry);

        if ($options->opensAsShow) {
            $this->declareContentType($contentTypes, PptxPackage::PRESENTATION, self::SHOW_TYPE);
        } else {
            $this->declareContentType($contentTypes, PptxPackage::PRESENTATION, self::DECK_TYPE);
        }

        if ($options->loop) {
            $this->loopForever($package, $relationships, $contentTypes, $relationshipNumber);
        }

        $package->put(PptxPackage::PRESENTATION, (string) $presentation->saveXML());
        $package->put(PptxPackage::PRESENTATION_RELATIONSHIPS, (string) $relationships->saveXML());
        $package->put(PptxPackage::CONTENT_TYPES, (string) $contentTypes->saveXML());

        if ($options->secondsPerSlide !== null) {
            foreach (self::slidesInOrder($package) as $slide) {
                $editor = new SlideEditor($package->get($slide));
                $editor->advanceAfter($options->secondsPerSlide);
                $package->put($slide, $editor->toXml());
            }
        }

        $this->recordCounts($package);

        return $package->toBinary();
    }

    /**
     * The slide parts in the order the presentation shows them.
     *
     * @return list<string>
     */
    private static function slidesInOrder(PptxPackage $package): array
    {
        $presentation = self::load($package->get(PptxPackage::PRESENTATION));
        $relationships = self::load($package->get(PptxPackage::PRESENTATION_RELATIONSHIPS));

        $targets = [];

        foreach (self::query($relationships, '/r:Relationships/r:Relationship') as $relationship) {
            if ($relationship->getAttribute('Type') === self::SLIDE_RELATIONSHIP) {
                $targets[$relationship->getAttribute('Id')] = self::resolve('ppt/', $relationship->getAttribute('Target'));
            }
        }

        $slides = [];

        foreach (self::query($presentation, '/p:presentation/p:sldIdLst/p:sldId') as $entry) {
            $part = $targets[$entry->getAttributeNS(self::OFFICE_RELATIONSHIPS, 'id')] ?? null;

            if ($part !== null && $package->has($part)) {
                $slides[] = $part;
            }
        }

        return $slides;
    }

    private function relationshipOf(DOMDocument $relationships, string $part): DOMElement
    {
        foreach (self::query($relationships, '/r:Relationships/r:Relationship') as $relationship) {
            if (
                $relationship->getAttribute('Type') === self::SLIDE_RELATIONSHIP
                && self::resolve('ppt/', $relationship->getAttribute('Target')) === $part
            ) {
                return $relationship;
            }
        }

        throw InvalidSlideTemplate::notAPresentation();
    }

    private function slideEntry(DOMDocument $presentation, string $relationshipId): DOMElement
    {
        foreach (self::query($presentation, '/p:presentation/p:sldIdLst/p:sldId') as $entry) {
            if ($entry->getAttributeNS(self::OFFICE_RELATIONSHIPS, 'id') === $relationshipId) {
                return $entry;
            }
        }

        throw InvalidSlideTemplate::notAPresentation();
    }

    /**
     * The relationships of a copied slide: everything the template slide
     * points to (its layout, its pictures) except its notes page, which
     * belongs to the template slide alone.
     */
    private function withoutNotes(string $relationshipsXml): string
    {
        $relationships = self::load($relationshipsXml);

        foreach (self::query($relationships, '/r:Relationships/r:Relationship') as $relationship) {
            if ($relationship->getAttribute('Type') === self::NOTES_RELATIONSHIP) {
                $relationship->parentNode?->removeChild($relationship);
            }
        }

        return (string) $relationships->saveXML();
    }

    /**
     * Takes the template slide (and its notes page) out of the
     * presentation: only its filled copies are shown.
     */
    private function removeTemplateSlide(PptxPackage $package, DOMDocument $contentTypes, DOMElement $relationship, DOMElement $entry): void
    {
        $relationshipsPart = self::relationshipsPartOf($this->repeatedSlide);

        if ($package->has($relationshipsPart)) {
            foreach (self::query(self::load($package->get($relationshipsPart)), '/r:Relationships/r:Relationship') as $own) {
                if ($own->getAttribute('Type') !== self::NOTES_RELATIONSHIP) {
                    continue;
                }

                $notes = self::resolve(dirname($this->repeatedSlide).'/', $own->getAttribute('Target'));

                $package->remove($notes);
                $package->remove(self::relationshipsPartOf($notes));
                $this->forgetContentType($contentTypes, $notes);
            }

            $package->remove($relationshipsPart);
        }

        $package->remove($this->repeatedSlide);
        $this->forgetContentType($contentTypes, $this->repeatedSlide);

        $relationship->parentNode?->removeChild($relationship);
        $entry->parentNode?->removeChild($entry);
    }

    /**
     * Makes the show start again after its last slide, until Esc is pressed.
     */
    private function loopForever(PptxPackage $package, DOMDocument $relationships, DOMDocument $contentTypes, int $relationshipNumber): void
    {
        $part = null;

        foreach (self::query($relationships, '/r:Relationships/r:Relationship') as $relationship) {
            if ($relationship->getAttribute('Type') === self::PROPERTIES_RELATIONSHIP) {
                $part = self::resolve('ppt/', $relationship->getAttribute('Target'));
            }
        }

        if ($part === null || ! $package->has($part)) {
            $part = 'ppt/presProps.xml';
            $package->put($part, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<p:presentationPr xmlns:p="'.self::PRESENTATION.'"/>');
            $this->declareContentType($contentTypes, $part, self::PROPERTIES_TYPE);

            $relationship = $relationships->createElementNS(self::RELATIONSHIPS, 'Relationship');
            $relationship->setAttribute('Id', 'rId'.($relationshipNumber + 1));
            $relationship->setAttribute('Type', self::PROPERTIES_RELATIONSHIP);
            $relationship->setAttribute('Target', 'presProps.xml');
            $relationships->documentElement->appendChild($relationship);
        }

        $properties = self::load($package->get($part));
        $show = self::query($properties, '/p:presentationPr/p:showPr')[0] ?? null;

        if ($show === null) {
            $show = $properties->createElementNS(self::PRESENTATION, 'p:showPr');
            $show->appendChild($properties->createElementNS(self::PRESENTATION, 'p:present'));
            $show->appendChild($properties->createElementNS(self::PRESENTATION, 'p:sldAll'));

            // The schema wants it before the recent colours and the extensions.
            $after = self::query($properties, '/p:presentationPr/p:clrMru | /p:presentationPr/p:extLst')[0] ?? null;
            $properties->documentElement->insertBefore($show, $after);
        }

        $show->setAttribute('loop', '1');
        $show->setAttribute('useTimings', '1');

        $package->put($part, (string) $properties->saveXML());
    }

    private function declareContentType(DOMDocument $contentTypes, string $part, string $type): void
    {
        foreach (self::query($contentTypes, '/c:Types/c:Override') as $override) {
            if ($override->getAttribute('PartName') === '/'.$part) {
                $override->setAttribute('ContentType', $type);

                return;
            }
        }

        $override = $contentTypes->createElementNS(self::CONTENT_TYPES, 'Override');
        $override->setAttribute('PartName', '/'.$part);
        $override->setAttribute('ContentType', $type);
        $contentTypes->documentElement->appendChild($override);
    }

    private function forgetContentType(DOMDocument $contentTypes, string $part): void
    {
        foreach (self::query($contentTypes, '/c:Types/c:Override') as $override) {
            if ($override->getAttribute('PartName') === '/'.$part) {
                $override->parentNode?->removeChild($override);
            }
        }
    }

    /**
     * Keeps the counts of slides and of notes pages in the file's
     * properties true.
     */
    private function recordCounts(PptxPackage $package): void
    {
        $part = 'docProps/app.xml';

        if (! $package->has($part)) {
            return;
        }

        $slides = count(self::slidesInOrder($package));
        $notes = count(preg_grep('#^ppt/notesSlides/[^/]+\.xml$#', $package->names()) ?: []);

        $properties = (string) preg_replace('#<Slides>[0-9]+</Slides>#', "<Slides>{$slides}</Slides>", $package->get($part));
        $properties = (string) preg_replace('#<Notes>[0-9]+</Notes>#', "<Notes>{$notes}</Notes>", $properties);

        $package->put($part, $properties);
    }

    /**
     * @param  list<string>  $names
     */
    private function highestNumber(array $names, string $pattern): int
    {
        $highest = 0;

        foreach ($names as $name) {
            if (preg_match($pattern, $name, $number) === 1) {
                $highest = max($highest, (int) $number[1]);
            }
        }

        return $highest;
    }

    private function highestRelationshipNumber(DOMDocument $relationships): int
    {
        $highest = 0;

        foreach (self::query($relationships, '/r:Relationships/r:Relationship') as $relationship) {
            if (preg_match('/([0-9]+)$/', $relationship->getAttribute('Id'), $number) === 1) {
                $highest = max($highest, (int) $number[1]);
            }
        }

        return $highest;
    }

    /**
     * Slide ids start at 256.
     */
    private function highestSlideId(DOMDocument $presentation): int
    {
        $highest = 255;

        foreach (self::query($presentation, '/p:presentation/p:sldIdLst/p:sldId') as $entry) {
            $highest = max($highest, (int) $entry->getAttribute('id'));
        }

        return $highest;
    }

    /**
     * "ppt/slides/slide1.xml" => "ppt/slides/_rels/slide1.xml.rels"
     */
    private static function relationshipsPartOf(string $part): string
    {
        return dirname($part).'/_rels/'.basename($part).'.rels';
    }

    /**
     * The part a relationship points to, from the folder of the part that owns it.
     */
    private static function resolve(string $folder, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $segments = [];

        foreach (explode('/', $folder.$target) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    private static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw InvalidSlideTemplate::notAPresentation();
        }

        return $document;
    }

    /**
     * @return list<DOMElement>
     */
    private static function query(DOMDocument $document, string $expression): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('r', self::RELATIONSHIPS);
        $xpath->registerNamespace('c', self::CONTENT_TYPES);
        $xpath->registerNamespace('p', self::PRESENTATION);

        return iterator_to_array($xpath->query($expression), false);
    }
}
