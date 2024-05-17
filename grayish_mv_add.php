<?php
/*
Plugin Name: grayish MV add Plugin
Description: grayishのフロントページのメインビジュアルにスライダー又は動画を追加するプラグイン
Version: 1.0.8
Author: Na2factory
Author URI: https://na2-factory.com/
License: GNU General Public License
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

// 現在のテーマがcocoon-master or cocoon-masterの子テーマである場合のみ処理を行う
$theme = wp_get_theme();
if ('cocoon-master' !== $theme->template && (!$theme->parent() || 'cocoon-master' !== $theme->parent()->template)) {
	return;
}

add_action('after_setup_theme', function () {
	$skin_url = get_skin_url();
	// $skin_urlにskin-grayish-topfullが含まれている場合のみ、処理を続行
	if (strpos($skin_url, 'skin-grayish-topfull') === false) {
		return;
	} else {

		// プラグイン
		if (!defined('GRYMV_PLUGIN_VERSION')) {
			define('GRYMV_PLUGIN_VERSION', '1.0.8');
		}
		if (!defined('GRY_PLUGIN_PATH')) {
			define('GRY_PLUGIN_PATH', plugin_dir_path(__FILE__));
		}
		if (!defined('GRY_PLUGIN_URL')) {
			define('GRY_PLUGIN_URL', plugins_url('/', __FILE__));
		}

		add_action('wp_enqueue_scripts', function () {
			/** CSS */
			wp_enqueue_style(
				'grayish-mvadd-style',
				GRY_PLUGIN_URL . 'assets/grayish_mvadd_style.css',
				array(),
				GRYMV_PLUGIN_VERSION
			);
		});

		//=================================================
		// 管理画面 > 設定のメニューに「grayish mv add menu」を追加
		//=================================================

		add_action('admin_menu', 'register_my_custom_submenu_page');
		if (!function_exists('register_my_custom_submenu_page')) :

			function register_my_custom_submenu_page()
			{
				add_submenu_page(
					'options-general.php',
					'grayish MV add',
					'grayish MV add 設定',
					'manage_options',
					'grayish_mvadd_menu',
					'grayish_mvadd_menu_page_contents'
				);
			}
		endif;

		add_action('admin_init', function () {

			register_setting('grayish-mvadd-settings-group', 'grayish_mvadd_positive_integer', [
				'sanitize_callback' => function ($input) {
					$input = intval($input);
					return $input > 0 ? $input : false;
				}
			]);
			register_setting('grayish-mvadd-settings-group', 'grayish-mvadd-slider-type');
			register_setting('grayish-mvadd-settings-group', 'grayish-mvadd-slider-delay');
			register_setting('grayish-mvadd-settings-group', 'grayish-mvadd-slider-fade-ani');
			register_setting('grayish-mvadd-settings-group', 'grayish-mvadd-slider-parallax-ani');

			add_settings_section(
				'grayish-mvadd-settings-section',
				'grayishのフロントページのMV(メインビジュアル）にスライダー又は動画を追加します。',
				function () {
					echo '<p>【事前準備】プラグインの専用パターンの登録（インポート）を行い、使用する動画や画像を設定してください。</p>';
					echo '<p>【スライダー】専用パターンで3カラム以上画像を設定してください。</p>';
					echo '<p>【動画】PCサイズのみ、又はPC/SPサイズの専用パターンを設定してください。</p>';
				},
				'grayish_mvadd_menu'
			);

			add_settings_field(
				'grayish-mvadd-positive-integer',
				'patternのID（数字のみ入力）',
				function () {
					$post_id = get_option('grayish_mvadd_positive_integer', '');
					echo "<input type='number' name='grayish_mvadd_positive_integer' min='1' step='1' value='" . esc_attr($post_id) . "'>";
					$post_chk = get_post($post_id);
					if ($post_chk !== null && get_post_type($post_chk) === 'wp_block' && $post_chk->post_status === 'publish') {
						$post_title = $post_chk->post_title;
						echo '<div class="notice notice-success is-dismissible"><p>使用中のpatternのタイトル:' . esc_html($post_title) . '</p></div>';
						// スライダー, 動画のパターンかどうか簡易調査
						$chk_output = do_shortcode('[pattern id="' . $post_id . '"]');
						if (strpos($chk_output, 'header-swiper-mode') !== false) {
							echo '<p>スライダーのパターンです。</p>';
							update_option('grayish_mvadd_number_input', $post_id);
							update_option('grayish_mvadd_type', 'slider');
						} else if (strpos($chk_output, 'header-mp4-mode') !== false) {
							echo '<p>PCサイズのみの動画のパターンです。</p>';
							update_option('grayish_mvadd_number_input', $post_id);
							update_option('grayish_mvadd_type', 'movie_pc');
						} else if (strpos($chk_output, 'header-mp4-pcsp-mode') !== false) {
							echo '<p>PC/SPサイズの動画のパターンです。</p>';
							update_option('grayish_mvadd_number_input', $post_id);
							update_option('grayish_mvadd_type', 'movie_pcsp');
						} else {
							echo '<p style="color: red;">ERROR！スライダーでも動画でもありません。</p>';
							echo '<div class="notice notice-error is-dismissible"><p>スライダー又は動画のpattern idを入力してください。</p></div>';
						}
					} else {
						if ($post_id === '') {
							echo '<div class="notice notice-warning is-dismissible"><p>pattern idを入力してください。</p></div>';
						} else {
							echo '<div class="notice notice-error is-dismissible"><p>pattern idが存在しないようです。もう一度入力してください。</p></div>';
						}
					}
				},
				'grayish_mvadd_menu',
				'grayish-mvadd-settings-section'
			);

			add_settings_field(
				'grayish-mvadd-slider-type',
				'スライダータイプを選択',
				function () {
					$type_setting = get_option('grayish-mvadd-slider-type', 'fade');
					echo '<input type="radio" id="fade" name="grayish-mvadd-slider-type" value="fade"' . ($type_setting === 'fade' ? ' checked' : '') . '>';
					echo '<label for="fade">フェード</label>';
					echo '<p></p>';
					echo '<input type="radio" id="h-slide" name="grayish-mvadd-slider-type" value="h-slide"' . ($type_setting === 'h-slide' ? ' checked' : '') . '>';
					echo '<label for="h-slide">横にスライド</label>';
					echo '<p></p>';
					echo '<input type="radio" id="v-slide" name="grayish-mvadd-slider-type" value="v-slide"' . ($type_setting === 'v-slide' ? ' checked' : '') . '>';
					echo '<label for="v-slide">縦にスライド</label>';
				},
				'grayish_mvadd_menu',
				'grayish-mvadd-settings-section'
			);
			add_settings_field(
				'grayish-mvadd-slider-delay',
				'スライド切り替え時間(秒)',
				function () {
					$delay_setting = get_option('grayish-mvadd-slider-delay', '7');
					echo ' <select name="grayish-mvadd-slider-delay" id="default_delay" class="postform">';
					for ($i = 13; $i >= 3; $i -= 2) {
						echo ' <option class="level-0" value="' . $i . '"' . ($delay_setting == $i ? ' selected="selected"' : '') . '>' . ($i * 1) . ($i == 7 ? '（デフォルト）' : ($i == 13 ? '（ゆっくり）' : ($i == 3 ? '（速い）' : ''))) . '</option>';
					}
					echo ' </select>';
				},
				'grayish_mvadd_menu',
				'grayish-mvadd-settings-section'
			);

			add_settings_field(
				'grayish-mvadd-slider-fade-ani',
				'フェードタイプのみ <br>Zoomアニメーション選択',
				function () {
					$type_setting = get_option('grayish-mvadd-slider-fade-ani', 'none');
					echo '<input type="radio" id="none" name="grayish-mvadd-slider-fade-ani" value="none"' . ($type_setting === 'none' ? ' checked' : '') . '>';
					echo '<label for="none">なし</label>';
					echo '<p></p>';
					echo '<input type="radio" id="zoom-in" name="grayish-mvadd-slider-fade-ani" value="zoom-in"' . ($type_setting === 'zoom-in' ? ' checked' : '') . '>';
					echo '<label for="zoom-in">ZoomIn</label>';
					echo '<p></p>';
					echo '<input type="radio" id="zoom-out" name="grayish-mvadd-slider-fade-ani" value="zoom-out"' . ($type_setting === 'zoom-out' ? ' checked' : '') . '>';
					echo '<label for="zoom-out">ZoomOut</label>';
				},
				'grayish_mvadd_menu',
				'grayish-mvadd-settings-section'
			);
			add_settings_field(
				'grayish-mvadd-slider-parallax-ani',
				'横・縦にスライドタイプのみ <br>パララックスアニメーション選択',
				function () {
					$type_setting = get_option('grayish-mvadd-slider-parallax-ani', 'none');
					echo '<input type="radio" id="none" name="grayish-mvadd-slider-parallax-ani" value="none"' . ($type_setting === 'none' ? ' checked' : '') . '>';
					echo '<label for="none">なし</label>';
					echo '<p></p>';
					echo '<input type="radio" id="parallax" name="grayish-mvadd-slider-parallax-ani" value="parallax"' . ($type_setting === 'parallax' ? ' checked' : '') . '>';
					echo '<label for="parallax">パララックスあり</label>';
				},
				'grayish_mvadd_menu',
				'grayish-mvadd-settings-section'
			);
		});
		//=================================================
		// メインメニューページ内容の表示・更新処理
		//=================================================
		function grayish_mvadd_menu_page_contents()
		{
			echo '<div class="wrap">';
			echo '<h1>grayish MV add 設定</h1>';
			echo '<form method="post" action="options.php">';
			settings_fields('grayish-mvadd-settings-group');
			do_settings_sections('grayish_mvadd_menu');
			submit_button();
			echo '</form>';
			echo '</div>';
		}


		//=================================================
		// 投稿IDを取得->フロントページのheaderの下にカバーブロックのHTMLを追加
		//=================================================

		add_filter("cocoon_part__tmp/header-container", function ($content) {
			if (is_front_top_page()) {
				$post_user_id = get_option('grayish_mvadd_number_input', '');
				$add_type = get_option('grayish_mvadd_type', '');
				global $_IS_SWIPER_ENABLE;

				if ($post_user_id && $add_type) {
					ob_start();
					$output = do_shortcode('[pattern id="' . $post_user_id . '"]');
					if ($add_type === 'slider') {
						$_IS_SWIPER_ENABLE = true;
						$output = replace_wp_images_with_full_size($output);
					} else {
						$_IS_SWIPER_ENABLE = false;
						if ($add_type === 'movie_pc') {
						} else if ($add_type === 'movie_pcsp') {
							$output = process_video_output_pcsp($output);
						} else {
							// 対象のpattern以外は出力しない
							$output = "";
						}
					}

					// $pattern_aft = '/<header id="header" class="header(.*?)>\s*<div id="header-in" class="header-in wrap cf"(.*?)>\s*<\/div>\s*<\/header>/s';
					// $replacement_aft = '<header id="header" class="header$1><div class="header-cstm-front-addblk">' . $output . '</div><div id="header-in" class="header-in wrap cf"$2></div></header>';
					$pattern_aft = '/<header id="header" class="header(.*?)>\s*<div class="grayish_topmv_whovlay"><\/div><div class="grayish_topmv_dot"><\/div><div id="header-in" class="header-in wrap cf"(.*?)>\s*<\/div>\s*<\/header>/s';
					$replacement_aft = '<header id="header" class="header$1><div class="header-cstm-front-addblk">' . $output . '</div><div class="grayish_topmv_whovlay"></div><div class="grayish_topmv_dot"></div><div id="header-in" class="header-in wrap cf"$2></div></header>';
					$content_buf = preg_replace($pattern_aft, $replacement_aft, $content);

					echo $content_buf;
					$content = ob_get_clean();
					return $content;
				} else {
					// pattern idが未入力の場合は何もしない
					return $content;
				}
			} else {
				// フロントページ以外の場合は何もしない
				return $content;
			}
		});


		//=================================================
		// プラグインの処理
		//=================================================

		add_filter(
			'body_class_additional',
			function ($classes) {

				if (is_front_top_page()) {
					$classes[] = 'grayish-plg-frontpage';
				}
				return $classes;
			}
		);


		if (!function_exists('replace_wp_images_with_full_size')) :
			function replace_wp_images_with_full_size($output)
			{
				if (preg_match_all('/class="wp-image-(\d+)"/', $output, $matches)) {
					foreach ($matches[1] as $image_id) {
						$new_img_tag = wp_get_attachment_image($image_id, 'full');
						$output = preg_replace('/<img[^>]+class="wp-image-' . $image_id . '"[^>]*>/', $new_img_tag, $output);
					}
				}
				return $output;
			}
		endif;


		add_filter('render_block', 'cstm_swiper_blk_output', 10, 2);
		if (!function_exists('cstm_swiper_blk_output')) :
			function cstm_swiper_blk_output($block_content, $block)
			{
				if ($block['blockName'] === 'core/cover'  && (isset($block['attrs']['className']) && 'header-cstm-front-addblk-cover header-swiper-mode' === $block['attrs']['className'])) {
					$tag = new WP_HTML_Tag_Processor($block_content);
					$tag->next_tag(
						array(
							'tag_name'   => 'div',
							'class_name' => 'wp-block-cover__inner-container',
						)
					);
					$tag->add_class('cstm-mv-swiper swiper');

					$tag->next_tag(
						array(
							'tag_name'   => 'div',
							'class_name' => 'wp-block-columns',
						)
					);
					$tag->add_class('swiper-wrapper');

					while ($tag->next_tag(array('tag_name' => 'div', 'class_name' => 'wp-block-column'))) {
						$tag->add_class('swiper-slide');
					}

					return $tag->get_updated_html();
				}
				return $block_content;
			}

		endif;

		add_filter('render_block', 'cstm_swiperimg_blk_output', 10, 2);
		if (!function_exists('cstm_swiperimg_blk_output')) :
			function cstm_swiperimg_blk_output($block_content, $block)
			{
				$sliderType = get_option('grayish-mvadd-slider-type', 'fade');
				$parallaxAni = get_option('grayish-mvadd-slider-parallax-ani', 'none');

				if ($block['blockName'] === 'core/cover'  && (isset($block['attrs']['className']) && 'header-cstm-front-addblk-cover header-swiper-mode' === $block['attrs']['className'])) {
					$tag = new WP_HTML_Tag_Processor($block_content);
					while ($tag->next_tag(array('tag_name' => 'figure', 'class_name' => 'wp-block-image'))) {
						if (("$sliderType" === 'v-slide' || "$sliderType" === 'h-slide') && "$parallaxAni" === 'parallax') {
							$tag->set_attribute('data-swiper-parallax', '60%');
							$tag->set_attribute('data-swiper-parallax-scale', '1.1');
						}
					}
					return $tag->get_updated_html();
				}
				return $block_content;
			}

		endif;


		if (!function_exists('process_video_output_pcsp')) :
			function process_video_output_pcsp($output)
			{

				// PC
				if (strpos($output, 'video-pc') !== false) {
					$pc_videopath = '/<figure(.*?)class="(.*?)wp-block-video(.*?)video-pc(.*?)">\s*<video(.*?)src="(.*?)"(.*?)><\/video>\s*<\/figure>/s';
					if (preg_match($pc_videopath, $output, $matches)) {
						$pc_videopath_src = $matches[6];
					}
				}
				// SP
				if (strpos($output, 'figure class="wp-block-video video-sp') !== false) {
					$sp_videopath = '/<figure class="wp-block-video video-sp">\s*<video(.*?)src="(.*?)"(.*?)><\/video>\s*<\/figure>/s';
					if (preg_match($sp_videopath, $output, $matches)) {
						$sp_videopath_src = $matches[2];
					}
				}

				if (isset($pc_videopath_src) && isset($sp_videopath_src)) {
					$output .= <<<JS
				<script>
				const mediaQueryListMD = window.matchMedia('(min-width: 768px)');
				const videoTarget = document.querySelector('.header-mp4-pcsp-mode .video-pc video');
				function VideoFileChange(e) {
					if (e.matches) {
						videoTarget.src = "{$pc_videopath_src}";
					} else {
						videoTarget.src = "{$sp_videopath_src}";
					}
				}
				mediaQueryListMD.addEventListener("change", VideoFileChange);
				VideoFileChange(mediaQueryListMD);
				</script>
				JS;

					$pattern_aft = '/<figure class="wp-block-video video-sp">(.*?)<\/video>\s*<\/figure>/s';
					$replacement_aft = '';
					$output = preg_replace($pattern_aft, $replacement_aft, $output);
				}

				return $output;
			}
		endif;

		add_action('wp_head', 'grayish_mvadd_custom_css');
		if (!function_exists('grayish_mvadd_custom_css')) :
			function grayish_mvadd_custom_css()
			{
				if (is_front_top_page()) {
					$add_type = get_option('grayish_mvadd_type', '');
					if ($add_type === 'slider') {
						if (get_option('grayish-mvadd-slider-type', 'fade') === 'fade') {
							$fade_ani = get_option('grayish-mvadd-slider-fade-ani', 'none');
							if ($fade_ani !== 'none') {
								$delay = intval(get_option('grayish-mvadd-slider-delay', '7'));
								$delaytime = $delay * 2 + 2;
								echo '<style>:root { --fade-zoom-maxtime: ' . $delaytime . 's; }</style>';
								if ($fade_ani === 'zoom-in') {
									echo '<style>:root { --fade-zoom-mode: var(--fade-zoom-in); }</style>';
								} else if ($fade_ani === 'zoom-out') {
									echo '<style>:root { --fade-zoom-mode: var(--fade-zoom-out); }</style>';
								} else {
									echo '<style>:root { --fade-zoom-mode: var(--fade-zoom-none); }</style>';
								}
							} else {
								echo '<style>:root { --fade-zoom-mode: var(--fade-zoom-none); }</style>';
							}
						}
					}
				}
			}
		endif;

		// Swiper
		add_filter("cocoon_part__tmp/footer-javascript", function ($content) {
			if (is_front_top_page()) {
				$add_type = get_option('grayish_mvadd_type', '');
				if ($add_type === 'slider') {
					ob_start();
					// スライダータイプ
					if (get_option('grayish-mvadd-slider-type', 'fade') === 'fade') {
						$effect = 'fade';
						$direction = 'horizontal';
						$parallax = 'false';
					} else if (get_option('grayish-mvadd-slider-type', 'fade') === 'h-slide') {
						$effect = 'slide';
						$direction = 'horizontal';
						if (get_option('grayish-mvadd-slider-parallax-ani', 'none') === 'parallax') {
							$parallax = 'true';
						} else {
							$parallax = 'false';
						}
					} else if (get_option('grayish-mvadd-slider-type', 'fade') === 'v-slide') {
						$effect = 'slide';
						$direction = 'vertical';
						if (get_option('grayish-mvadd-slider-parallax-ani', 'none') === 'parallax') {
							$parallax = 'true';
						} else {
							$parallax = 'false';
						}
					} else {
						$effect = 'fade';
						$direction = 'horizontal';
						$parallax = 'false';
					}

					// Delay
					if (get_option('grayish-mvadd-slider-delay', '7')) {
						$delay = intval(get_option('grayish-mvadd-slider-delay', '7') * 1000);
					} else {
						$delay = intval(7000);
					}

					$output = <<<JS
							const headerMVContainer = document.querySelector('.grayish-plg-frontpage.front-top-page .container .header-container .header');
							const swiperMVContainer = document.querySelector('.cstm-mv-swiper');
							const slideLength = document.querySelectorAll('.cstm-mv-swiper.swiper .swiper-slide').length;

							const initCstmMVSwiper = () => {
								if(!swiperMVContainer)return;
								// スライド数が1以下の場合はswiper動作しない
								if(slideLength <= 1) return;
								const myEffect = '$effect';
								const myDelay = $delay;
								const myParallax = $parallax;

								const CstmMVSwiper = new Swiper('.cstm-mv-swiper.swiper', {
								effect: myEffect,
								fadeEffect: {
								crossFade: true,
								},
								loop: true,
								loopAdditionalSlides: 1,
								slidesPerView: 'auto',
								direction: '$direction',
								parallax: myParallax,
								spaceBetween: 0,
								speed: 2000,
								autoplay: {
								delay: myDelay,
								disableOnInteraction: false,
								waitForTransition: false,
								},
								followFinger: false,
								watchSlidesProgress:true,
								on: {
									afterInit: (swiper) => {
									headerMVContainer.classList.add('is-init-after');
									},
								}
								});
							};
							initCstmMVSwiper();
					JS;

					$pattern_aft = '/<script>(.*?)const mySwiper(.*?)<\/script>/s';
					$replacement_aft = '<script>$1const mySwiper$2' . $output . '</script>';
					$content_buf = preg_replace($pattern_aft, $replacement_aft, $content);

					echo $content_buf;
					$content = ob_get_clean();
				}
				return $content;
			} else {
				return $content;
			}
		});
	}
});
