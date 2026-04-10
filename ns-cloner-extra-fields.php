<?php
/**
 * NS Cloner – Extra Site Fields (Must-Use Plugin)
 *
 * Adds Tagline, Site Icon, and Administration Email Address fields to the
 * NS Cloner "Create New Site" section.
 *
 * HOW THIS WORKS (update-safe design):
 *  - This file lives in wp-content/mu-plugins/ and is auto-loaded by WordPress
 *    BEFORE regular plugins, completely outside the NS Cloner plugin directory.
 *  - NS Cloner plugin updates will NEVER overwrite or remove this file.
 *  - It hooks exclusively into stable NS Cloner action/filter points:
 *      • ns_cloner_close_section_box_create_target  – inject field HTML
 *      • ns_cloner_validate_site_errors             – email format validation
 *      • ns_cloner_process_finish                   – write options to cloned site
 *      • admin_enqueue_scripts                      – media picker JS
 *
 * @package NS_Cloner_Extra_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1.  RENDER – inject 3 fields at the bottom of the "Create New Site" box
// ─────────────────────────────────────────────────────────────────────────────
add_action(
	'ns_cloner_close_section_box_create_target',
	function () {
		?>
		<hr style="margin:14px 0 10px;">

		<?php /* ── Tagline ────────────────────────────────────── */ ?>
		<h5>
			<label for="target_tagline">
				<?php esc_html_e( 'Tagline', 'ns-cloner-extra-fields' ); ?>
			</label>
		</h5>
		<div class="ns-cloner-input-group">
			<input
				type="text"
				name="target_tagline"
				id="target_tagline"
				placeholder="<?php esc_attr_e( 'Just another WordPress site', 'ns-cloner-extra-fields' ); ?>"
			/>
		</div>

		<?php /* ── Site Icon ──────────────────────────────────── */ ?>
		<h5>
			<label for="target_site_icon_url">
				<?php esc_html_e( 'Site Icon', 'ns-cloner-extra-fields' ); ?>
			</label>
		</h5>
		<div class="ns-cloner-input-group" style="align-items:center;flex-wrap:wrap;gap:8px;">
			<?php /* Hidden field that carries the chosen image URL in the form post */ ?>
			<input type="hidden" name="target_site_icon_url" id="target_site_icon_url" />
			<img
				id="target_site_icon_preview"
				src=""
				alt="<?php esc_attr_e( 'Site icon preview', 'ns-cloner-extra-fields' ); ?>"
				style="display:none;width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid #ddd;"
			/>
			<button type="button" class="button ns-ecf-icon-select-btn">
				<?php esc_html_e( 'Select Site Icon', 'ns-cloner-extra-fields' ); ?>
			</button>
			<button type="button" class="button ns-ecf-icon-remove-btn" style="display:none;">
				<?php esc_html_e( 'Remove', 'ns-cloner-extra-fields' ); ?>
			</button>
			<p class="description" style="width:100%;margin:4px 0 0;">
				<?php esc_html_e( 'Recommended: square image, at least 512 × 512 px. Leave blank to inherit from source site.', 'ns-cloner-extra-fields' ); ?>
			</p>
		</div>

		<?php /* ── Administration Email Address ─────────────── */ ?>
		<h5>
			<label for="target_admin_email">
				<?php esc_html_e( 'Administration Email Address', 'ns-cloner-extra-fields' ); ?>
			</label>
		</h5>
		<div class="ns-cloner-input-group">
			<input
				type="email"
				name="target_admin_email"
				id="target_admin_email"
				placeholder="<?php esc_attr_e( 'admin@example.com', 'ns-cloner-extra-fields' ); ?>"
				data-label="<?php esc_attr_e( 'Administration Email Address', 'ns-cloner-extra-fields' ); ?>"
			/>
		</div>
		<?php
	}
);

// ─────────────────────────────────────────────────────────────────────────────
// 2.  VALIDATE – piggyback on the Create-Target section's validate() call.
//     ns_cloner_validate_site_errors is applied inside ns_wp_validate_site(),
//     which is called by NS_Cloner_Section_Create_Target::validate().
//     This means the error will appear inline (yellow highlight on the section)
//     both on the live AJAX validation AND on the final Clone button click.
// ─────────────────────────────────────────────────────────────────────────────
add_filter(
	'ns_cloner_validate_site_errors',
	function ( $errors, $site_name, $site_title ) {
		if ( ! function_exists( 'ns_cloner_request' ) ) {
			return $errors;
		}

		$admin_email = ns_cloner_request()->get( 'target_admin_email', '' );

		if ( ! empty( $admin_email ) && ! is_email( $admin_email ) ) {
			$errors[] = __( 'Administration Email Address must be a valid email address.', 'ns-cloner-extra-fields' );
		}

		return $errors;
	},
	10,
	3
);

