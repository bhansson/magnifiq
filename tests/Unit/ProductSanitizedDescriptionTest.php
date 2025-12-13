<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductSanitizedDescriptionTest extends TestCase
{
    public function test_preserves_allowed_html_tags(): void
    {
        $product = new Product;
        $product->description = '<p>This is a <strong>bold</strong> and <em>italic</em> paragraph.</p>';

        $this->assertEquals(
            '<p>This is a <strong>bold</strong> and <em>italic</em> paragraph.</p>',
            $product->sanitized_description
        );
    }

    public function test_strips_dangerous_script_tags(): void
    {
        $product = new Product;
        $product->description = '<p>Hello</p><script>alert("xss")</script><p>World</p>';

        $this->assertEquals(
            '<p>Hello</p>alert("xss")<p>World</p>',
            $product->sanitized_description
        );
    }

    public function test_strips_iframe_tags(): void
    {
        $product = new Product;
        $product->description = '<p>Content</p><iframe src="evil.com"></iframe>';

        $this->assertEquals(
            '<p>Content</p>',
            $product->sanitized_description
        );
    }

    public function test_removes_javascript_href_attributes(): void
    {
        $product = new Product;
        $product->description = '<a href="javascript:alert(1)">Click me</a>';

        $this->assertEquals(
            '<a href="#">Click me</a>',
            $product->sanitized_description
        );
    }

    public function test_removes_onclick_event_handlers(): void
    {
        $product = new Product;
        $product->description = '<p onclick="alert(1)">Paragraph</p>';

        $this->assertEquals(
            '<p>Paragraph</p>',
            $product->sanitized_description
        );
    }

    public function test_preserves_safe_links(): void
    {
        $product = new Product;
        $product->description = '<a href="https://example.com">Visit us</a>';

        $this->assertEquals(
            '<a href="https://example.com">Visit us</a>',
            $product->sanitized_description
        );
    }

    public function test_handles_lists(): void
    {
        $product = new Product;
        $product->description = '<ul><li>Item 1</li><li>Item 2</li></ul>';

        $this->assertEquals(
            '<ul><li>Item 1</li><li>Item 2</li></ul>',
            $product->sanitized_description
        );
    }

    public function test_returns_null_for_empty_description(): void
    {
        $product = new Product;
        $product->description = null;

        $this->assertNull($product->sanitized_description);
    }

    public function test_returns_null_for_blank_description(): void
    {
        $product = new Product;
        $product->description = '';

        $this->assertNull($product->sanitized_description);
    }
}
