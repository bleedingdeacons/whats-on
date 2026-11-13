<?php

declare(strict_types=1);

use WhatsOn\Events\QueryOptions;

it('uses the defaults when no attributes are set', function (): void {
    $options = QueryOptions::fromAttrs([]);

    expect($options->count)->toBe(QueryOptions::DEFAULT_COUNT)
        ->and($options->categories)->toBe([])
        ->and($options->featuredOnly)->toBeFalse();
});

it('reads Divi module attributes', function (): void {
    $options = QueryOptions::fromAttrs(
        [
            'events' => [
                'advanced' => [
                    'count'        => [ 'desktop' => [ 'value' => '4' ] ],
                    'categories'   => [ 'desktop' => [ 'value' => 'Social, meetings ,social' ] ],
                    'featuredOnly' => [ 'desktop' => [ 'value' => 'on' ] ],
                ],
            ],
        ]
    );

    expect($options->count)->toBe(4)
        ->and($options->categories)->toBe([ 'social', 'meetings' ])
        ->and($options->featuredOnly)->toBeTrue();
});

it('clamps the count', function (mixed $value, int $expected): void {
    expect(QueryOptions::parseCount($value))->toBe($expected);
})->with(
    [
        'zero'        => [ '0', 1 ],
        'negative'    => [ '-5', 1 ],
        'too many'    => [ '500', QueryOptions::MAX_COUNT ],
        'with a unit' => [ '12px', 12 ],
        'empty'       => [ '', QueryOptions::DEFAULT_COUNT ],
    ]
);

it('keeps numeric category slugs as strings', function (): void {
    expect(QueryOptions::parseCategories('2026, events'))->toBe([ '2026', 'events' ]);
});

it('strips anything that is not a slug character from categories', function (): void {
    expect(QueryOptions::parseCategories("a'b, <c>, , d-e_f"))->toBe([ 'ab', 'c', 'd-e_f' ])
        ->and(QueryOptions::parseCategories([ 'x', 'y' ]))->toBe([ 'x', 'y' ])
        ->and(QueryOptions::parseCategories(null))->toBe([]);
});
