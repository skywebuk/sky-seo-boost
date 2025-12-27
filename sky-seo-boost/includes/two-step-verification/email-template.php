<?php
/**
 * Sky SEO Boost - Two-Step Verification Email Template
 *
 * Modern, clean email template for 2FA verification codes
 *
 * @package Sky_SEO_Boost
 * @since 4.4.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the HTML email template for 2FA verification
 *
 * @param string $user_name The user's display name
 * @param string $site_name The site name
 * @param string $token The 8-digit verification code
 * @param int $expiry_minutes Token expiry time in minutes
 * @return string HTML email content
 */
function sky_seo_get_2fa_email_template($user_name, $site_name, $token, $expiry_minutes) {
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Step Verification Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #000000 !important; line-height: 1.6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #000000 !important; padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; text-align: center; background: #000000; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">
                                <span style="display: inline-block; background: #ffffff; border-radius: 6px; padding: 4px 8px; margin-right: 12px; vertical-align: middle; line-height: 0;">
                                    <img src="<?php echo esc_url(plugins_url('assets/img/skyweb_logo_dark.png', dirname(dirname(__FILE__)))); ?>" alt="Sky Web" style="height: 28px; width: auto; vertical-align: middle; display: block;" />
                                </span>Sky Security
                            </h1>
                            <p style="margin: 8px 0 0 0; color: #cccccc; font-size: 14px; font-weight: 400;">
                                Two-Step Verification
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 24px 0; color: #333333; font-size: 16px;">
                                Hi <strong><?php echo esc_html($user_name); ?></strong>,
                            </p>

                            <p style="margin: 0 0 24px 0; color: #555555; font-size: 15px;">
                                Someone is attempting to log into your account on <strong><?php echo esc_html($site_name); ?></strong>.
                            </p>

                            <p style="margin: 0 0 16px 0; color: #555555; font-size: 15px;">
                                Your verification code is:
                            </p>

                            <!-- Verification Code Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 0 0 32px 0;">
                                        <div style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 3px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                            <div style="background-color: #ffffff; border-radius: 10px; padding: 24px 48px;">
                                                <p style="margin: 0; font-size: 40px; font-weight: 700; letter-spacing: 10px; color: #667eea; font-family: 'Courier New', monospace; text-align: center;">
                                                    <?php echo esc_html($token); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiry Notice -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fff9e6; border-left: 4px solid #ffc107; padding: 16px; border-radius: 4px;">
                                        <p style="margin: 0; color: #856404; font-size: 14px;">
                                            This code will expire in <strong><?php echo esc_html($expiry_minutes); ?> minutes</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 16px 0; color: #555555; font-size: 14px;">
                                If you did not request this code, please ignore this email or contact the site administrator if you have concerns.
                            </p>

                            <p style="margin: 0; color: #555555; font-size: 14px;">
                                Thank you for keeping your account secure!
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 8px 0; color: #6c757d; font-size: 13px;">
                                This is an automated message from
                            </p>
                            <p style="margin: 0; color: #495057; font-size: 14px; font-weight: 600;">
                                <?php echo esc_html($site_name); ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    <?php
    return ob_get_clean();
}
