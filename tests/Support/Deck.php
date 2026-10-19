<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads a PowerPoint file back, the way PowerPoint would: the slides in
 * the order of the show, their text and the names of their shapes.
 */
final class Deck
{
    private const PRESENTATION = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    private const DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const OFFICE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var array<string, string> part name => contents */
    private array $parts = [];

    public function __construct(string $bytes)
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'deck');
        file_put_contents($path, $bytes);

        try {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new RuntimeException('The bytes are not a ZIP archive.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $this->parts[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
            }

            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public static function fromFile(string $path): self
    {
        return new self((string) file_get_contents($path));
    }

    public function has(string $part): bool
    {
        return isset($this->parts[$part]);
    }

    public function part(string $part): string
    {
        return $this->parts[$part] ?? throw new RuntimeException("The presentation has no part [{$part}].");
    }

    /**
     * @return list<string> the names of all the parts
     */
    public function parts(): array
    {
        return array_keys($this->parts);
    }

    /**
     * @return list<string> the part of each slide, in the order of the show
     */
    public function slides(): array
    {
        $targets = [];

        foreach ($this->query($this->part('ppt/_rels/presentation.xml.rels'), '//r:Relationship') as $relationship) {
            $targets[$relationship->getAttribute('Id')] = 'ppt/'.$relationship->getAttribute('Target');
        }

        $slides = [];

        foreach ($this->query($this->part('ppt/presentation.xml'), '//p:sldIdLst/p:sldId') as $entry) {
            $slides[] = $targets[$entry->getAttributeNS(self::OFFICE_RELATIONSHIPS, 'id')];
        }

        return $slides;
    }

    public function slide(int $index): string
    {
        return $this->part($this->slides()[$index]);
    }

    /**
     * @return list<string> the text of each paragraph of a slide that has some
     */
    public function paragraphs(int $slide): array
    {
        $paragraphs = [];

        foreach ($this->query($this->slide($slide), '//a:p') as $paragraph) {
            if ($paragraph->textContent !== '') {
                $paragraphs[] = $paragraph->textContent;
            }
        }

        return $paragraphs;
    }

    /**
     * @return list<string> the name of every shape of a slide, in drawing order
     */
    public function shapes(int $slide): array
    {
        $names = [];

        foreach ($this->query($this->slide($slide), '//p:cSld/p:spTree//p:cNvPr') as $shape) {
            $names[] = $shape->getAttribute('name');
        }

        return $names;
    }

    /**
     * The shapes drawn straight on a slide (not inside a group), in
     * drawing order: where each stands, what it says and the sizes of its
     * text (in hundredths of a point).
     *
     * @return list<array{name: string, id: int, x: int, y: int, width: int, height: int, text: string, sizes: list<int>}>
     */
    public function drawn(int $slide): array
    {
        $xpath = $this->xpath($this->slide($slide));
        $first = function (string $expression, DOMElement $shape) use ($xpath): ?DOMElement {
            $node = $xpath->query($expression, $shape)?->item(0);

            return $node instanceof DOMElement ? $node : null;
        };

        $shapes = [];

        foreach ($xpath->query('/p:sld/p:cSld/p:spTree/*[*[1]/p:cNvPr]') ?: [] as $shape) {
            $properties = $first('./*[1]/p:cNvPr', $shape);
            $offset = $first('.//a:xfrm/a:off | ./p:xfrm/a:off', $shape);
            $extent = $first('.//a:xfrm/a:ext | ./p:xfrm/a:ext', $shape);
            $sizes = [];

            foreach ($xpath->query('.//a:rPr[@sz]', $shape) ?: [] as $run) {
                $sizes[] = (int) $run->getAttribute('sz');
            }

            $shapes[] = [
                'name' => (string) $properties?->getAttribute('name'),
                'id' => (int) $properties?->getAttribute('id'),
                'x' => (int) $offset?->getAttribute('x'),
                'y' => (int) $offset?->getAttribute('y'),
                'width' => (int) $extent?->getAttribute('cx'),
                'height' => (int) $extent?->getAttribute('cy'),
                'text' => trim($shape->textContent),
                'sizes' => $sizes,
            ];
        }

        return $shapes;
    }

    /**
     * The shapes of a slide that have a name, in drawing order.
     *
     * @return list<array{name: string, id: int, x: int, y: int, width: int, height: int, text: string, sizes: list<int>}>
     */
    public function named(int $slide, string $name): array
    {
        return array_values(array_filter($this->drawn($slide), fn (array $shape): bool => $shape['name'] === $name));
    }

    /**
     * @return list<DOMElement>
     */
    private function query(string $xml, string $expression): array
    {
        return iterator_to_array($this->xpath($xml)->query($expression), false);
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;

        if (! $document->loadXML($xml)) {
            throw new RuntimeException('A part of the presentation is not well-formed XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('p', self::PRESENTATION);
        $xpath->registerNamespace('a', self::DRAWING);
        $xpath->registerNamespace('r', self::RELATIONSHIPS);

        return $xpath;
    }
}
