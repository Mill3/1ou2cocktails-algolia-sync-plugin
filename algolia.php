<?php

/**
 * GitHub Plugin URI:  Mill3/1ou2cocktails-algolia-sync-plugin
 * GitHub Plugin URI:  https://github.com/Mill3/1ou2cocktails-algolia-sync-plugin
 * Plugin Name: 1ou2Cocktails - Algolia Sync
 * Description: Sync data from Wordpress to Algolia
 * Version: 0.6.0
 * Author Name: Mill3 Studio (Antoine Girard)
 *
 * @package Mill3_WP_Algolia_Sync
 */

namespace WpAlgolia;
class Main {

    public $algolia_client;

    public $registered_post_types = array();

    public function __construct($algolia_client) {
        $this->algolia_client = $algolia_client;
    }

    public function run() {
        return $this->register();
    }

    public function search() {
        return null;
    }

    private function register() {
        $this->registered_post_types['post'] = new \WpAlgolia\Register\Post('post', ALGOLIA_PREFIX . 'content', $this->algolia_client);
        $this->registered_post_types['page'] = new \WpAlgolia\Register\Page('page', ALGOLIA_PREFIX . 'content', $this->algolia_client);
        $this->registered_post_types['cocktail'] = new \WpAlgolia\Register\Cocktail('cocktail', ALGOLIA_PREFIX . 'content', $this->algolia_client);
        $this->registered_post_types['eat'] = new \WpAlgolia\Register\Eat('eat', ALGOLIA_PREFIX . 'content', $this->algolia_client);
        $this->registered_post_types['video'] = new \WpAlgolia\Register\Video('video', ALGOLIA_PREFIX . 'content', $this->algolia_client);
    }

}

add_action(
    'plugins_loaded',
    function () {

        if(!defined('ALGOLIA_APPLICATION_ID') || !defined('ALGOLIA_ADMIN_API_KEY')) {
            // Unless we have access to the Algolia credentials, stop here.
            return;
        }

        if(!defined('ALGOLIA_PREFIX')) {
            define('ALGOLIA_PREFIX', 'production_');
        }

        require_once __DIR__ . '/vendor/autoload.php';
        require_once __DIR__ . '/inc/AlgoliaIndex.php';
        require_once __DIR__ . '/inc/RegisterAbstract.php';
        require_once __DIR__ . '/inc/RegisterInterface.php';
        require_once __DIR__ . '/inc/Queries.php';

        // available post types
        require_once __DIR__ . '/post_types/Post.php';
        require_once __DIR__ . '/post_types/Page.php';
        require_once __DIR__ . '/post_types/Cocktail.php';
        require_once __DIR__ . '/post_types/Eat.php';
        require_once __DIR__ . '/post_types/Video.php';

        // client
        $algoliaClient = \Algolia\AlgoliaSearch\SearchClient::create(ALGOLIA_APPLICATION_ID, ALGOLIA_ADMIN_API_KEY);

        // instance with supported post types
        $instance = new \WpAlgolia\Main($algoliaClient);

        // run
        $instance->run();

        // Register Queries class
        $queries = new \WpAlgolia\Queries($instance);

        // WP CLI commands.
        if (defined('WP_CLI') && WP_CLI && $instance) {
            require_once __DIR__ . '/inc/Commands.php';
            $commands = new \WpAlgolia\Commands($instance);
            \WP_CLI::add_command('algolia', $commands);
        }

    }
);
