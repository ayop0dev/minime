<?php
/**
 * Email customization for minime plugin.
 * Handles password reset emails and email branding.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Minime_Emails {

    /**
     * Initialize email hooks.
     */
    public static function init() {
        add_filter( 'wp_mail_from', array( __CLASS__, 'mail_from' ) );
        add_filter( 'wp_mail_from_name', array( __CLASS__, 'mail_from_name' ) );
        add_filter( 'retrieve_password_title', array( __CLASS__, 'reset_email_subject' ) );
        add_filter( 'retrieve_password_message', array( __CLASS__, 'reset_email_message' ), 10, 4 );
    }

    /**
     * Customize email From address to use minime branding.
     */
    public static function mail_from( $original_email ) {
        // Get site domain from home URL
        $site_url = home_url();
        $parsed = parse_url( $site_url );
        
        if ( isset( $parsed['host'] ) && ! empty( $parsed['host'] ) ) {
            $domain = $parsed['host'];
            // Remove www. if present
            $domain = preg_replace( '/^www\./i', '', $domain );
            return 'no-reply@' . $domain;
        }
        
        // Fallback to admin email if parsing fails
        $admin_email = get_option( 'admin_email' );
        return $admin_email ? $admin_email : $original_email;
    }

    /**
     * Customize email From name to use minime branding.
     */
    public static function mail_from_name( $original_name ) {
        return 'minime';
    }

    /**
     * Customize password reset email subject.
     */
    public static function reset_email_subject( $title ) {
        return __( 'Reset your minime login password', 'minime' );
    }

    /**
     * Customize password reset email message with HTML template.
     */
    public static function reset_email_message( $message, $key, $user_login, $user_data ) {
        // Enable HTML email temporarily
        add_filter( 'wp_mail_content_type', array( __CLASS__, 'mail_content_type' ) );
        
        // Build reset URL pointing to custom admin page
        $reset_url = add_query_arg(
            array(
                'minime_reset' => '1',
                'key'          => rawurlencode( $key ),
                'login'        => rawurlencode( $user_login ),
            ),
            minime_get_admin_url()
        );
        
        // Get user display name or fallback to login
        $display_name = ! empty( $user_data->display_name ) ? $user_data->display_name : $user_login;
        
        // HTML email template
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; background-color: #f3f4f6; color: #111827;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); overflow: hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding: 48px 40px 32px; text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                            <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: #111827; letter-spacing: 0.08em; text-transform: uppercase;">minime</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 40px 32px;">
                            <h2 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; color: #111827;">' . esc_html( __( 'Reset Your Password', 'minime' ) ) . '</h2>
                            
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #4b5563;">
                                ' . sprintf( __( 'Hi %s,', 'minime' ), '<strong>' . esc_html( $display_name ) . '</strong>' ) . '
                            </p>
                            
                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 1.6; color: #4b5563;">
                                ' . esc_html( __( 'Someone requested a password reset for your minime account. If this was you, click the button below to create a new password:', 'minime' ) ) . '
                            </p>
                            
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url( $reset_url ) . '" style="display: inline-block; padding: 16px 40px; font-size: 16px; font-weight: 600; color: #ffffff; background-color: #111827; text-decoration: none; border-radius: 999px; letter-spacing: 0.05em; text-transform: uppercase;">' . esc_html( __( 'Reset Your Password', 'minime' ) ) . '</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                ' . esc_html( __( 'Or copy and paste this link into your browser:', 'minime' ) ) . '
                            </p>
                            
                            <p style="margin: 0 0 32px; padding: 16px; font-size: 13px; line-height: 1.5; color: #374151; background-color: #f9fafb; border-radius: 8px; word-break: break-all; font-family: monospace;">
                                ' . esc_url( $reset_url ) . '
                            </p>
                            
                            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                ' . esc_html( __( 'If you didn\'t request a password reset, you can safely ignore this email. Your password will remain unchanged.', 'minime' ) ) . '
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 32px 40px; text-align: center; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em;">
                                ' . esc_html( __( 'Powered by minime', 'minime' ) ) . '
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        // Remove HTML content type filter after this email
        add_action( 'phpmailer_init', array( __CLASS__, 'remove_mail_content_type' ) );
        
        return $html;
    }

    /**
     * Set email content type to HTML for password reset emails.
     */
    public static function mail_content_type( $content_type ) {
        return 'text/html; charset=UTF-8';
    }

    /**
     * Remove HTML content type filter after email is sent.
     */
    public static function remove_mail_content_type() {
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'mail_content_type' ) );
        remove_action( 'phpmailer_init', array( __CLASS__, 'remove_mail_content_type' ) );
    }
}
