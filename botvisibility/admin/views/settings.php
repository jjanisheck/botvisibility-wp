<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$capabilities_options = array(
    'content'     => 'Content / Blog',
    'api'         => 'API / Developer Platform',
    'ecommerce'   => 'E-Commerce',
    'saas'        => 'SaaS Application',
    'community'   => 'Community / Forum',
    'docs'        => 'Documentation',
    'media'       => 'Media / Images / Video',
    'education'   => 'Education / Courses',
);

$selected_caps = $options['capabilities'] ?? array( 'content' );
?>

<div class="botvis-settings">
    <form id="botvis-settings-form">
        <div class="botvis-setting-group">
            <h3>Site Description</h3>
            <p class="botvis-setting-desc">Used in llms.txt, agent-card.json, and other generated files.</p>
            <textarea name="site_description" rows="3" class="botvis-textarea"><?php echo esc_textarea( $options['site_description'] ?? get_bloginfo( 'description' ) ); ?></textarea>
        </div>

        <div class="botvis-setting-group">
            <h3>Capabilities</h3>
            <p class="botvis-setting-desc">What does your site offer? Included in agent-card.json and ai.json.</p>
            <div class="botvis-checkboxes">
                <?php foreach ( $capabilities_options as $value => $label ) : ?>
                    <label class="botvis-checkbox-label">
                        <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $selected_caps, true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="botvis-setting-group">
            <h3>REST API Enhancements</h3>
            <p class="botvis-setting-desc">Toggle additional headers on your REST API responses.</p>

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_cors" value="1" <?php checked( ! empty( $options['enable_cors'] ) ); ?>>
                <span>CORS Headers</span> <small>Allow cross-origin API access</small>
            </label>

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_cache_headers" value="1" <?php checked( ! empty( $options['enable_cache_headers'] ) ); ?>>
                <span>Caching Headers</span> <small>Add ETag and Cache-Control</small>
            </label>

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_rate_limits" value="1" <?php checked( ! empty( $options['enable_rate_limits'] ) ); ?>>
                <span>Rate Limit Headers</span> <small>Add X-RateLimit-* headers</small>
            </label>

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_idempotency" value="1" <?php checked( ! empty( $options['enable_idempotency'] ) ); ?>>
                <span>Idempotency Support</span> <small>Accept Idempotency-Key header</small>
            </label>
        </div>

        <div class="botvis-setting-group">
            <h3>robots.txt AI Policy</h3>
            <p class="botvis-setting-desc">How should AI crawlers be handled?</p>
            <select name="robots_ai_policy" class="botvis-select">
                <option value="allow" <?php selected( $options['robots_ai_policy'] ?? 'allow', 'allow' ); ?>>Allow AI crawlers</option>
                <option value="block" <?php selected( $options['robots_ai_policy'] ?? 'allow', 'block' ); ?>>Block AI crawlers</option>
            </select>
        </div>

        <div class="botvis-setting-group">
            <h3>Auto-Scan Schedule</h3>
            <select name="auto_scan_schedule" class="botvis-select">
                <option value="daily" <?php selected( $options['auto_scan_schedule'] ?? 'weekly', 'daily' ); ?>>Daily</option>
                <option value="weekly" <?php selected( $options['auto_scan_schedule'] ?? 'weekly', 'weekly' ); ?>>Weekly</option>
                <option value="disabled" <?php selected( $options['auto_scan_schedule'] ?? 'weekly', 'disabled' ); ?>>Disabled</option>
            </select>
        </div>

        <div class="botvis-setting-group">
            <h3>Agent Infrastructure</h3>
            <p class="botvis-setting-desc">Level 4 features that add agent-native capabilities to your site. Each feature can be individually enabled or disabled.</p>

            <?php
            $agent_features = $options['agent_features'] ?? array();
            foreach ( BotVisibility_Agent_Infrastructure::FEATURES as $key => $feature ) :
            ?>
                <label class="botvis-switch-label">
                    <input type="checkbox"
                           name="agent_features[<?php echo esc_attr( $key ); ?>]"
                           value="1"
                           class="botvis-agent-feature-toggle"
                           data-feature-key="<?php echo esc_attr( $key ); ?>"
                           <?php checked( ! empty( $agent_features[ $key ] ) ); ?>>
                    <span><?php echo esc_html( $feature['name'] ); ?></span>
                    <small><?php echo esc_html( $feature['description'] ); ?></small>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="botvis-setting-group">
            <h3>Cleanup</h3>
            <label class="botvis-switch-label">
                <input type="checkbox" name="remove_files_on_deactivate" value="1" <?php checked( ! empty( $options['remove_files_on_deactivate'] ) ); ?>>
                <span>Remove generated static files on deactivation</span>
            </label>
        </div>

        <div class="botvis-setting-actions">
            <button type="submit" class="botvis-btn botvis-btn-primary">Save Settings</button>
        </div>
    </form>
</div>
