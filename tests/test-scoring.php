<?php
/**
 * Tests for BotVisibility_Scoring.
 */
class Test_Scoring extends BotVis_Test_Case {

    // ──────────────────────────────────────────────────────────────
    //  LEVELS constant
    // ──────────────────────────────────────────────────────────────

    public function test_levels_constant_has_four_levels() {
        $levels = BotVisibility_Scoring::LEVELS;
        $this->assertCount( 4, $levels );
        $this->assertArrayHasKey( 1, $levels );
        $this->assertArrayHasKey( 2, $levels );
        $this->assertArrayHasKey( 3, $levels );
        $this->assertArrayHasKey( 4, $levels );
    }

    public function test_level_4_is_agent_native() {
        $level4 = BotVisibility_Scoring::LEVELS[4];
        $this->assertSame( 'Agent-Native', $level4['name'] );
        $this->assertSame( '#8b5cf6', $level4['color'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  CHECK_DEFINITIONS constant
    // ──────────────────────────────────────────────────────────────

    public function test_check_definitions_has_43_entries() {
        $this->assertCount( 43, BotVisibility_Scoring::CHECK_DEFINITIONS );
    }

    public function test_check_definitions_cover_all_levels() {
        $by_level = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0 );
        foreach ( BotVisibility_Scoring::CHECK_DEFINITIONS as $def ) {
            $by_level[ $def['level'] ]++;
        }
        $this->assertSame( 18, $by_level[1] );
        $this->assertSame( 11, $by_level[2] );
        $this->assertSame( 7,  $by_level[3] );
        $this->assertSame( 7,  $by_level[4] );
    }

    public function test_new_checks_present() {
        $ids = array_column( BotVisibility_Scoring::CHECK_DEFINITIONS, 'id' );
        foreach ( array( '1.15', '1.16', '1.17', '1.18', '2.10', '2.11' ) as $new_id ) {
            $this->assertContains( $new_id, $ids, "Missing check $new_id" );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  get_current_level — Level 1 threshold
    // ──────────────────────────────────────────────────────────────

    public function test_level_1_achieved_at_50_percent() {
        $checks   = array_merge(
            $this->make_checks( 1, 7, 7 )   // 50 %
        );
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 1, $level );
    }

    public function test_level_1_not_achieved_below_50_percent() {
        $checks   = $this->make_checks( 1, 6, 8 );   // ~42.9 %
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 0, $level );
    }

    // ──────────────────────────────────────────────────────────────
    //  get_current_level — Level 2 thresholds
    // ──────────────────────────────────────────────────────────────

    public function test_level_2_requires_l1_and_l2_thresholds() {
        // L1 ≥ 50 % AND L2 ≥ 55 % (round up: 5/9 ≈ 55.6 %)
        $checks = array_merge(
            $this->make_checks( 1, 7,  7  ),  // 50 %
            $this->make_checks( 2, 5,  4  )   // 55.6 %
        );
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 2, $level );
    }

    public function test_level_2_alternate_threshold() {
        // L1 ≥ 35 % AND L2 ≥ 75 %
        // L1: 5/14 ≈ 35.7 %, L2: 7/9 ≈ 77.8 %
        $checks = array_merge(
            $this->make_checks( 1, 5,  9  ),
            $this->make_checks( 2, 7,  2  )
        );
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 2, $level );
    }

    // ──────────────────────────────────────────────────────────────
    //  Edge cases
    // ──────────────────────────────────────────────────────────────

    public function test_get_current_level_returns_0_with_no_checks() {
        $progress = BotVisibility_Scoring::calculate_level_progress( array() );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 0, $level );
    }

    public function test_na_checks_excluded_from_scoring() {
        // 7 pass + 5 fail + 2 NA → applicable = 12, rate = 7/12 ≈ 58.3 % ≥ 50 %
        $checks = array();
        foreach ( range( 1, 7 ) as $i ) {
            $checks[] = array( 'id' => "1.{$i}", 'level' => 1, 'status' => 'pass' );
        }
        foreach ( range( 8, 12 ) as $i ) {
            $checks[] = array( 'id' => "1.{$i}", 'level' => 1, 'status' => 'fail' );
        }
        $checks[] = array( 'id' => '1.13', 'level' => 1, 'status' => 'na' );
        $checks[] = array( 'id' => '1.14', 'level' => 1, 'status' => 'na' );

        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $this->assertSame( 7, $progress[1]['passed'] );
        $this->assertSame( 5, $progress[1]['failed'] );
        $this->assertSame( 2, $progress[1]['na'] );

        $level = BotVisibility_Scoring::get_current_level( $progress );
        $this->assertSame( 1, $level );
    }

    // ──────────────────────────────────────────────────────────────
    //  get_agent_native_status
    // ──────────────────────────────────────────────────────────────

    public function test_agent_native_achieved_at_50_percent() {
        $checks   = $this->make_checks( 4, 4, 3 );   // 4/7 ≈ 57.1 %
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $status   = BotVisibility_Scoring::get_agent_native_status( $progress );
        $this->assertTrue( $status['achieved'] );
    }

    public function test_agent_native_not_achieved_below_50_percent() {
        $checks   = $this->make_checks( 4, 3, 4 );   // 3/7 ≈ 42.9 %
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $status   = BotVisibility_Scoring::get_agent_native_status( $progress );
        $this->assertFalse( $status['achieved'] );
    }

    public function test_agent_native_independent_from_l1_l3() {
        // L1–L3 all 0, L4 ≥ 50 % → currentLevel = 0 but agentNative achieved
        $checks = array_merge(
            $this->make_checks( 1, 0, 14 ),
            $this->make_checks( 2, 0, 9  ),
            $this->make_checks( 3, 0, 7  ),
            $this->make_checks( 4, 4, 3  )
        );
        $progress = BotVisibility_Scoring::calculate_level_progress( $checks );
        $level    = BotVisibility_Scoring::get_current_level( $progress );
        $status   = BotVisibility_Scoring::get_agent_native_status( $progress );

        $this->assertSame( 0, $level );
        $this->assertTrue( $status['achieved'] );
    }

    // ──────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Build a flat array of synthetic check results for a given level.
     *
     * @param int $level      Check level (1–4).
     * @param int $pass_count Number of passing checks.
     * @param int $fail_count Number of failing checks.
     * @return array
     */
    private function make_checks( $level, $pass_count, $fail_count ) {
        $checks = array();
        $seq    = 1;
        for ( $i = 0; $i < $pass_count; $i++, $seq++ ) {
            $checks[] = array(
                'id'     => "{$level}.{$seq}",
                'level'  => $level,
                'status' => 'pass',
            );
        }
        for ( $i = 0; $i < $fail_count; $i++, $seq++ ) {
            $checks[] = array(
                'id'     => "{$level}.{$seq}",
                'level'  => $level,
                'status' => 'fail',
            );
        }
        return $checks;
    }
}
