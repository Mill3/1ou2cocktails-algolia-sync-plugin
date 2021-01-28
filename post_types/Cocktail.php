<?php

/**
 * This file is part of WpAlgolia plugin.
 * (c) Antoine Girard for Mill3 Studio <antoine@mill3.studio>
 * @version 0.0.7
 */

namespace WpAlgolia\Register;

use WpAlgolia\RegisterAbstract as WpAlgoliaRegisterAbstract;
use WpAlgolia\RegisterInterface as WpAlgoliaRegisterInterface;

class Cocktail extends WpAlgoliaRegisterAbstract implements WpAlgoliaRegisterInterface
{
    public $searchable_fields = array('post_title', 'content', 'post_thumbnail');

    public $acf_fields = array('subtitle', 'ingredients', 'steps');

    public $taxonomies = array('spirit', 'flavour', 'cocktail-types', 'appearance', 'thematic', 'taste', 'occasion', 'tool');

    public function __construct($post_type, $index_name, $algolia_client)
    {
        $index_config = array(
            'acf_fields'        => $this->acf_fields,
            'taxonomies'        => $this->taxonomies,
            'post_type'         => $post_type,
            'hidden_flag_field' => 'search_hidden',
            'config'            => array(
                'searchableAttributes'  => $this->searchableAttributes(),
                'customRanking'         => array('asc(post_title)'),
                // 'queryLanguages'        => array('fr', 'en'),
            ),
            array(
               'forwardToReplicas' => true,
            ),
        );

        parent::__construct($post_type, $index_name, $algolia_client, $index_config);
    }

    public function searchableAttributes()
    {
        return array_merge($this->searchable_fields, $this->acf_fields, $this->taxonomies);
    }

    // implement any special data handling for post type here
    public function extraFields($data, $post) {

        // set permalink as formatted url value
        // $link_to_permalink = get_field('link_to_permalink', $postID);
        // if ($link_to_permalink) {
        //     $permalink = get_permalink($postID);
        //     $data['formated_url'] = "href='{$permalink}'";
        // }
        return $data;
    }
}
