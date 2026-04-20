<?php
/**
 * Tests for BotVisibility_Scanner (L1–L3 focused).
 */
class Test_Scanner extends BotVis_Test_Case {

    // ──────────────────────────────────────────────────────────────
    //  Basic scanner output
    // ──────────────────────────────────────────────────────────────

    public function test_run_all_checks_returns_43_results() {
        $result = BotVisibility_Scanner::run_all_checks();
        $this->assertCount( 43, $result['checks'] );
    }

    public function test_result_array_has_required_fields() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $result['checks'][0];

        $this->assertArrayHasKey( 'id',             $check );
        $this->assertArrayHasKey( 'name',           $check );
        $this->assertArrayHasKey( 'passed',         $check );
        $this->assertArrayHasKey( 'status',         $check );
        $this->assertArrayHasKey( 'level',          $check );
        $this->assertArrayHasKey( 'category',       $check );
        $this->assertArrayHasKey( 'message',        $check );
        $this->assertArrayHasKey( 'details',        $check );
        $this->assertArrayHasKey( 'recommendation', $check );
        $this->assertArrayHasKey( 'feature_key',    $check );
    }

    public function test_result_includes_levels_and_current_level() {
        $result = BotVisibility_Scanner::run_all_checks();
        $this->assertArrayHasKey( 'levels',            $result );
        $this->assertArrayHasKey( 'currentLevel',      $result );
        $this->assertArrayHasKey( 'agentNativeStatus', $result );
    }

    // ──────────────────────────────────────────────────────────────
    //  Transient caching
    // ──────────────────────────────────────────────────────────────

    public function test_scan_results_cached_in_transient() {
        BotVisibility_Scanner::run_all_checks();
        $cached = get_transient( 'botvis_scan_results' );
        $this->assertNotFalse( $cached );
        $this->assertArrayHasKey( 'checks', $cached );
    }

    // ──────────────────────────────────────────────────────────────
    //  Specific L1 checks
    // ──────────────────────────────────────────────────────────────

    public function test_check_rss_feed_always_passes() {
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.14' );
        $this->assertNotNull( $check );
        $this->assertSame( 'pass', $check['status'] );
    }

    public function test_check_llms_txt_pass_with_virtual_file() {
        // Default options have enabled_files set; llms.txt virtual route is active.
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.1' );
        $this->assertNotNull( $check );
        $this->assertContains( $check['status'], array( 'pass', 'partial' ) );
    }

    public function test_check_llms_txt_fail_without_file() {
        $this->set_option_value( 'enabled_files', array() );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.1' );
        $this->assertNotNull( $check );
        $this->assertSame( 'fail', $check['status'] );
    }

    public function test_check_cors_headers_fail_when_disabled() {
        $this->set_option_value( 'enable_cors', false );
        $result = BotVisibility_Scanner::run_all_checks();
        $check  = $this->find_check( $result['checks'], '1.6' );
        $this->assertNotNull( $check );
        $this->assertSame( 'fail', $check['status'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  Validity assertions across all checks
    // ──────────────────────────────────────────────────────────────

    public function test_all_checks_have_valid_level() {
        $result = BotVisibility_Scanner::run_all_checks();
        foreach ( $result['checks'] as $check ) {
            $this->assertContains(
                (int) $check['level'],
                array( 1, 2, 3, 4 ),
                "Check {$check['id']} has unexpected level {$check['level']}"
            );
        }
    }

    public function test_all_checks_have_valid_status() {
        $result = BotVisibility_Scanner::run_all_checks();
        foreach ( $result['checks'] as $check ) {
            $this->assertContains(
                $check['status'],
                array( 'pass', 'partial', 'fail', 'na' ),
                "Check {$check['id']} has unexpected status {$check['status']}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Find a check result by its ID.
     *
     * @param array  $checks Array of check result arrays.
     * @param string $id     Check ID (e.g. '1.14').
     * @return array|null
     */
    private function find_check( $checks, $id ) {
        foreach ( $checks as $check ) {
            if ( $check['id'] === $id ) {
                return $check;
            }
        }
        return null;
    }
}