// ─────────────────────────────────────────────────────────────────────────────
// 3.  APPLY – write field values to the newly cloned site's options table.
//     Priority 25 ensures this runs AFTER the blogname update (default 10)
//     already performed by NS_Cloner_Process_Manager::finish().
// ─────────────────────────────────────────────────────────────────────────────
add_action(
	'ns_cloner_process_finish',
	function () {
		if ( ! function_exists( 'ns_cloner_request' ) ) {
			return;
		}

		// Only run for the "core" clone mode (creates a new site).
		if ( ! ns_cloner_request()->is_mode( 'core' ) ) {
			return;
		}

		$target_id     = (int) ns_cloner_request()->get( 'target_id' );
		$target_prefix = ns_cloner_request()->get( 'target_prefix' );

		if ( ! $target_id || ! $target_prefix ) {
			return;
		}

		$db = ns_cloner()->db;

		// ── a) Tagline → blogdescription ──────────────────────────────────
		$tagline = ns_cloner_request()->get( 'target_tagline', '' );
		if ( '' !== $tagline ) {
			$db->update(
				$target_prefix . 'options',
				array( 'option_value' => sanitize_text_field( $tagline ) ),
				array( 'option_name' => 'blogdescription' ),
				array( '%s' ),
				array( '%s' )
			);
			ns_cloner()->log->log( "NS ECF: SET blogdescription for site $target_id" );
		}

		// ── b) Administration Email → admin_email ─────────────────────────
		$admin_email = ns_cloner_request()->get( 'target_admin_email', '' );
		if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
			$db->update(
				$target_prefix . 'options',
				array( 'option_value' => sanitize_email( $admin_email ) ),
				array( 'option_name' => 'admin_email' ),
				array( '%s' ),
				array( '%s' )
			);
			ns_cloner()->log->log( "NS ECF: SET admin_email for site $target_id" );
		}

		// ── c) Site Icon → sideload image, set site_icon option ───────────
		$site_icon_url = ns_cloner_request()->get( 'target_site_icon_url', '' );
		if ( ! empty( $site_icon_url ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			// Switch context to the new site so the attachment is created in its library.
			switch_to_blog( $target_id );

			$attachment_id = media_sideload_image( esc_url_raw( $site_icon_url ), 0, '', 'id' );

			if ( is_wp_error( $attachment_id ) ) {
				ns_cloner()->log->log( 'NS ECF: FAILED to sideload site icon – ' . $attachment_id->get_error_message() );
			} else {
				update_option( 'site_icon', absint( $attachment_id ) );
				ns_cloner()->log->log( "NS ECF: SET site_icon (attachment $attachment_id) for site $target_id" );
			}

			restore_current_blog();
		}
	},
	25
);

// ─────────────────────────────────────────────────────────────────────────────
// 4.  ASSETS – enqueue WordPress media library + inline JS for image picker
// ─────────────────────────────────────────────────────────────────────────────
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only load on NS Cloner admin pages.
		if ( false === strpos( $hook, 'ns-cloner' ) ) {
			return;
		}

		// Make wp.media available on this screen.
		wp_enqueue_media();

		// Inline JS – appended after the 'ns-cloner' script.
		$inline_js = <<<'JS'
jQuery(function ($) {
    var ecfMediaFrame;

    // ── Open media picker ──────────────────────────────────────────────────
    $(document).on('click', '.ns-ecf-icon-select-btn', function (e) {
        e.preventDefault();

        if (ecfMediaFrame) {
            ecfMediaFrame.open();
            return;
        }

        ecfMediaFrame = wp.media({
            title: 'Select Site Icon',
            button: { text: 'Use as Site Icon' },
            multiple: false,
            library: { type: 'image' }
        });

        ecfMediaFrame.on('select', function () {
            var attachment = ecfMediaFrame.state().get('selection').first().toJSON();

            // Prefer a medium-sized URL to keep UI preview fast;
            // fall back to full size.
            var previewUrl = (attachment.sizes && attachment.sizes.medium)
                ? attachment.sizes.medium.url
                : attachment.url;

            $('#target_site_icon_url').val(attachment.url);
            $('#target_site_icon_preview').attr('src', previewUrl).show();
            $('.ns-ecf-icon-select-btn').text('Change Icon');
            $('.ns-ecf-icon-remove-btn').show();
        });

        ecfMediaFrame.open();
    });

    // ── Remove chosen icon ─────────────────────────────────────────────────
    $(document).on('click', '.ns-ecf-icon-remove-btn', function (e) {
        e.preventDefault();
        $('#target_site_icon_url').val('');
        $('#target_site_icon_preview').hide().attr('src', '');
        $('.ns-ecf-icon-select-btn').text('Select Site Icon');
        $(this).hide();
    });
});
JS;

		wp_add_inline_script( 'ns-cloner', $inline_js );
	},
	20
);
