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

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_markdown_for_agents" value="1" <?php checked( ! empty( $options['enable_markdown_for_agents'] ) ); ?>>
                <span>Markdown for Agents</span> <small>Serve posts/pages as markdown when <code>Accept: text/markdown</code></small>
            </label>

            <label class="botvis-switch-label">
                <input type="checkbox" name="enable_webmcp" value="1" <?php checked( ! empty( $options['enable_webmcp'] ) ); ?>>
                <span>WebMCP</span> <small>Expose in-browser tools via <code>navigator.modelContext.provideContext()</code> on the homepage</small>
            </label>
        </div>

        <div class="botvis-setting-group">
            <h3>Content Signals</h3>
            <p class="botvis-setting-desc">Declare AI content usage preferences in robots.txt (<a href="https://contentsignals.org" target="_blank" rel="noopener">contentsignals.org</a>).</p>
            <?php
            $cs          = $options['content_signals'] ?? array();
            $signal_keys = array(
                'search'   => 'Search crawlers',
                'ai-train' => 'AI training',
                'ai-input' => 'AI input / grounding',
            );
            foreach ( $signal_keys as $skey => $label ) :
                $val = $cs[ $skey ] ?? '';
            ?>
                <div class="botvis-content-signal-row" style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                    <label style="flex:1;"><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $skey ); ?></code></label>
                    <select name="content_signals[<?php echo esc_attr( $skey ); ?>]" class="botvis-select" style="max-width:160px;">
                        <option value=""    <?php selected( $val, '' ); ?>>Unset</option>
                        <option value="yes" <?php selected( $val, 'yes' ); ?>>Allow (yes)</option>
                        <option value="no"  <?php selected( $val, 'no' ); ?>>Deny (no)</option>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="botvis-setting-group">
            <h3>x402 Payments</h3>
            <p class="botvis-setting-desc">Expose a gated endpoint at <code>/wp-json/botvisibility/v1/paid-preview</code> that returns HTTP 402 with machine-readable payment requirements. This advertises the x402 surface to agents; it does not perform on-chain verification.</p>
            <?php $x402 = $options['x402'] ?? array(); ?>
            <label class="botvis-switch-label">
                <input type="checkbox" name="x402[enabled]" value="1" <?php checked( ! empty( $x402['enabled'] ) ); ?>>
                <span>Enable x402 endpoint</span>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <label>
                    <span>Network</span>
                    <input type="text" name="x402[network]" value="<?php echo esc_attr( $x402['network'] ?? 'base-sepolia' ); ?>" class="botvis-input" />
                </label>
                <label>
                    <span>Asset</span>
                    <input type="text" name="x402[asset]" value="<?php echo esc_attr( $x402['asset'] ?? 'USDC' ); ?>" class="botvis-input" />
                </label>
                <label>
                    <span>Pay-to address</span>
                    <input type="text" name="x402[pay_to]" value="<?php echo esc_attr( $x402['pay_to'] ?? '' ); ?>" class="botvis-input" placeholder="0x..." />
                </label>
                <label>
                    <span>Max amount (atomic)</span>
                    <input type="text" name="x402[max_amount_required]" value="<?php echo esc_attr( $x402['max_amount_required'] ?? '10000' ); ?>" class="botvis-input" />
                </label>
                <label style="grid-column:1/-1;">
                    <span>Resource description</span>
                    <input type="text" name="x402[resource_description]" value="<?php echo esc_attr( $x402['resource_description'] ?? 'Premium preview access' ); ?>" class="botvis-input" />
                </label>
            </div>
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
