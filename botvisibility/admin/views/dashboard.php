<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$levels_data = BotVisibility_Scoring::LEVELS;
?>
<div class="botvis-dashboard">
    <div id="botvis-scan-area">
        <?php if ( $cached ) : ?>
            <div id="botvis-results" data-results="<?php echo esc_attr( wp_json_encode( $cached ) ); ?>">
                <?php
                $total_passed     = 0;
                $total_applicable = 0;
                foreach ( $cached['levels'] as $lp ) {
                    $total_passed     += $lp['passed'];
                    $total_applicable += $lp['total'] - $lp['na'];
                }
                $current = $cached['currentLevel'];
                $level   = $current > 0 ? $levels_data[ $current ] : null;
                ?>
                <div class="botvis-score-header">
                    <div class="botvis-level-info">
                        <div class="botvis-level-name" style="color: <?php echo $level ? esc_attr( $level['color'] ) : 'var(--text-inverse)'; ?>">
                            <?php echo $level ? sprintf( 'Level %d: %s', $current, esc_html( $level['name'] ) ) : 'Getting Started'; ?>
                        </div>
                        <div class="botvis-level-desc">
                            <?php echo $level ? esc_html( $level['description'] ) : 'Start by making your site discoverable to AI agents.'; ?>
                        </div>
                    </div>
                    <div class="botvis-score-number">
                        <span class="botvis-score-value"><?php echo (int) $total_passed; ?></span><span class="botvis-score-total">/<?php echo (int) $total_applicable; ?></span>
                        <div class="botvis-score-label">checks passed</div>
                    </div>
                </div>

                <div class="botvis-thermometer">
                    <div class="botvis-thermometer-fill" style="width: <?php echo $total_applicable > 0 ? round( ( $total_passed / $total_applicable ) * 100 ) : 0; ?>%"></div>
                    <div class="botvis-thermometer-needle" style="left: <?php echo $total_applicable > 0 ? round( ( $total_passed / $total_applicable ) * 100 ) : 0; ?>%"></div>
                </div>

                <div class="botvis-level-bars">
                    <?php foreach ( $cached['levels'] as $num => $lp ) : ?>
                        <?php
                        $applicable = $lp['total'] - $lp['na'];
                        $pct        = $applicable > 0 ? round( ( $lp['passed'] / $applicable ) * 100 ) : 0;
                        ?>
                        <div class="botvis-level-bar">
                            <div class="botvis-level-bar-label">
                                <span style="color: <?php echo esc_attr( $lp['level']['color'] ); ?>">L<?php echo (int) $num; ?>: <?php echo esc_html( $lp['level']['name'] ); ?></span>
                                <span><?php echo (int) $lp['passed']; ?>/<?php echo (int) $applicable; ?></span>
                            </div>
                            <div class="botvis-progress-track">
                                <div class="botvis-progress-fill" style="width: <?php echo (int) $pct; ?>%; background: <?php echo esc_attr( $lp['level']['color'] ); ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php
                // Agent-Native (L4) independent section.
                $l4_progress = $cached['levels'][4] ?? null;
                $agent_native = $cached['agentNativeStatus'] ?? array( 'achieved' => false, 'rate' => 0.0 );
                if ( $l4_progress ) :
                    $l4_applicable = $l4_progress['total'] - $l4_progress['na'];
                    $l4_pct = $l4_applicable > 0 ? round( ( $l4_progress['passed'] / $l4_applicable ) * 100 ) : 0;
                ?>
                <div class="botvis-agent-native-section">
                    <div class="botvis-level-bar">
                        <div class="botvis-level-bar-label">
                            <span style="color: #8b5cf6">
                                L4: Agent-Native
                                <?php if ( $agent_native['achieved'] ) : ?>
                                    <span class="botvis-badge-achieved">Ready</span>
                                <?php endif; ?>
                            </span>
                            <span><?php echo (int) $l4_progress['passed']; ?>/<?php echo (int) $l4_applicable; ?></span>
                        </div>
                        <div class="botvis-progress-track">
                            <div class="botvis-progress-fill" style="width: <?php echo (int) $l4_pct; ?>%; background: #8b5cf6"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                </div>

                <div class="botvis-scan-meta">
                    Last scanned: <?php echo esc_html( $cached['timestamp'] ); ?>
                </div>
            </div>
        <?php else : ?>
            <div class="botvis-empty-state">
                <svg width="64" height="64" viewBox="0 0 32 32" fill="none" style="opacity:0.3"><circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2"/><circle cx="16" cy="16" r="6" stroke="currentColor" stroke-width="2"/></svg>
                <p>No scan results yet. Click "Scan Now" to analyze your site.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="botvis-actions">
        <button id="botvis-scan-btn" class="botvis-btn botvis-btn-primary">Scan Now</button>
        <button id="botvis-fix-all-btn" class="botvis-btn botvis-btn-secondary">Fix All</button>
    </div>

    <div id="botvis-scanning-progress" style="display:none">
        <div class="botvis-spinner"></div>
        <div class="botvis-scanning-text">Scanning...</div>
    </div>

    <?php if ( $cached ) : ?>
        <div class="botvis-badge-embed">
            <h3>Badge Embed Code</h3>
            <code class="botvis-embed-code">&lt;a href="https://botvisibility.com"&gt;&lt;img src="https://botvisibility.com/api/badge?url=<?php echo urlencode( home_url() ); ?>" alt="BotVisibility Score" /&gt;&lt;/a&gt;</code>
        </div>
    <?php endif; ?>
</div>
