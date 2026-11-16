<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\ImageFields;

it('writes every image field once one is given', function (): void {
    $fields = ImageFields::withWholeImage(['price' => '39', 'image_url' => 'variant.jpg']);

    expect($fields['image_url'])->toBe('variant.jpg');
    expect($fields['image_srcset'])->toBe('');
    expect($fields['image_sizes'])->toBe('');
    expect($fields['price'])->toBe('39');
});

it('leaves fields without any image as they are', function (): void {
    expect(ImageFields::withWholeImage(['price' => '39']))->toBe(['price' => '39']);
});
