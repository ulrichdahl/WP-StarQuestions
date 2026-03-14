<?php
/*
Plugin Name: Sc Questions
Plugin URI: https://github.com/ulrichdahl/sc-questions
Description: Indsaml spørgsmål med RSI Handle verifikation. Understøtter auto-detektion af browser sprog (DA/EN).
Version: 1.1
Author: Ulrich Dahl <ulrich.dahl@gmail.com> / Gemini
Author URI: https://github.com/ulrichdahl/
License: GPL2
*/

if (!defined('ABSPATH')) {
	exit;
}

class SC_Questions_Plugin {

	// Gemmer det aktuelle sprog ('da' eller 'en')
	private $current_lang = 'en';

	public function __construct() {
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
		add_action('admin_menu', array($this, 'add_export_page'));
		add_action('admin_post_sc_export_txt', array($this, 'handle_txt_export'));
	}

	/**
	 * OVERSÆTTELSES-MOTOR
	 * Tjekker browser sprog og returnerer den rette tekst
	 */
	private function detect_browser_language() {
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
			// Hvis browseren starter med 'da', sæt til dansk, ellers engelsk
			$this->current_lang = ($lang === 'da') ? 'da' : 'en';
		} else {
			$this->current_lang = 'en';
		}
	}

	private function get_text($key) {
		// Ordbog
		$translations = array(
			'en' => array(
				'header' => 'Ask a Question',
				'group' => 'Group',
				'handle_label' => 'RSI Handle:',
				'handle_desc' => 'We will verify that your citizen profile exists, and you can ONLY post 1 question.<br>If you want your question deleted or changed you must write a message to <a href="https://robertsspaceindustries.com/spectrum/messages/member/DK-Raven" target="_blank">DK-Raven</a> on Spectrum.',
				'question_label' => 'Your Question:',
				'submit_btn' => 'Submit Question',
				'list_header' => 'Submitted Questions',
				'no_questions' => 'No questions in this group yet.',
				'err_handle_fail' => 'Error: Could not find RSI Handle "%s". Please check spelling.',
				'err_duplicate' => 'Error: You have already asked a question in this group (%s).',
				'err_tech' => 'A technical error occurred. Please try again.',
				'success' => 'Thank you! Your question has been received.',
				'export_handle' => 'Handle',
				'export_question' => 'Question'
			),
			'da' => array(
				'header' => 'Stil et spørgsmål',
				'group' => 'Gruppe',
				'handle_label' => 'RSI Handle:',
				'handle_desc' => 'Vi tjekker om din citizen profil findes, og du kan kun stille 1 spørgsmål.<br>Ønsker du at slette eller ændre dit spørgsmål skal du skrive en direkte besked til <a href="https://robertsspaceindustries.com/spectrum/messages/member/DK-Raven" target="_blank">DK-Raven</a> på Spectrum.',
				'question_label' => 'Dit spørgsmål:',
				'submit_btn' => 'Indsend Spørgsmål',
				'list_header' => 'Indsendte spørgsmål',
				'no_questions' => 'Ingen spørgsmål i denne gruppe endnu.',
				'err_handle_fail' => 'Fejl: Kunne ikke finde RSI Handle "%s". Tjek stavemåden.',
				'err_duplicate' => 'Fejl: Du har allerede stillet et spørgsmål i denne gruppe (%s).',
				'err_tech' => 'Der opstod en teknisk fejl. Prøv igen.',
				'success' => 'Tak! Dit spørgsmål er modtaget.',
				'export_handle' => 'Handle',
				'export_question' => 'Spørgsmål'
			)
		);

		return isset($translations[$this->current_lang][$key]) ? $translations[$this->current_lang][$key] : $key;
	}

	/**
	 * 1. Opret Post Type
	 */
	public function register_post_type() {
		register_post_type('sc_question', array(
			'labels' => array(
				'name' => 'SC Spørgsmål',
				'singular_name' => 'Spørgsmål',
				'add_new_item' => 'Tilføj nyt spørgsmål',
				'search_items' => 'Søg i spørgsmål',
			),
			'public' => false,
			'show_ui' => true,
			'supports' => array('title', 'editor', 'custom-fields'),
			'menu_icon' => 'dashicons-format-chat',
		));
	}

	/**
	 * 2. Shortcode Logic
	 */
	public function render_shortcode($atts) {
		// 1. Detekter sprog hver gang shortcoden køres
		$this->detect_browser_language();

		$atts = shortcode_atts(array(
			'group' => 'default',
			'section' => 'questions',
		), $atts);

		$group = sanitize_text_field($atts['group']);
		$message = '';

		// Håndter formular
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

			<form method="post" action="#<?php echo $atts['section'];?>" style="margin-bottom: 40px; border: 1px solid #ddd; padding: 20px;">
				<h3><?php echo $this->get_text('header'); ?><br/><?php echo $this->get_text('group'); ?>: <?php echo esc_html($group); ?></h3>

				<p>
					<label for="sc_handle"><?php echo $this->get_text('handle_label'); ?></label><br>
					<input type="text" name="sc_handle" id="sc_handle" required style="width:100%;">
					<small><?php echo $this->get_text('handle_desc'); ?></small>
				</p>

				<p>
					<label for="sc_question"><?php echo $this->get_text('question_label'); ?></label><br>
					<textarea name="sc_question" id="sc_question" rows="4" required style="width:100%;"></textarea>
				</p>

				<input type="hidden" name="sc_group" value="<?php echo esc_attr($group); ?>">
				<?php wp_nonce_field('sc_new_question', 'sc_nonce'); ?>

				<input type="submit" name="sc_submit_question" value="<?php echo $this->get_text('submit_btn'); ?>" class="button">
			</form>

			<h3><?php echo $this->get_text('list_header'); ?></h3>
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
						<div class="sc-question wp-block wp-block-kubio-column  position-relative wp-block-kubio-column__container d-flex h-col-lg-auto h-col-md-auto h-col-auto" data-kubio="kubio/column"><div class="position-relative wp-block-kubio-column__inner style-local-101-inner d-flex h-flex-basis h-px-lg-3 v-inner-lg-3 h-px-md-3 v-inner-md-3 h-px-3 v-inner-3"><div class="background-wrapper"><div class="background-layer background-layer-media-container-lg"></div><div class="background-layer background-layer-media-container-md"></div><div class="background-layer background-layer-media-container"></div></div><div class="position-relative wp-block-kubio-column__align style-local-101-align h-y-container h-column__content h-column__v-align flex-basis-100 align-self-lg-center align-self-md-center align-self-center"><ul class="wp-block wp-block-kubio-iconlist  position-relative wp-block-kubio-iconlist__outer ul-list-icon list-type-vertical-on-desktop list-type-vertical-on-tablet list-type-vertical-on-mobile" data-kubio="kubio/iconlist"><li class="wp-block wp-block-kubio-iconlistitem  position-relative wp-block-kubio-iconlistitem__item" data-kubio="kubio/iconlistitem"><div class="first-el-spacer position-relative wp-block-kubio-iconlistitem__divider-wrapper"></div><div class="position-relative wp-block-kubio-iconlistitem__text-wrapper"><span class="h-svg-icon wp-block-kubio-iconlistitem__icon" name="font-awesome/question-circle" style="fill:rgba(var(--kubio-color-3),1);width: 24px;height: 24px;"><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="question-circle" viewBox="0 0 1536 1896.0833"><path d="M896 1376v-192q0-14-9-23t-23-9H672q-14 0-23 9t-9 23v192q0 14 9 23t23 9h192q14 0 23-9t9-23zm256-672q0-88-55.5-163T958 425t-170-41q-243 0-371 213-15 24 8 42l132 100q7 6 19 6 16 0 25-12 53-68 86-92 34-24 86-24 48 0 85.5 26t37.5 59q0 38-20 61t-68 45q-63 28-115.5 86.5T640 1020v36q0 14 9 23t23 9h192q14 0 23-9t9-23q0-19 21.5-49.5T972 957q32-18 49-28.5t46-35 44.5-48 28-60.5 12.5-81zm384 192q0 209-103 385.5T1153.5 1561 768 1664t-385.5-103T103 1281.5 0 896t103-385.5T382.5 231 768 128t385.5 103T1433 510.5 1536 896z"></path></svg></span><span class="position-relative wp-block-kubio-iconlistitem__text" style="padding:4px"><a href="https://robertsspaceindustries.com/en/citizens/<?php echo esc_html($handle); ?>" target="_blank"><?php echo esc_html($handle); ?></a></span></div><div class="last-el-spacer position-relative wp-block-kubio-iconlistitem__divider-wrapper"></div><div class="position-relative wp-block-kubio-iconlistitem__divider-wrapper"></div></li></ul><p class="sc-question wp-block wp-block-kubio-text  position-relative wp-block-kubio-text__text" data-kubio="kubio/text"><?php the_content(); ?></p></div></div></div>
					<?php endwhile; ?>
				</div>
			<?php else: ?>
				<p><?php echo $this->get_text('no_questions'); ?></p>
			<?php endif; wp_reset_postdata(); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 3. Håndter indsendelse
	 */
	private function handle_form_submission($group) {
		$handle = sanitize_text_field($_POST['sc_handle']);
		$question = sanitize_textarea_field($_POST['sc_question']);

		// A. Verificer RSI Handle
		if (!$this->verify_rsi_handle($handle)) {
			return '<span style="color:red;">' . sprintf($this->get_text('err_handle_fail'), esc_html($handle)) . '</span>';
		}

		// B. Tjek for dubletter
		if ($this->has_user_posted_in_group($handle, $group)) {
			return '<span style="color:red;">' . sprintf($this->get_text('err_duplicate'), esc_html($group)) . '</span>';
		}

		// C. Opret spørgsmål
		$post_id = wp_insert_post(array(
			'post_type' => 'sc_question',
			'post_title' => wp_trim_words($question, 10),
			'post_content' => $question,
			'post_status' => 'publish',
		));

		if ($post_id) {
			update_post_meta($post_id, 'sc_handle', $handle);
			update_post_meta($post_id, 'sc_group', $group);
			return '<span style="color:green;">' . $this->get_text('success') . '</span>';
		}

		return '<span style="color:red;">' . $this->get_text('err_tech') . '</span>';
	}

	/**
	 * Tjek RSI URL
	 */
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

	/**
	 * 4. Admin Interface (Forbliver standard/engelsk for admin delens skyld, men viser data)
	 */
	public function set_custom_columns($columns) {
		$new_columns = array();
		foreach($columns as $key => $value) {
			$new_columns[$key] = $value;
			if ($key === 'title') {
				$new_columns['sc_handle'] = 'RSI Handle';
				$new_columns['sc_group'] = 'Group';
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
			<option value="">All Groups</option>
			<?php foreach ($groups as $g): ?>
				<option value="<?php echo esc_attr($g); ?>" <?php selected($current_v, $g); ?>><?php echo esc_html($g); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function filter_query($query) {
		global $pagenow;
		if (is_admin() && $pagenow === 'edit.php' && isset($_GET['filter_group']) && $_GET['filter_group'] != '' && $query->query['post_type'] === 'sc_question') {
			$query->set('meta_key', 'sc_group');
			$query->set('meta_value', sanitize_text_field($_GET['filter_group']));
		}
	}

	/**
	 * 5. Eksport Funktion
	 */
	public function add_export_page() {
		add_submenu_page(
			'edit.php?post_type=sc_question',
			'Export Questions',
			'Export to TXT',
			'manage_options',
			'sc-export',
			array($this, 'render_export_page')
		);
	}

	public function render_export_page() {
		global $wpdb;
		$groups = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'sc_group'");
		?>
		<div class="wrap">
			<h1>Export Questions</h1>
			<p>Select a group to export to a text file.</p>
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
				<input type="hidden" name="action" value="sc_export_txt">
				<select name="export_group">
					<option value="all">-- All Groups --</option>
					<?php foreach ($groups as $g): ?>
						<option value="<?php echo esc_attr($g); ?>"><?php echo esc_html($g); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button('Download .txt file'); ?>
			</form>
		</div>
		<?php
	}

	public function handle_txt_export() {
		if (!current_user_can('manage_options')) {
			wp_die('No access');
		}

		// Bruger fastsat sprog for eksport template (Engelsk standard), eller kan tilpasses.
		// Her beholder jeg template formatet "Handle: ..." da det er teknisk format.

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

				echo "Handle: " . $handle . "\r\n";
				echo "Question: " . $content . "\r\n";
				echo "\r\n--\r\n";
			}
		} else {
			echo "No questions found.";
		}
		exit;
	}
}

new SC_Questions_Plugin();
