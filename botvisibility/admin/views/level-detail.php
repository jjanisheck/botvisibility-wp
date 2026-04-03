<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$sub_tab     = isset( $_GET['level'] ) ? (int) $_GET['level'] : 1;
$levels_data = BotVisibility_Scoring::LEVELS;
$all_checks  = BotVisibility_Scoring::CHECK_DEFINITIONS;
?>

<div class="botvis-level-detail">
    <nav class="botvis-sub-tabs">
        <?php foreach ( $levels_data as $num => $level ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=botvisibility&tab=scan-results&level=' . $num ) ); ?>"
               class="botvis-sub-tab<?php echo $sub_tab === $num ? ' active' : ''; ?>"
               style="<?php echo $sub_tab === $num ? 'border-color:' . esc_attr( $level['color'] ) : ''; ?>">
                L<?php echo (int) $num; ?>: <?php echo esc_html( $level['name'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div id="botvis-check-list" data-level="<?php echo (int) $sub_tab; ?>">
        <?php
        $level_checks = array_filter( $all_checks, function( $c ) use ( $sub_tab ) {
            return (int) $c['level'] === $sub_tab;
        });

        $results = $cached['checks'] ?? array();
        $results_by_id = array();
        foreach ( $results as $r ) {
            $results_by_id[ $r['id'] ] = $r;
        }

        foreach ( $level_checks as $check ) :
            $result = $results_by_id[ $check['id'] ] ?? null;
            $status = $result ? $result['status'] : 'unknown';
            $status_class = 'botvis-status-' . $status;
        ?>
            <div class="botvis-check-item <?php echo esc_attr( $status_class ); ?>" data-check-id="<?php echo esc_attr( $check['id'] ); ?>">
                <button type="button" class="botvis-check-header" aria-expanded="false">
                    <span class="botvis-check-status-icon" data-status="<?php echo esc_attr( $status ); ?>"></span>
                    <span class="botvis-check-id"><?php echo esc_html( $check['id'] ); ?></span>
                    <span class="botvis-check-name"><?php echo esc_html( $check['name'] ); ?></span>
                    <span class="botvis-check-caret">&#9660;</span>
                </button>
                <div class="botvis-check-details" style="display:none">
                    <p class="botvis-check-desc"><?php echo esc_html( $check['description'] ); ?></p>
                    <?php if ( $result ) : ?>
                        <p class="botvis-check-message"><?php echo esc_html( $result['message'] ); ?></p>
                        <?php if ( ! empty( $result['details'] ) ) : ?>
                            <p class="botvis-check-detail-text"><?php echo esc_html( $result['details'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( ! empty( $result['recommendation'] ) ) : ?>
                            <p class="botvis-check-recommendation"><?php echo esc_html( $result['recommendation'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( 'pass' !== $status && 'na' !== $status ) : ?>
                            <button type="button" class="botvis-btn botvis-btn-fix" data-check-id="<?php echo esc_attr( $check['id'] ); ?>">Fix</button>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="botvis-check-no-result">Run a scan to see results for this check.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
