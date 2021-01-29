<?php

/**
 * This file is part of WpAlgolia plugin.
 * (c) Antoine Girard for Mill3 Studio <antoine@mill3.studio>
 * @version 0.0.7
 */

namespace WpAlgolia;

class AlgoliaIndex
{
    /**
     * Algolia Index settings.
     *
     * @var array
     */
    public $index_settings;

    /**
     * Algolia index instance.
     *
     * @var object
     */
    public $index = null;

    /**
     * The index indice name in Algolia.
     *
     * @var string
     */
    public $index_name;

    /**
     * Plugin Client instance passed to class.
     *
     * @var object
     */
    public $algolia_client;

    /**
     * Index custom post type.
     *
     * @var string
     */
    public $post_type;

    /**
     * Log instance.
     *
     * @var object
     */
    public $log;

    /**
     * parent class instance reference.
     *
     * @var object
     */
    public $instance;

    /**
     * Constructor.
     *
     * @param string $index_name
     * @param object $algolia_client
     * @param array  $index_settings
     * @param mixed  $log
     * @param mixed  $instance
     */
    public function __construct($index_name, $algolia_client, $index_settings = array('config' => array()), $log, $instance)
    {
        $this->index_name = $index_name;
        $this->algolia_client = $algolia_client;
        $this->index_settings = $index_settings;
        $this->post_type = $index_settings['post_type'];
        $this->log = $log;
        $this->instance = $instance;
        $this->run();
    }

    /**
     * Main run command.
     */
    public function run()
    {
        $this->init_index();
    }

    /**
     * Save or update post object to Algolia.
     *
     * @param int    $postID
     * @param object $post
     */
    public function save($postID, $post)
    {
        $data = array(
            'objectID'                => $this->index_objectID($post->ID),
            'post_title'              => $post->post_title,
            'post_thumbnail'          => get_the_post_thumbnail_url($post, 'largest'),
            'post_thumbnail_sizes'    => array(
                'small'    => get_the_post_thumbnail_url($post, 'small'),
                'large'    => get_the_post_thumbnail_url($post, 'large'),
                'largest'  => get_the_post_thumbnail_url($post, 'largest'),
                'full'     => get_the_post_thumbnail_url($post, 'full'),
            ),
            'date'              => get_the_date('c', $post->ID),
            'timestamp'         => get_the_date('U', $post->ID),
            'excerpt'           => $post->post_excerpt ? $this->prepareTextContent($post->post_excerpt) : $this->prepareTextContent($post->post_content, 125),
            'content'           => $this->prepareTextContent($post->post_content),
            'url'               => get_permalink($post->ID),
            'post_type'         => get_post_type($post->ID),
        );

        // attach post locale, set to defaults if not set yet
        if (\function_exists('pll_get_post_language')) {
            $post_locale = pll_get_post_language($post->ID);
            $data['locale'] = $post_locale ?: pll_default_language('slug');
        }

        // handle extra fields formating per post-type
        if (method_exists($this->instance, 'extraFields')) {
            $data = $this->instance->extraFields($data, $post, $this->log);
        }

        // append each custom field values
        foreach ($this->index_settings['acf_fields'] as $key => $field) {
            // get ACF data
            if (\is_array($field)) {
                $field_data = get_field($key, $postID);
            } else {
                $field_data = get_field($field, $postID);
            }

            if ($field_data) {
                if (\is_array($field)) {
                    foreach ($field as $field_label) {
                        if (1 === \count($field)) {
                            $data[$key] = $this->prepareTextContent($field_data->$field_label);
                        } else {
                            $data["{$key}_{$field_label}"] = $this->prepareTextContent($field_data->$field_label);
                        }
                    }
                } else {
                    $data[$field] = $this->prepareTextContent($field_data);
                }
            }
        }

        // append extra taxonomies
        foreach ($this->index_settings['taxonomies'] as $key => $taxonomy) {
            $term_name = null;
            $is_array = false;
            $acf_fields = null;

            // check if taxonomy is defined as an array in post-type register config
            if (\is_array($taxonomy)) {
                $is_array = true;
                $term_name = $taxonomy['name'];
                $acf_fields = is_array($taxonomy['acf_fields']) ? $taxonomy['acf_fields'] : null;
            } else {
                $term_name = $taxonomy;
            }

            // get all post terms
            $terms = wp_get_post_terms($post->ID, $term_name);

            foreach ($terms as $key => $term) {
                // when taxonomy is defined as an array
                if($is_array && $acf_fields) {
                    // add term name to record
                    $data[$term_name][$key]['name'] = $term->name;

                    // get each registered acf field on the same term
                    foreach ($acf_fields as $acf_field) {
                        $data[$term_name][$key][$acf_field] = get_field($acf_field, $term);
                    }
                } else {
                    $data[$term_name][$key] = $term->name;
                }
            }
        }

        // clear cache keys
        $this->delete_cached_object($postID);
        $this->delete_cached_query($data['locale']);

        // save object
        $this->index->saveObject($data);
    }

