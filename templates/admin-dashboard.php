<?php
/**
 * Admin dashboard template for WordPress backend.
 * Displays read-only overview and settings form.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'minime – Settings', 'minime' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Edit your minime settings here. These values are used by the public card and the admin panel.', 'minime' ); ?>
    </p>

    <hr />

    <form method="post" action="">
        <?php wp_nonce_field( 'minime_save_overview', 'minime_overview_nonce' ); ?>
        <input type="hidden" name="minime_overview_form" value="1">

        <h2><?php esc_html_e( 'Identity', 'minime' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="site_title"><?php esc_html_e( 'Site Title (Name)', 'minime' ); ?></label></th>
                <td>
                    <input type="text" id="site_title" name="site_title" value="<?php echo esc_attr( $site_title ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Used as the main title on your minime card.', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="site_tagline"><?php esc_html_e( 'Tagline (Subtitle)', 'minime' ); ?></label></th>
                <td>
                    <input type="text" id="site_tagline" name="site_tagline" value="<?php echo esc_attr( $site_tagline ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'A short tagline or subtitle.', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="bio"><?php esc_html_e( 'Bio', 'minime' ); ?></label></th>
                <td>
                    <textarea id="bio" name="bio" rows="4" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Card description or bio text.', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Avatar / Site Icon', 'minime' ); ?></th>
                <td>
                    <?php if ( $site_icon_url ) : ?>
                        <img src="<?php echo esc_url( $site_icon_url ); ?>" alt="" style="width:64px;height:64px;border-radius:50%;border:1px solid #ccd0d4;object-fit:cover;margin-bottom:8px;display:block;" />
                    <?php endif; ?>
                    <input type="hidden" id="site_icon_id" name="site_icon_id" value="<?php echo esc_attr( $site_icon_id ); ?>">
                    <input type="text" id="site_icon_url_display" value="<?php echo esc_attr( $site_icon_url ); ?>" class="regular-text" readonly>
                    <p class="description"><?php printf( __( 'This is the same avatar used on the public minime card. To change it, use the <a href="%s">Site Icon</a> setting or the minime admin panel.', 'minime' ), esc_url( admin_url( 'options-general.php' ) ) ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Background', 'minime' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="background_type"><?php esc_html_e( 'Background Type', 'minime' ); ?></label></th>
                <td>
                    <?php $bg_type = isset( $background['type'] ) ? $background['type'] : 'image'; ?>
                    <select id="background_type" name="background_type" class="regular-text">
                        <option value="image" <?php selected( $bg_type, 'image' ); ?>><?php esc_html_e( 'Image', 'minime' ); ?></option>
                        <option value="color" <?php selected( $bg_type, 'color' ); ?>><?php esc_html_e( 'Solid Color', 'minime' ); ?></option>
                        <option value="gradient" <?php selected( $bg_type, 'gradient' ); ?>><?php esc_html_e( 'Gradient', 'minime' ); ?></option>
                        <option value="custom" <?php selected( $bg_type, 'custom' ); ?>><?php esc_html_e( 'Custom HTML/CSS', 'minime' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="background_image_id"><?php esc_html_e( 'Image ID', 'minime' ); ?></label></th>
                <td>
                    <input type="number" id="background_image_id" name="background_image_id" value="<?php echo esc_attr( isset( $background['image_id'] ) ? $background['image_id'] : 0 ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'WordPress Media Library attachment ID for background image.', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="background_color"><?php esc_html_e( 'Background Color', 'minime' ); ?></label></th>
                <td>
                    <input type="text" id="background_color" name="background_color" value="<?php echo esc_attr( isset( $background['color'] ) ? $background['color'] : '#000000' ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Hex color code (e.g., #000000).', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="background_gradient_colors"><?php esc_html_e( 'Gradient Colors', 'minime' ); ?></label></th>
                <td>
                    <?php
                    $gradient = isset( $background['gradient'] ) ? $background['gradient'] : array();
                    $colors = isset( $gradient['colors'] ) && is_array( $gradient['colors'] ) ? $gradient['colors'] : array();
                    $colors_str = implode( ', ', $colors );
                    ?>
                    <input type="text" id="background_gradient_colors" name="background_gradient_colors" value="<?php echo esc_attr( $colors_str ); ?>" class="large-text">
                    <p class="description"><?php esc_html_e( 'Comma-separated hex colors (e.g., #111827, #1f2937, #374151).', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="background_gradient_angle"><?php esc_html_e( 'Gradient Angle', 'minime' ); ?></label></th>
                <td>
                    <?php $angle = isset( $gradient['angle'] ) ? (int) $gradient['angle'] : 180; ?>
                    <input type="number" id="background_gradient_angle" name="background_gradient_angle" value="<?php echo esc_attr( $angle ); ?>" min="0" max="360" step="1" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Angle in degrees (0–360).', 'minime' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="background_custom_code"><?php esc_html_e( 'Custom HTML/CSS', 'minime' ); ?></label></th>
                <td>
                    <textarea id="background_custom_code" name="background_custom_code" rows="6" class="large-text code"><?php echo esc_textarea( isset( $background['custom_code'] ) ? $background['custom_code'] : '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Custom HTML/CSS code for background. Scripts are not allowed.', 'minime' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'minime' ); ?></button>
        </p>
    </form>

    <hr />

    <h2><?php esc_html_e( 'Social Links', 'minime' ); ?></h2>
    <?php
    $socials = isset( $settings['socials'] ) && is_array( $settings['socials'] ) ? $settings['socials'] : array();
    if ( $socials ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Type', 'minime' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'minime' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $socials as $row ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( isset( $row['type'] ) ? $row['type'] : '' ); ?></code></td>
                        <td><code><?php echo esc_html( isset( $row['value'] ) ? $row['value'] : '' ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><em><?php esc_html_e( 'No social links defined.', 'minime' ); ?></em></p>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Buttons', 'minime' ); ?></h2>
    <?php
    $buttons = isset( $settings['buttons'] ) && is_array( $settings['buttons'] ) ? $settings['buttons'] : array();
    if ( $buttons ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Label', 'minime' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'minime' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $buttons as $row ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( isset( $row['label'] ) ? $row['label'] : '' ); ?></code></td>
                        <td><code><?php echo esc_html( isset( $row['value'] ) ? $row['value'] : '' ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><em><?php esc_html_e( 'No buttons defined.', 'minime' ); ?></em></p>
    <?php endif; ?>

    <hr />

    <p>
        <a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary" target="_blank" rel="noopener">
            <?php esc_html_e( 'Open minime admin panel', 'minime' ); ?>
        </a>
        &nbsp;
        <a href="<?php echo esc_url( $public_url ); ?>" class="button" target="_blank" rel="noopener">
            <?php esc_html_e( 'View public minime page', 'minime' ); ?>
        </a>
    </p>
</div>
