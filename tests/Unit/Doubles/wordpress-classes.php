<?php

declare(strict_types=1);

// The WordPress and WooCommerce objects the module reads, as plain data holders. Loaded from a test that runs in
// its own process only, and never over the real classes.

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;

        public string $post_type = 'post';

        public string $post_status = 'publish';

        public string $post_title = '';

        public string $post_content = '';

        public string $post_excerpt = '';

        public string $post_password = '';

        /**
         * @param  array<string, mixed>  $properties
         */
        public function __construct(array $properties = [])
        {
            foreach ($properties as $name => $value) {
                $this->{$name} = $value;
            }
        }
    }
}

if (! class_exists('WP_Term')) {
    class WP_Term
    {
        public int $term_id = 0;

        public string $name = '';

        public string $slug = '';

        public string $taxonomy = '';

        public int $term_taxonomy_id = 0;

        public int $parent = 0;

        /**
         * @param  array<string, mixed>  $properties
         */
        public function __construct(array $properties = [])
        {
            foreach ($properties as $name => $value) {
                $this->{$name} = $value;
            }
        }
    }
}

if (! class_exists('WC_Product')) {
    class WC_Product
    {
        /**
         * @param  array<string, mixed>  $data
         */
        public function __construct(public array $data = []) {}

        public function get_id(): int
        {
            return (int) ($this->data['id'] ?? 0);
        }

        public function get_price(): string
        {
            return (string) ($this->data['price'] ?? '10');
        }

        public function get_price_html(): string
        {
            return (string) ($this->data['price_html'] ?? '');
        }

        public function is_in_stock(): bool
        {
            return (bool) ($this->data['in_stock'] ?? true);
        }

        public function get_total_sales(): int
        {
            return (int) ($this->data['total_sales'] ?? 0);
        }

        public function get_average_rating(): string
        {
            return (string) ($this->data['average_rating'] ?? '0');
        }

        public function get_rating_count(): int
        {
            return (int) ($this->data['rating_count'] ?? 0);
        }

        public function is_on_sale(): bool
        {
            return (bool) ($this->data['on_sale'] ?? false);
        }

        public function get_permalink(): string
        {
            return (string) ($this->data['permalink'] ?? '');
        }

        /** @return list<int> */
        public function get_visible_children(): array
        {
            return $this->data['children'] ?? [];
        }
    }

    class WC_Product_Grouped extends WC_Product {}

    class WC_Product_Variable extends WC_Product
    {
        /** @return array<string, array<int, string>> */
        public function get_variation_prices(bool $for_display = false): array
        {
            return ['price' => $this->data['prices'] ?? []];
        }

        /** @return list<WC_Product_Variation> */
        public function get_available_variations(string $return = 'array'): array
        {
            return $this->data['variations'] ?? [];
        }
    }

    class WC_Product_Variation extends WC_Product
    {
        /** @return array<string, string> */
        public function get_attributes(): array
        {
            return $this->data['attributes'] ?? [];
        }

        public function get_image_id(string $context = 'view'): int
        {
            return (int) ($this->data['image_id'] ?? 0);
        }
    }
}

if (! class_exists('WP_Post_Type')) {
    class WP_Post_Type
    {
        public object $labels;

        public function __construct(string $name = 'Posts', string $all = 'All posts', public bool $public = true, public bool $exclude_from_search = false)
        {
            $this->labels = (object) ['name' => $name, 'all_items' => $all];
        }
    }
}
