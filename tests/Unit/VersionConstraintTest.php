<?php

namespace Tests\Unit;

use App\Support\Version;
use App\Support\VersionConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VersionConstraintTest extends TestCase
{
    public static function constraints(): array
    {
        return [
            ['1.1.0', '>=1.0.0 <2.0.0', true],
            ['2.0.0', '>=1.0.0 <2.0.0', false],
            ['1.1.0', '^1.0', true],
            ['2.0.0', '^1.0', false],
            ['0.2.9', '^0.2', true],
            ['0.3.0', '^0.2', false],
            ['1.2.9', '~1.2.3', true],
            ['1.3.0', '~1.2.3', false],
            ['1.2.5', '1.2.*', true],
            ['1.3.0', '1.2.*', false],
            ['1.1.0', '>=1.0.0 || >=1.2.0', true],
            ['1.0.0', '<1.0.0', false],
            ['1.0.0', '<=1.0.0', true],
            ['1.0.1', '1.0.0', false],
            ['8.3.36', '>=8.3', true],
            ['8.2.0', '>=8.3', false],
            ['1.1.0', '', true],
            ['1.1.0', '*', true],
            ['1.1.0', 'not-a-version', false],
        ];
    }

    #[DataProvider('constraints')]
    public function test_version_constraints(string $version, string $constraint, bool $expected): void
    {
        $this->assertSame($expected, VersionConstraint::satisfies($version, $constraint));
    }

    public function test_short_versions_are_padded_for_comparison(): void
    {
        $this->assertSame('1.2.0', Version::full('1.2'));
        $this->assertSame('1.2.3', Version::full('v1.2.3'));
        $this->assertTrue(Version::isNewer('1.2.0', '1.1.9'));
        $this->assertFalse(Version::isNewer('1.2.0', '1.2.0'));
    }
}
