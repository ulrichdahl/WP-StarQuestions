<?php
/*
 * Plugin Name: Star Citizen Questions
 * Plugin URI: https://github.com/ulrichdahl/WP-StarQuestions
 * Description: Collect questions with RSI Handle verification.
 * Version: 1.2.0
 * Author: Ulrich Dahl <ulrich.dahl@gmail.com> / Gemma4
 * Author URI: https://github.com/ulrichdahl/
 * Text Domain: sc-questions
 * Domain Path: /languages
 * License: GPL3
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Questions_Plugin {

    private $menu_slug = 'star-citizen';

    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Registrer Custom Post Type
        add_action('init', array($this, 'register_post_type'));

        // Shortcode
        add_shortcode('sc_questions', array($this, 'render_shortcode'));

        // Admin kolonner og filtre
        add_filter('manage_sc_question_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_sc_question_posts_custom_column', array($this, 'custom_column_data'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'filter_by_group'));
        add_action('pre_get_posts', array($this, 'filter_query'));

        // Eksport funktion
        add_action('admin_post_sc_export_txt', array($this, 'handle_txt_export'));
        add_action('admin_menu', array($this, 'add_export_page'));
        add_action('admin_menu', function() {
            global $submenu;
            if ($this->does_menu_exists()) remove_submenu_page($this->menu_slug, $this->menu_slug);
            if (!isset($submenu[$this->menu_slug])) return;
            $items = $submenu[$this->menu_slug];
            $postsKey = null;
            $exportKey = null;
            foreach ($items as $key => $item) {
                if ($item[2] == 'edit.php?post_type=sc_question') {
                    $postsKey = $key;
                }
                if ($item[2] == 'sc-export') {
                    $exportKey = $key;
                }
            }
            $postsItem = $items[$postsKey];
            $exportItem = $items[$exportKey];
            unset($items[$postsKey]);
            unset($items[$exportKey]);
            $items[] = $postsItem;
            $items[] = $exportItem;
            $submenu[$this->menu_slug] = array_values($items);
        }, 999);
        add_action('admin_head', array($this, 'fix_svg_size'));
    }

    public function load_textdomain() {
        load_plugin_textdomain(
                'sc-questions',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    private function does_menu_exists() {
        global $menu;
        if (!is_array($menu)) {
            return false;
        }
        foreach ($menu as $item) {
            if ($item[2] == $this->menu_slug) {
                return true;
            }
        }
        return false;
    }

    function fix_svg_size() {
        echo '
	<style>
		#toplevel_page_star-citizen .wp-menu-image img {
			width: 20px !important;
			height: 20px !important;
			padding: 0 !important;
			margin: 0 !important;
			box-sizing: border-box;
			display: inline-block;
			vertical-align: middle;
		}

		#toplevel_page_star-citizen .wp-menu-image {
			display: flex !important;
			align-items: center;
			justify-content: center;
		}
	</style>
';
    }

    public function add_export_page() {
        if (!$this->does_menu_exists()) {
            add_menu_page(
                    __('Star Citizen', 'sc-questions'),
                    __('Star Citizen', 'sc-questions'),
                    'manage_options',
                    $this->menu_slug,
                    null,
                    plugins_url('sc-questions/assets/scc-ogo.svg', 'sc-questions'),
                    26
            );
        }
        add_submenu_page(
                $this->menu_slug,
                __('Export Questions', 'sc-questions'),
                __('Export Questions', 'sc-questions'),
                'manage_options',
                'sc-export',
                array($this, 'render_export_page'),
                26
        );
    }

    public function register_post_type() {
        register_post_type('sc_question', array(
                'labels' => array(
                        'name' => __('Questions', 'sc-questions'),
                        'singular_name' => __('Question', 'sc-questions'),
                        'add_new_item' => __('Add a question', 'sc-questions'),
                        'search_items' => __('Search for questions', 'sc-questions'),
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => $this->menu_slug,
                'supports' => array('title', 'editor', 'custom-fields'),
                'menu_icon' => 'dashicons-format-chat',
                'menu_position' => 25,
        ));
    }

    private function get_text($key) {
        $translations = array(
                'header' => __('Ask a Question', 'sc-questions'),
                'group' => __('For the event', 'sc-questions'),
                'handle_label' => __('RSI Handle:', 'sc-questions'),
                'handle_desc' => __('We will verify that your citizen profile exists, and you can ONLY post 1 question.<br>If you want your question deleted or changed you must write a message to <a href="https://robertsspaceindustries.com/spectrum/messages/member/DK-Raven" target="_blank">DK-Raven</a> on Spectrum.', 'sc-questions'),
                'question_label' => __('Your Question:', 'sc-questions'),
                'submit_btn' => __('Submit Question', 'sc-questions'),
                'list_header' => __('Submitted Questions', 'sc-questions'),
                'no_questions' => __('No questions in this group yet.', 'sc-questions'),
                'err_handle_fail' => __('Error: Could not find RSI Handle "%s". Please check spelling.', 'sc-questions'),
                'err_duplicate' => __('Error: You have already asked a question in this group (%s).', 'sc-questions'),
                'err_tech' => __('A technical error occurred. Please try again.', 'sc-questions'),
                'success' => __('Thank you! Your question has been received.', 'sc-questions'),
                'export_handle' => __('Handle', 'sc-questions'),
                'export_question' => __('Question', 'sc-questions'),
        );

        return isset($translations[$key]) ? $translations[$key] : $key;
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
                'group' => 'default',
                'section' => 'questions',
        ), $atts);

        $group = sanitize_text_field($atts['group']);
        $message = '';

        if (isset($_POST['sc_submit_question']) && isset($_POST['sc_nonce']) && wp_verify_nonce($_POST['sc_nonce'], 'sc_new_question')) {
            $message = $this->handle_form_submission($group);
        }

        ob_start();
        ?>
        <div class="sc-questions-wrapper">
            <?php if (!empty($message)): ?>
                <div class="sc-message" style="background: #f0f0f1; padding: 10px; margin-bottom: 20px; border-left: 4px solid #0073aa;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="#<?php echo esc_attr($atts['section']); ?>" style="margin-bottom: 40px; border: 1px solid #ddd; padding: 20px;">
                <h3><?php echo esc_html($this->get_text('header')); ?></h3><h4><?php echo esc_html($this->get_text('group')); ?>: <?php echo esc_html($group); ?></h4>

                <p>
                    <label for="sc_handle"><?php echo esc_html($this->get_text('handle_label')); ?></label><br>
                    <input type="text" name="sc_handle" id="sc_handle" required style="width:100%;">
                    <small><?php echo wp_kses_post($this->get_text('handle_desc')); ?></small>
                </p>

                <p>
                    <label for="sc_question"><?php echo esc_html($this->get_text('question_label')); ?></label><br>
                    <textarea name="sc_question" id="sc_question" rows="4" required style="width:100%;"></textarea>
                </p>

                <input type="hidden" name="sc_group" value="<?php echo esc_attr($group); ?>">
                <?php wp_nonce_field('sc_new_question', 'sc_nonce'); ?>

                <input type="submit" name="sc_submit_question" value="<?php echo esc_attr($this->get_text('submit_btn')); ?>" class="button">
            </form>

            <h3><?php echo esc_html($this->get_text('list_header')); ?></h3>
            <?php
            $args = array(
                    'post_type' => 'sc_question',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                            array(
                                    'key' => 'sc_group',
                                    'value' => $group,
                                    'compare' => '='
                            )
                    )
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) : ?>
                <div class="sc-questions-list">
                    <?php while ($query->have_posts()) : $query->the_post();
                        $handle = get_post_meta(get_the_ID(), 'sc_handle', true);
                        ?>
                        <div class="sc-question">
                            <p><a href="https://robertsspaceindustries.com/en/citizens/<?php echo esc_attr($handle); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($handle); ?></a></p>
                            <p><?php the_content(); ?></p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p><?php echo esc_html($this->get_text('no_questions')); ?></p>
            <?php endif; wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function handle_form_submission($group) {
        $handle = sanitize_text_field($_POST['sc_handle']);
        $question = sanitize_textarea_field($_POST['sc_question']);

        if (!$this->verify_rsi_handle($handle)) {
            return '<span style="color:red;">' . sprintf(esc_html($this->get_text('err_handle_fail')), esc_html($handle)) . '</span>';
        }

        if ($this->has_user_posted_in_group($handle, $group)) {
            return '<span style="color:red;">' . sprintf(esc_html($this->get_text('err_duplicate')), esc_html($group)) . '</span>';
        }

        $post_id = wp_insert_post(array(
                'post_type' => 'sc_question',
                'post_title' => wp_trim_words($question, 10),
                'post_content' => $question,
                'post_status' => 'publish',
        ));

        if ($post_id) {
            update_post_meta($post_id, 'sc_handle', $handle);
            update_post_meta($post_id, 'sc_group', $group);
            return '<span style="color:green;">' . esc_html($this->get_text('success')) . '</span>';
        }

        return '<span style="color:red;">' . esc_html($this->get_text('err_tech')) . '</span>';
    }

    private function verify_rsi_handle($handle) {
        $url = 'https://robertsspaceindustries.com/en/citizens/' . $handle;

        $response = wp_remote_head($url, array(
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36',
                'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $response = wp_remote_get($url, array(
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36',
            ));
        }

        if (is_wp_error($response)) {
            return false;
        }

        return (wp_remote_retrieve_response_code($response) === 200);
    }

    private function has_user_posted_in_group($handle, $group) {
        $args = array(
                'post_type' => 'sc_question',
                'meta_query' => array(
                        'relation' => 'AND',
                        array('key' => 'sc_handle', 'value' => $handle, 'compare' => '='),
                        array('key' => 'sc_group', 'value' => $group, 'compare' => '=')
                ),
                'fields' => 'ids'
        );
        $query = new WP_Query($args);
        return ($query->found_posts > 0);
    }

    public function set_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['sc_handle'] = __('RSI Handle', 'sc-questions');
                $new_columns['sc_group'] = __('Group', 'sc-questions');
            }
        }
        return $new_columns;
    }

    public function custom_column_data($column, $post_id) {
        if ($column === 'sc_handle') {
            echo esc_html(get_post_meta($post_id, 'sc_handle', true));
        }
        if ($column === 'sc_group') {
            echo esc_html(get_post_meta($post_id, 'sc_group', true));
        }
    }

    public function filter_by_group($post_type) {
        if ($post_type !== 'sc_question') return;
        global $wpdb;
        $groups = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'sc_group' ORDER BY meta_value ASC");
        $current_v = isset($_GET['filter_group']) ? $_GET['filter_group'] : '';
        ?>
        <select name="filter_group">
            <option value=""><?php echo esc_html__('All Groups', 'sc-questions'); ?></option>
            <?php foreach ($groups as $g): ?>
                <option value="<?php echo esc_attr($g); ?>" <?php selected($current_v, $g); ?>><?php echo esc_html($g); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function filter_query($query) {
        global $pagenow;
        if (is_admin()
                && $pagenow === 'edit.php'
                && isset($_GET['filter_group'])
                && $_GET['filter_group'] != ''
                && isset($query->query['post_type'])
                && $query->query['post_type'] === 'sc_question') {
            $query->set('meta_key', 'sc_group');
            $query->set('meta_value', sanitize_text_field($_GET['filter_group']));
        }
    }

    public function render_export_page() {
        global $wpdb;
        $groups = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'sc_group'");
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Export Questions', 'sc-questions'); ?></h1>
            <p><?php echo esc_html__('Select a group to export to a text file.', 'sc-questions'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sc_export_txt">
                <select name="export_group">
                    <option value="all"><?php echo esc_html__('-- All Groups --', 'sc-questions'); ?></option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo esc_attr($g); ?>"><?php echo esc_html($g); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Download .txt file', 'sc-questions')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_txt_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No access', 'sc-questions'));
        }

        $group = isset($_POST['export_group']) ? sanitize_text_field($_POST['export_group']) : 'all';
        $args = array('post_type' => 'sc_question', 'posts_per_page' => -1, 'post_status' => 'publish');

        if ($group !== 'all') {
            $args['meta_key'] = 'sc_group';
            $args['meta_value'] = $group;
        }

        $query = new WP_Query($args);
        $filename = 'sc-questions-' . date('Y-m-d') . '.txt';

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $handle = get_post_meta(get_the_ID(), 'sc_handle', true);
                $content = wp_strip_all_tags(get_the_content());

                echo esc_html__('Handle', 'sc-questions') . ': ' . $handle . "\r\n";
                echo esc_html__('Question', 'sc-questions') . ': ' . $content . "\r\n";
                echo "\r\n--\r\n";
            }
        } else {
            echo esc_html__('No questions found.', 'sc-questions');
        }
        exit;
    }
}

new SC_Questions_Plugin();