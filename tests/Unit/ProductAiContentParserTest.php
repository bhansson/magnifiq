<?php

use App\Support\ProductAiContentParser;

test('it parses usps from multiline string', function () {
    $input = "- Fast setup\n* Durable frame\n2. Extended warranty";

    $result = ProductAiContentParser::parseUsps($input);

    expect($result)->toBe(['Fast setup', 'Durable frame', 'Extended warranty']);
});

test('it parses faq from structured string', function () {
    $input = <<<FAQ
Q: How long is the warranty?
A: All purchases include a two-year warranty.

Q: Can I return the product?
A: Returns are accepted within 30 days.
FAQ;

    $result = ProductAiContentParser::parseFaq($input);

    expect($result)->toHaveCount(2);
    expect($result[0]['question'])->toBe('How long is the warranty?');
    expect($result[0]['answer'])->toBe('All purchases include a two-year warranty.');
    expect($result[1]['question'])->toBe('Can I return the product?');
    expect($result[1]['answer'])->toBe('Returns are accepted within 30 days.');
});