    /**
     * Delete object in Alglia index, clear cache object.
     *
     * @param [type] $postID
     */
    public function delete($postID)
    {
        $this->index->deleteObject($this->index_objectID($postID));
        $this->delete_cached_object($postID);
    }

    /**
     * Check if a record already exists in Algolia index.
     *
     * @param int $postID
     */
    public function record_exist($postID)
    {
        $objectID = $this->index_objectID($postID);
        $cached_object = $this->get_cached_object($postID);

        if (!$cached_object) {
            try {
                $object = $this->index->getObject($objectID, array('attributesToRetrieve' => 'objectID'));
                $this->cache_object($postID);

                return true;
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            return $cached_object;
        }
    }

    /**
     * Cache an Algolia index.
     */
    public function cache_index()
    {
        set_transient($this->cache_key_index(), $this->index, 3600);
    }

    public function get_cached_index()
    {
        return get_transient($this->cache_key_index());
    }

    public function cache_object($postID)
    {
        set_transient($this->cache_key_object($postID), true, 3600);
    }

    public function get_cached_object($postID)
    {
        return get_transient($this->cache_key_object($postID));
    }

    public function cache_query($data, $locale)
    {
        set_transient($this->cache_key_query($locale), $data, 600);
    }

    public function get_cached_query($locale)
    {
        return get_transient($this->cache_key_query($locale));
    }

    public function delete_cached_query($locale)
    {
        // echo $this->get_cached_query($locale);
        delete_transient($this->cache_key_query($locale));
    }

    public function delete_cached_object($postID)
    {
        delete_transient($this->cache_key_object($postID));
    }

    public function cache_key_index()
    {
        return "wp-algolia-index-initialized-{$this->index_name}";
    }

    public function cache_key_query($locale)
    {
        return "wp-algolia-query-{$this->index_name}-{$locale}";
    }

    public function cache_key_object($postID)
    {
        return "wp-algolia-index-object-{$this->post_type}-{$postID}";
    }

    /**
     * Init Algolia index and set its settings.
     *
     * @param mixed $settings
     */
    public function init_index($settings = false)
    {
        $cached_index = $this->get_cached_index();

        // cache found, set stored value to class
        if ($cached_index) {
            // $this->log->info('Use cached index');

            $this->index = $cached_index;

            if ($settings) {
                $this->index->setSettings($this->index_settings['config']);
            }

            return;
        }

        // no cache is set, create index with settings

        // init index in Algolia
        $this->index = $this->algolia_client->initIndex($this->index_name);

        // set settings
        if ($settings) {
            $this->index->setSettings($this->index_settings['config']);
        }

        // trigger cache storage
        $this->cache_index();
    }

    /**
     * Create a unique ID string for Algolia objectID field.
     *
     * @param int $postID
     *
     * @return string
     */
    private function index_objectID($postID)
    {
        return implode('_', array($this->index_settings['post_type'], $postID));
    }

    /**
     * Strig tags from raw field.
     *
     * @param string $content
     * @param mixed  $trimLength
     *
     * @return string
     */
    private function prepareTextContent($content, $trimLength = 0)
    {
        if ('string' !== \gettype($content)) {
            return $content;
        }

        $content = strip_tags($content);
        $content = preg_replace('#[\n\r]+#s', ' ', $content);

        $content = $trimLength > 0 ? mb_strimwidth($content, 0, $trimLength, '...') : $content;

        return $content;
    }
}
