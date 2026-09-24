<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Indexing\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use WC_Product;
use WC_Product_Grouped;
use WC_Product_Simple;
use WooCommerce;
use WP_Post;
use WP_User;

final class AnonymousIndexingTest extends TestCase
{
    /** Fixed, so a run cut short before its teardown cannot leave an account behind for good. */
    private const string LOGIN = 'meilifacets-administrator-for-the-test';

    /** @var list<WC_Product> */
    private array $created = [];

    private ?int $administrator = null;

    private ?WC_Product_Simple $draft = null;

    private ?WC_Product_Simple $published = null;

    protected function tearDown(): void
    {
        wp_set_current_user(0);

        foreach (array_reverse($this->created) as $product) {
            $product->delete(true);
        }

        if ($this->administrator !== null) {
            require_once ABSPATH.'wp-admin/includes/user.php';
            wp_delete_user($this->administrator);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_indexes_a_grouped_product_as_a_visitor_sees_it_whoever_saves_it(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Grouped products are WooCommerce\'s.');
        }

        $product = $this->groupedWithADraftChild();

        $asVisitor = $this->documentOf($product);
        wp_set_current_user($this->administrator());
        $asAdministrator = $this->documentOf($product);

        $this->assertSame($asVisitor, $asAdministrator);
        $this->assertSame(
            $this->shownPriceOf($this->published),
            $asAdministrator[DocumentField::Price->value][PriceField::Max->value]
        );
        $this->assertStringNotContainsString(
            wp_strip_all_tags(wc_price($this->shownPriceOf($this->draft))),
            wp_strip_all_tags((string) $asAdministrator[DocumentField::Card->value][CardField::Price->value])
        );
    }

    #[Test]
    public function it_signs_out_for_the_read_then_signs_the_administrator_back_in(): void
    {
        $administrator = $this->administrator();
        wp_set_current_user($administrator);

        $this->assertSame(0, new AnonymousVisitor()->during(static fn (): int => get_current_user_id()));
        $this->assertSame($administrator, get_current_user_id());
    }

    #[Test]
    public function it_signs_the_administrator_back_in_when_the_read_fails(): void
    {
        $administrator = $this->administrator();
        wp_set_current_user($administrator);

        try {
            new AnonymousVisitor()->during(static fn (): never => throw new RuntimeException('the read failed'));
        } catch (RuntimeException) {
        }

        $this->assertSame($administrator, get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function documentOf(WC_Product $product): array
    {
        $bridge = $this->app->make(MeiliScoutBridge::class);
        $post = get_post($product->get_id());

        $this->assertInstanceOf(WP_Post::class, $post);

        return [...$bridge->addPrice([], $post), ...$bridge->addCard([], $post)];
    }

    /** The shop's own answer, so the expectations hold whatever it does with taxes. */
    private function shownPriceOf(?WC_Product_Simple $child): float
    {
        $this->assertInstanceOf(WC_Product_Simple::class, $child);

        return (float) wc_get_price_to_display($child);
    }

    private function groupedWithADraftChild(): WC_Product_Grouped
    {
        $this->draft = $this->child('39.90', 'draft');
        $this->published = $this->child('25.50', 'publish');
        $cheapest = $this->child('19.50', 'publish');

        $product = new WC_Product_Grouped;
        $product->set_name('Group with a draft child, for the test');
        $product->set_status('publish');
        $product->set_children([$this->draft->get_id(), $this->published->get_id(), $cheapest->get_id()]);
        $product->save();

        return $this->created[] = $product;
    }

    private function child(string $price, string $status): WC_Product_Simple
    {
        $child = new WC_Product_Simple;
        $child->set_name('Child of a group, for the test');
        $child->set_status($status);
        $child->set_regular_price($price);
        $child->save();

        return $this->created[] = $child;
    }

    private function administrator(): int
    {
        if ($this->administrator !== null) {
            return $this->administrator;
        }

        $leftOver = get_user_by('login', self::LOGIN);

        if ($leftOver instanceof WP_User) {
            require_once ABSPATH.'wp-admin/includes/user.php';
            wp_delete_user($leftOver->ID);
        }

        $administrator = wp_insert_user([
            'user_login' => self::LOGIN,
            'user_email' => 'administrator@meilifacets.test',
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
        ]);

        $this->assertIsInt($administrator, 'The test needs an administrator to index as.');

        return $this->administrator = $administrator;
    }
}
