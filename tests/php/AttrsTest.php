<?php

declare(strict_types=1);

use WhatsOn\Support\Attrs;

covers(Attrs::class);

describe('at', function (): void {
    $attrs = [ 'events' => [ 'advanced' => [ 'count' => [ 'desktop' => [ 'value' => '4' ] ] ] ] ];

    it('reads a nested value by dot path', function () use ($attrs): void {
        expect(Attrs::at($attrs, 'events.advanced.count.desktop.value'))->toBe('4')
            ->and(Attrs::desktop($attrs, 'events.advanced.count'))->toBe('4');
    });

    it('returns null for a missing segment or a non-array on the way', function () use ($attrs): void {
        expect(Attrs::at($attrs, 'events.advanced.missing.desktop.value'))->toBeNull()
            ->and(Attrs::at($attrs, 'events.advanced.count.desktop.value.deeper'))->toBeNull()
            ->and(Attrs::at([], 'anything'))->toBeNull();
    });
});

describe('string', function (): void {
    it('converts scalars and defaults everything else', function (mixed $value, string $expected): void {
        expect(Attrs::string($value, 'default'))->toBe($expected);
    })->with([
        'string' => [ 'x', 'x' ],
        'int'    => [ 3, '3' ],
        'bool'   => [ true, '1' ],
        'null'   => [ null, 'default' ],
        'array'  => [ [ 'x' ], 'default' ],
        'object' => [ new stdClass(), 'default' ],
    ]);
});

describe('map', function (): void {
    it('keeps arrays with their keys as strings, and turns anything else into []', function (): void {
        expect(Attrs::map([ 'a' => 1, 2 => 'b' ]))->toBe([ 'a' => 1, '2' => 'b' ])
            ->and(Attrs::map('nope'))->toBe([])
            ->and(Attrs::map(null))->toBe([]);
    });
});
