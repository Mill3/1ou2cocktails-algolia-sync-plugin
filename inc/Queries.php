<?php

/**
 * This file is part of WpAlgolia plugin.
 * (c) Antoine Girard for Mill3 Studio <antoine@mill3.studio>
 * @version 0.5.4
 */


namespace WpAlgolia;

class Queries
{
    /**
     * @var Main
     */
    private $instance;

    /**
     * @param InMemoryIndexRepository $indexRepository
     */
    public function __construct(Main $instance)
    {
        $this->instance = $instance;
        $this->register_callbacks();
    }

    protected function register_callbacks()
    {
        add_filter( 'wp_algolia_query', array( $this, 'query' ), 10, 2 );
    }


    /**
     * Get registered post-type from Main $instance
     *
     * @param string $indexName
     * @return class
     */
    public function get_registered_post_type($indexName) {
        try {
            return $this->instance->registered_post_types[$indexName];
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Query index from the Main $instance
     *
     * @param string $post_type
     *
     * @param string $locale
     *
     * @return object
     */
    public function query($post_type = null, $locale = 'en') {
        if( ! $post_type ) return;

        // get registered post type
        $indexInstance = $this->get_registered_post_type($post_type);

        // stops here if not found
        if( ! $indexInstance ) return;

        // query index method in instance
        return $indexInstance->query($locale);
    }


}
