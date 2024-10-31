<?php
/*
Plugin Name: Security Checker for Themes
Description: Analyze your WordPress theme's code for issues, security vulnerabilities, and adherence to coding standards with a detailed report and score.
Version: 1.0.0
Author: Harpalsinh Parmar
Author URI: https://profiles.wordpress.org/developer1998/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

class SecurityCheckerForThemes{
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'scft_enqueue_plugin_styles']);
        add_action('admin_enqueue_scripts', [$this, 'scft_enqueue_plugin_script_inline'], 30);
        add_action('admin_menu', [$this, 'scft_add_admin_menu']);
        register_activation_hook(__FILE__, [$this, 'scft_plugin_activation']);
        register_deactivation_hook(__FILE__, [$this, 'scft_plugin_deactivation']);
        set_error_handler([$this, 'scft_custom_error_handler']);
    }

    public function scft_plugin_activation() {
        flush_rewrite_rules();
    }

    public function scft_plugin_deactivation() {
        flush_rewrite_rules();
    }

    public function scft_enqueue_plugin_styles($hook) {
        $plugin_pages = array('toplevel_page_security-checker-for-themes');
        if (in_array($hook, $plugin_pages)) {
            wp_enqueue_style( 'wpcode-plugin-styles', plugins_url( 'assets/css/styles.css', __FILE__ ), array(), '1.0.0' );
            wp_enqueue_script('wpcode-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', array('jquery'), '1.0.0', true);
            //wp_enqueue_script('wpcode-chart-js', plugin_dir_url(__FILE__) . 'assets/js/Chart.js', array('jquery'), '4.4.4', false);
        }
    }

    public function scft_add_admin_menu() {
        add_menu_page(
            'Security Checker for Themes',
            'Security Checker for Themes',
            'manage_options',
            'security-checker-for-themes',
            [$this, 'scft_theme_checker_page'],
            'dashicons-privacy'
        );
    }

    public function scft_theme_checker_page() {
        ?>
        <div class="wrap wptheme-check">
            <h1 class="page-title"><span class="dashicons dashicons-privacy"></span> Security Checker for Themes</h1>

            <?php
            $theme_directories = [
                get_template_directory(),        // Parent theme
                get_stylesheet_directory()       // Child theme
            ];
            $all_files = [];
            foreach ($theme_directories as $directory) {
                $files = $this->scft_scan_theme_files($directory);
                $all_files = array_merge($all_files, $files);
            }

            $all_errors = [];
            $all_warnings = [];
            $all_suggestions = [];

            foreach ($all_files as $file) {
				$content = file_get_contents($file);
				$issues = $this->scft_analyze_file($file);
				$deprecated_issues = $this->scft_check_deprecated_functions($content);

				foreach ($issues as $issue) {
					$line_number = $issue['line'];
					$type = $issue['type'];
					$message = $issue['message'];

					switch ($type) {
						case 'Error':
							if (!isset($all_errors[$file])) {
								$all_errors[$file] = [];
							}
							if (!in_array(['line' => $line_number, 'message' => $message], $all_errors[$file], true)) {
								$all_errors[$file][] = ['line' => $line_number, 'message' => $message];
							}
							break;
						case 'Warning':
							if (!isset($all_warnings[$file])) {
								$all_warnings[$file] = [];
							}
							if (!in_array(['line' => $line_number, 'message' => $message], $all_warnings[$file], true)) {
								$all_warnings[$file][] = ['line' => $line_number, 'message' => $message];
							}
							break;
						default:
							if (!isset($all_suggestions[$file])) {
								$all_suggestions[$file] = [];
							}
							if (!in_array(['line' => $line_number, 'message' => $message], $all_suggestions[$file], true)) {
								$all_suggestions[$file][] = ['line' => $line_number, 'message' => $message];
							}
							break;
					}
				}

				// Add deprecated issues to suggestions array
				foreach ($deprecated_issues as $issue) {
					if (!isset($all_suggestions[$file])) {
						$all_suggestions[$file] = [];
					}
					if (!in_array($issue, $all_suggestions[$file], true)) {
						$all_suggestions[$file][] = $issue;
					}
				}
			}

            // Prepare data for JavaScript
            $error_count = count($all_errors);
            $warning_count = count($all_warnings);
            $suggestion_count = count($all_suggestions);

            // Localize script with data
            wp_localize_script('chart-inline-js', 'themeCheckerData', [
                'errors' => $error_count,
                'warnings' => $warning_count,
                'suggestions' => $suggestion_count
            ]);

            $error_log_file = ini_get('error_log');
            if (file_exists($error_log_file)) {
                $all_php_errors = file($error_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }

            ?>

            <div class="navtabs">
                <div class="navtab active" data-target="home"><span class="dashicons dashicons-dashboard"></span> Dashboard</div>
                <div class="navtab" data-target="errors"><span class="dashicons dashicons-dismiss"></span> Errors (<?php echo count($all_errors); ?>)</div>
                <div class="navtab" data-target="warnings"><span class="dashicons dashicons-warning"></span> Warnings (<?php echo count($all_warnings); ?>)</div>
                <div class="navtab" data-target="suggestions"><span class="dashicons dashicons-info"></span> Suggestions (<?php echo count($all_suggestions); ?>)</div>
                <div class="underline"></div>
            </div>

            <div id="home" class="content active home">
                <div class="dashboard-main">
                    <div class="dashboard-section">
                        <div class="chart">
                            <canvas id="myChart" style="width:100%;max-width:900px"></canvas>
                        </div>
                        <div class="milestone">
                            <div class="milestone-data errors">
                                <span class="data-count"><?php echo count($all_errors); ?></span>
                                <h3>Errors</h3>
                            </div>
                            <div class="milestone-data warning">
                                <span class="data-count"><?php echo count($all_warnings); ?></span>
                                <h3>Warnings</h3>
                            </div>
                            <div class="milestone-data suggestions">
                                <span class="data-count"><?php echo count($all_suggestions); ?></span>
                                <h3>Suggestions</h3>
                            </div>   
                        </div>
                    </div>
                    <div class="dashboard-right">
                        <div class="div-flex">
                            <div class="m-part-1">
                                <div class="security-grade">
                                    <?php

                                    $total = count($all_errors) + count($all_warnings) + count($all_suggestions);

                                    if ($total <= 100) {
                                        $successPercentage = 100 - (count($all_errors) + count($all_warnings) + count($all_suggestions));
                                    } else {
                                        $errorsPercentage = floor((count($all_errors) / $total) * 100);
                                        $warningsPercentage = floor((count($all_warnings) / $total) * 100);
                                        $suggestionsPercentage = floor((count($all_suggestions) / $total) * 100);
                                        $successPercentage = 100 - ($errorsPercentage + $warningsPercentage + $suggestionsPercentage);
                                    }



                                    if ($successPercentage >= 70 && $successPercentage <= 100){
                                        echo "<h2 class='a-grade'>A</h2><p>Heigh-Level Theme Security</p>";
                                    } 
                                    else if ($successPercentage >= 50 && $successPercentage <= 70){
                                        echo "<h2 class='b-grade'>B</h2><p>Heigh-Level Theme Security</p>";
                                    }
                                    else if ($successPercentage >= 30 && $successPercentage <= 50){
                                        echo "<h2 class='c-grade'>C</h2><p>Mid-Level Theme Security</p>";
                                    }
                                    else if ($successPercentage <= 30 && $successPercentage > 0){
                                        echo "<h2 class='d-grade'>D</h2><p>Improve Theme Security</p>";
                                    }
                                    ?>
                                    
                                </div>
                            </div>
                            <div class="m-part-2">
                                <div class="pro-options">
                                    <h3><span class="dashicons dashicons-privacy"></span> Security Checker for Themes</h3>
                                    <p>Security Checker for Themes is a powerful plugin designed to help WordPress developers ensure their themes adhere to coding standards, are free from security vulnerabilities, and maintain high-quality code with a graph and score based on the findings and improve the quality of your theme by identifying and fixing issues.</p>
                                </div>
                            </div>
                        </div>
                        <div class="div-flex">
                            <div class="m-part-1">
                                <div class="new-scan">
                                    <h3><span class="dashicons dashicons-update"></span> Refresh Report</h3>
                                    <p>Refresh Report to check for differences and verify if your changes have been updated or not.</p>
                                    <a href="javascript:window.location.href=window.location.href" class="refresh_agin">Refresh Now</a>
                                </div>
                                <div class="pro-options">
                                    <h3><span class="dashicons dashicons-sos"></span> Help and Support</h3>
                                    <p>For detailed assistance, refer to our documentation and frequently asked questions. Our aim is to ensure your theme meets the highest standards of safety and performance.</p>
                                    <a href="https://wordpress.org/support/plugin/security-checker-for-themes" class="refresh_agin">Support Forum</a>
                                </div>
                            </div>
                            <div class="m-part-2">
                                <div class="wptheme-check-documentation">
                                    <span class="dashicons dashicons-book"></span>
                                    <h3>Documentation</h3>
                                    <p>It is a great starting point to fix some of the most common issues.</p>
                                    <a href="<?php echo esc_url(plugin_dir_url(__FILE__)); ?>documenation.html" target=""class="read-docu">Read the Documentation</a>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>

            <div id="errors" class="content error-section">
                <?php if (!empty($all_errors)) { ?>
                    <h2>Errors</h2>
                    <?php foreach ($all_errors as $file => $errors) : ?>
                        <div class="error-info">
                            <h3><span class="dashicons dashicons-dismiss"></span> Errors in <?php echo esc_html($file); ?> <span class="arrows dashicons dashicons-arrow-down-alt2"></span></h3>
                            <ul>
                                <?php foreach ($errors as $error) : ?>
                                    <li><span class='error-lbl'>Line <?php echo esc_html($error['line']); ?></span> <?php echo esc_html($error['message']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php } else { ?>
                    <p class="no-result">No errors found in theme files.</p>
                <?php } ?>
            </div>
            
            <div id="warnings" class="content warning-section">
                <?php if (!empty($all_warnings)) { ?>
                    <h2>Warnings</h2>
                    <?php foreach ($all_warnings as $file => $warnings) : ?>
                        <div class="warning-info">
                            <h3><span class="dashicons dashicons-warning"></span> Warnings in <?php echo esc_html($file); ?> <span class="arrows dashicons dashicons-arrow-down-alt2"></span></h3>
                            <ul>
                                <?php foreach ($warnings as $warning) : ?>
                                     <li><span class='warning-lbl'>Line <?php echo esc_html($warning['line']); ?></span> <?php echo esc_html($warning['message']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php } else { ?>
                    <p class="no-result">No warnings found in theme files.</p>
                <?php } ?>
            </div>
            
            <div id="suggestions" class="content suggestion-section">
                <?php if (!empty($all_suggestions)) { ?>
                    <h2>Suggestions</h2>
                    <?php foreach ($all_suggestions as $file => $suggestions) : ?>
                        <div class="suggestion-info">
                            <h3><span class="dashicons dashicons-info"></span> Suggestions for <?php echo esc_html($file); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></h3>
                            <ul>
                                <?php foreach ($suggestions as $suggestion) : ?>
                                    <li><span class='suggestions-lbl'>Line <?php echo esc_html($suggestion['line']); ?></span> <?php echo esc_html($suggestion['message']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php } else { ?>
                    <p class="no-result">No suggestions found in theme files.</p>
                <?php } ?>
            </div>
            
        </div>

        <?php
    }

    public function scft_enqueue_plugin_script_inline() {
        wp_register_script('chart-inline-js', plugin_dir_url(__FILE__) . 'assets/js/Chart.js', [], '4.4.4', true);
        wp_enqueue_script('chart-inline-js');
        
        // Add the inline script
        $inline_script = "
            document.addEventListener('DOMContentLoaded', function(event) {
                const errors = parseInt(themeCheckerData.errors);
                const warnings = parseInt(themeCheckerData.warnings);
                const suggestions = parseInt(themeCheckerData.suggestions);

                const total = errors + warnings + suggestions;

                let errorsPercentage, warningsPercentage, suggestionsPercentage, successPercentage;

                if (total <= 100) {
                    errorsPercentage = errors;
                    warningsPercentage = warnings;
                    suggestionsPercentage = suggestions;
                    successPercentage = 100 - (errors + warnings + suggestions);
                } else {
                    errorsPercentage = Math.floor((errors / total) * 100);
                    warningsPercentage = Math.floor((warnings / total) * 100);
                    suggestionsPercentage = Math.floor((suggestions / total) * 100);
                    successPercentage = 100 - (errorsPercentage + warningsPercentage + suggestionsPercentage);
                }

                const xValues = ['Errors', 'Warnings', 'Suggestions', 'Success'];
                const yValues = [errorsPercentage, warningsPercentage, suggestionsPercentage, successPercentage];
                const barColors = ['#e30202', '#ffc000', '#01afeb', '#00af4f'];

                new Chart('myChart', {
                  type: 'doughnut',
                  data: {
                    labels: xValues,
                    datasets: [{
                      backgroundColor: barColors,
                      data: yValues
                    }]
                  },
                  options: {
                    title: {
                      display: true,
                      text: 'Security Checker for Themes'
                    },
                    responsive: false,
                    aspectRatio: 1.4
                  }
                });
            });
        ";
        wp_add_inline_script('chart-inline-js', $inline_script);
    }


    private function scft_scan_theme_files($directory) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function scft_analyze_file($file) {
		$content = file_get_contents($file);
        $lines = explode("\n", $content);
        $issues = [];

        foreach ($lines as $line_number => $line) {
            // Example checks
            if (preg_match('/\beval\b/', $line)) {
                $issues[] = [
					'type' => 'Error',
                    'line' => $line_number + 1,
                    'message' => "Error: Use of eval() found."
                ];
            }
            if (preg_match('/\bbase64_decode\b/', $line)) {
                $issues[] = [
					'type' => 'Error',
                    'line' => $line_number + 1,
                    'message' => "Error: Use of base64_decode() found."
                ];
            }
            if (preg_match('/\bextract\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Use of extract() found. It can lead to variable name collisions."
                ];
            }
            if (preg_match('/\bwp_remote_get\b/', $line) && !preg_match('/\bwp_safe_remote_get\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: wp_remote_get() used without wp_safe_remote_get(). Consider using wp_safe_remote_get() for better security."
                ];
            }
            if (preg_match('/\bmysql_query\b/', $line)) {
                $issues[] = [
					'type' => 'Suggestion',
                    'line' => $line_number + 1,
                    'message' => "Suggestion: Use \$wpdb->query() instead of mysql_query() for database queries."
                ];
            }
            if (preg_match('/\bmysql_query\b/', $line) || preg_match('/\bmysqli_query\b/', $line) || preg_match('/\bPDO::query\b/', $line)) {
                $issues[] = [
					'type' => 'Error',
                    'line' => $line_number + 1,
                    'message' => "Error: Potential SQL injection risk. Use prepared statements instead."
                ];
            }
            if (preg_match('/\b\$_(GET|POST|REQUEST|SERVER|COOKIE|FILES)\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Warning: Direct access to superglobals found."
                ];
            }
            if (preg_match('/\bextract\b/', $line) || preg_match('/\bcompact\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Use of unsafe functions (extract, compact) found."
                ];
            }
            if (preg_match('/\bfopen\b/', $line) || preg_match('/\bfread\b/', $line) || preg_match('/\bfwrite\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Insecure file handling (fopen, fread, fwrite) found."
                ];
            }
            if (preg_match('/\binclude\b/', $line) || preg_match('/\brequire\b/', $line) || preg_match('/\binclude_once\b/', $line) || preg_match('/\brequire_once\b/', $line)) {
                if (preg_match('/\$_/', $line)) {
                    $issues[] = [
						'type' => 'Error',
                        'line' => $line_number + 1,
                        'message' => "Error: Unsafe file inclusion using dynamic input."
                    ];
                }
            }
            if (preg_match('/\bmd5\b/', $line) || preg_match('/\bsha1\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Insecure encryption method (md5, sha1) found. Use stronger methods like hash_hmac() with sha256."
                ];
            }
            if (preg_match('/\becho\b/', $line) && !preg_match('/esc_html|esc_attr|wp_kses|sanitize_text_field/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Output without sanitization found. Use esc_html(), esc_attr(), wp_kses(), sanitize_text_field() functions to get output."
                ];
            }
            if (preg_match('/\bDB_PASSWORD\b/', $line) || preg_match('/\bDB_USER\b/', $line) || preg_match('/\bDB_NAME\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Hard-coded sensitive information found."
                ];
            }
            if (preg_match('/\bcheck_admin_referer\b/', $line) && !preg_match('/\bwp_verify_nonce\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: check_admin_referer used without wp_verify_nonce."
                ];
            }
            if (preg_match('/\bfile_get_contents\b/', $line) || preg_match('/\bfopen\b/', $line) || preg_match('/\bcurl_exec\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Unsafe HTTP request method used. Consider using wp_remote_get() or wp_remote_post()."
                ];
            }
            if (preg_match('/\bget_transient\b/', $line) && preg_match('/plugin_|theme_/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Insecure method for plugin/theme update checks. Use wp_version_check() or is_plugin_active_for_network() instead."
                ];
            }
            if (preg_match('/\bget_template_part\b/', $line)) {
                $issues[] = [
					'type' => 'Warning',
                    'line' => $line_number + 1,
                    'message' => "Warning: Direct file access possible through get_template_part(). Use template_redirect hook and check permissions."
                ];
            }
            if (preg_match('/\bunserialize\b/', $line)) {
                $issues[] = [
					'type' => 'Error',
                    'line' => $line_number + 1,
                    'message' => "Error: Insecure object instantiation using unserialize(). Use json_decode() or secure serialization methods."
                ];
            }
            if (preg_match('/\bmd5\b/', $line) || preg_match('/\bsha1\b/', $line)) {
                $issues[] = [
					'type' => 'Error',
                    'line' => $line_number + 1,
                    'message' => "Error: Weak password hashing algorithm used (md5, sha1). Use password_hash() and password_verify() with bcrypt or Argon2."
                ];
            }
        }

        return $issues;
    }

    private function scft_check_deprecated_functions($content) {
        $deprecated_functions = [
            'is_site_admin' => 'Use is_super_admin() instead.',
            'is_user_admin' => 'Use current_user_can(\'manage_options\') instead.',
            'wp_get_sites' => 'Use get_sites() instead.',
            'user_can_create_post' => 'Use current_user_can(\'publish_posts\') instead.',
            'get_currentuserinfo' => 'Use wp_get_current_user() instead.',
            'user_pass_ok' => 'Use wp_check_password() instead.',
            'get_userdatabylogin' => 'Use get_user_by(\'login\', $username) instead.',
            'get_users_of_blog' => 'Use get_users() instead.',
            'wp_get_http' => 'Use wp_safe_remote_get() or wp_remote_get() instead.',
            'wp_upload_dir' => 'Use wp_get_upload_dir() instead.',
            'wp_load_image' => 'Use wp_get_image_editor() instead.',
            'get_theme_data' => 'Use wp_get_theme() instead.',
            'get_current_theme' => 'Use wp_get_theme() instead.',
            'wp_create_thumbnail' => 'Use image_resize() or WP_Image_Editor() instead.',
            'wp_insert_category' => 'Use wp_insert_term() instead.',
            'wp_delete_category' => 'Use wp_delete_term() instead.',
            'wp_update_category' => 'Use wp_update_term() instead.',
            'wp_get_post_categories' => 'Use wp_get_post_terms() instead.',
            'wp_set_post_categories' => 'Use wp_set_post_terms() instead.',
            'wp_get_post_tags' => 'Use wp_get_post_terms() instead.',
            'wp_set_post_tags' => 'Use wp_set_post_terms() instead.',
            'get_category_by_slug' => 'Use get_term_by(\'slug\') with a taxonomy of category instead.',
            'get_category_link' => 'Use get_term_link() instead.',
            'get_the_category_by_ID' => 'Use get_term() instead.',
            'get_category_parents' => 'Use get_ancestors() instead.',
            'wp_list_cats' => 'Use wp_list_categories() instead.',
            'register_sidebar_widget' => 'Use wp_register_sidebar_widget() instead.',
            'unregister_sidebar_widget' => 'Use wp_unregister_sidebar_widget() instead.',
            'register_widget_control' => 'Use wp_register_widget_control() instead.',
            'unregister_widget_control' => 'Use wp_unregister_widget_control() instead.',
            'wp_specialchars' => 'Use esc_html() instead.',
            'wp_specialchars_decode' => 'Use htmlspecialchars_decode() instead.',
            'get_next_posts' => 'Use get_next_posts_link() instead.',
            'get_previous_posts' => 'Use get_previous_posts_link() instead.',
            'next_posts' => 'Use next_posts_link() instead.',
            'previous_posts' => 'Use previous_posts_link() instead.',
            'next_post' => 'Use next_post_link() instead.',
            'previous_post' => 'Use previous_post_link() instead.',
            'get_links' => 'Use get_bookmarks() instead.',
            'comment_id' => 'Use comment_ID() instead.',
            'get_the_author_email' => 'Use get_the_author_meta(\'email\') instead.',
            'get_the_author_url' => 'Use get_the_author_meta(\'url\') instead.',
            'get_the_author' => 'Use get_the_author_meta(\'display_name\') instead.',
            'the_author_email' => 'Use the_author_meta(\'email\') instead.',
            'the_author_url' => 'Use the_author_meta(\'url\') instead.',
            'the_author' => 'Use the_author_meta(\'display_name\') instead.',
            'get_post_custom_values' => 'Use get_post_meta() instead.',
            'get_post_custom' => 'Use get_post_meta() instead.',
            'get_postdata' => 'Use get_post() instead.',
            'get_themes' => 'Use wp_get_themes() instead.',
            'get_theme' => 'Use wp_get_theme() instead.',
            'get_current_theme' => 'Use wp_get_theme() instead.',
            'get_theme_data' => 'Use wp_get_theme() instead.'
        ];

        $lines = explode("\n", $content);
        $issues = [];
        foreach ($lines as $line_number => $line) {
            foreach ($deprecated_functions as $function => $message) {
                if (strpos($line, $function) !== false) {
                    $issues[] = [
						'type' => 'Suggestion',
                        'line' => $line_number + 1,
                        'message' => "Deprecated function $function used. $message"
                    ];
                }
            }
        }
        return $issues;
    }

    public function scft_custom_error_handler($errno, $errstr, $errfile, $errline) {
        $error_types = [
            E_WARNING => 'Warning',
            E_NOTICE => 'Notice',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
        ];

        if (array_key_exists($errno, $error_types)) {
            $message = "{$error_types[$errno]}: $errstr in $errfile on line $errline";
            error_log($message);
            return true;
        }
        return false;
    }

}
new SecurityCheckerForThemes();