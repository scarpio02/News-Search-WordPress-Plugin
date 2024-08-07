<?php
/*
Plugin Name: News Search
Description: WordPress plugin with a shortcode [news_search] to display a search form that will search the Google News XML/RSS Feed.
Author:      Santiago Carpio
Version:     1.0
License:     GPLv2 or later

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version
2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
with this program. If not, visit: https://www.gnu.org/licenses/
*/


// exit if file is called directly
if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

// Include Bootstrap 5 for styling
function news_search_bootstrap() {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), null);
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'news_search_bootstrap');

function display_search_bar() {
    echo '<div class="container-sm mt-3">';
    echo '<form class="mb-5" method="GET">';
        echo '<div class="row">';
            echo '<div class="col-sm-3"></div>';
            echo '<div class="col-sm-6">';
                echo '<div class="input-group">';
                    echo '<input type="text" name="search" class="form-control" placeholder="Search for news...">';
                    echo '<input class="btn btn-primary" type="submit" value="Search">';
                echo '</div>';
            echo '</div>';
            echo '<div class="col-sm-3"></div>';
        echo '</div>';
    echo '</form>';
}

function search_news() {
    // Grab query and sanitize
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '*';
    $search_encoded = urlencode($search);

    // Get news feed
    $url = 'https://news.google.com/rss/search?q=' . $search_encoded . '&hl=en-US&gl=US&ceid=US:en';
    $response = wp_remote_get($url);

    // If error, display message
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) == 404) {
        ob_start();

        // Display search bar
        display_search_bar();

        echo '<div class="row"><div class="col-sm-3"></div><div class="col-sm-6">';
        echo 'No results found.';
        echo '</div><div class="col-sm-3"></div></div>';
        echo '</div>';
    }
    else {
        $body = wp_remote_retrieve_body($response);
        $news_data = simplexml_load_string($body);

        if (!$news_data) {
            return 'Unable to parse search results.';
        }

        // Store news items
        $news_items = array();
        foreach ($news_data->channel->item as $item) {
            $news_items[] = $item;
        }

        // Calculate number of pages for pagination
        $num_items = count($news_items);
        $num_rows = 10;
        $num_pages = ceil($num_items / $num_rows);
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

        ob_start();
        // Display search bar
        display_search_bar();

        // Display results
        echo '<div class="row"><div class="col-sm-1"></div><div class="col-sm-10">';
        if ($num_pages > 1) {
            // Construct page link for pagination buttons
            $page_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";   
            $page_url .= $_SERVER['HTTP_HOST'];
            $query_parameters = $_SERVER['REQUEST_URI'];
            if (isset($_GET['page'])) {
                $query_parameters = str_replace('&page=' . $page, '', $query_parameters);
            }
            $page_url .= $query_parameters;

            // Display pagination buttons
            echo '<ul class="pagination">';
            for ($i = 1; $i <= $num_pages; $i++ ) {
                if ($i == $page) {
                    echo '<li class="page-item active"><a class="page-link" href="' . $page_url . '&page=' . $i . '">' . $i . '</a></li>';
                }
                else {
                    echo '<li class="page-item"><a class="page-link" href="' . $page_url . '&page=' . $i . '">' . $i . '</a></li>';
                }
            }
            echo '</ul>';

        }
        echo '<div class="list-group">';
        for ($i = ($page-1) * $num_rows; $i < $num_items && $i < ($page * $num_rows); $i++) {
            echo '<a href="' . esc_url($news_items[$i]->link) . '" class="list-group-item list-group-item-action" target="_blank">' . esc_html($news_items[$i]->title) . '</a>';
        }
        echo '</div>';
        echo '</div><div class="col-sm-1"></div></div>';
        echo '</div>';
    }

    return ob_get_clean();
}
add_shortcode( 'news_search', 'search_news' );
