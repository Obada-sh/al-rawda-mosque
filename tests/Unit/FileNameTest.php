<?php

namespace Tests\Unit;

use App\Support\FileName;
use PHPUnit\Framework\TestCase;

class FileNameTest extends TestCase
{
    public function test_an_ordinary_name_is_kept_as_it_is(): void
    {
        $this->assertSame('أوائل الحلقات - دورة الخريف.pptx', FileName::safe('أوائل الحلقات - دورة الخريف.pptx'));
    }

    public function test_what_windows_refuses_in_a_name_is_replaced(): void
    {
        $this->assertSame('دورة 2026-2027.pptx', FileName::safe('دورة 2026/2027.pptx'));
        $this->assertSame('a-b-c-d-e-f.pptx', FileName::safe('a:b*c?"d"<e>|f.pptx'));
        $this->assertSame('tab-here.ppsx', FileName::safe("tab\there.ppsx"));
        $this->assertSame('back-slash.pptx', FileName::safe('back\\slash.pptx'));
    }

    public function test_dots_and_spaces_at_the_ends_are_dropped(): void
    {
        $this->assertSame('دورة الخريف.pptx', FileName::safe('  دورة   الخريف .. .pptx'));
    }

    public function test_a_name_with_nothing_left_still_names_a_file(): void
    {
        $this->assertSame('file.pptx', FileName::safe(' ... .pptx'));
        $this->assertSame('file', FileName::safe('///'));
    }

    public function test_a_very_long_name_is_cut_and_keeps_its_extension(): void
    {
        $name = FileName::safe(str_repeat('ط', 300).'.pptx');

        $this->assertSame(120 + 5, mb_strlen($name));
        $this->assertStringEndsWith('.pptx', $name);
    }
}
