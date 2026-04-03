# Contributing to BotVisibility for WordPress

Thank you for your interest in contributing. This guide covers everything you need to get the development environment running, write and run tests, and submit a pull request.

## Prerequisites

- [Docker](https://www.docker.com/get-started) (required by `wp-env`)
- [Node.js](https://nodejs.org/) 18+
- [Composer](https://getcomposer.org/) 2+

## Getting Started

```bash
# Clone the repository
git clone https://github.com/jjanisheck/botvisibility-wp.git
cd botvisibility-wp

# Install JavaScript dependencies (wp-env)
npm install

# Install PHP dependencies (PHPUnit, etc.)
composer install

# Start the local WordPress environment
npx wp-env start

# Verify everything is working
composer test
```

`wp-env` boots two containers: a WordPress site (`http://localhost:8888`) and a test environment (`http://localhost:8889`). The plugin is automatically mapped into both.

## Running Tests

### All tests

```bash
composer test
```

### Core suite only (scoring, scanner, file generation, REST enhancements)

```bash
composer test-core
```

### Agent-native suite only (sessions, tokens, audit logs, sandbox, tool schemas)

```bash
composer test-agent
```

### Single test file

```bash
npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit tests/test-scoring.php
```

### Single test method

```bash
npx wp-env run tests-cli --env-cwd=wp-content/plugins/botvisibility-wp vendor/bin/phpunit tests/test-scoring.php --filter test_method_name
```

## Writing Tests

### File and class naming

| Element | Convention | Example |
|---|---|---|
| File name | `test-<class-slug>.php` | `test-scoring.php` |
| Class name | `Test_ClassName` | `Test_Scoring` |
| Method name | `test_<behavior>` | `test_level_advances_at_threshold` |

All test classes must extend `BotVis_Test_Case`.

### Available helpers

| Helper | Purpose |
|---|---|
| `enable_feature( $key )` | Enable an agent-native feature by key |
| `create_agent_user( $role )` | Create a WP user and set as current user |
| `make_agent_request( $method, $route, $params, $headers )` | Dispatch a REST API request |
| `set_option_value( $key, $value )` | Write a key into `botvisibility_options` |

### Example test

```php
<?php
/**
 * Tests for BotVisibility_Scoring.
 */
class Test_Scoring extends BotVis_Test_Case {

    public function test_level_one_achieved_at_fifty_percent() {
        // Arrange: set exactly 50% of L1 checks passing.
        $results = array_fill( 0, 7, 'pass' ) + array_fill( 7, 7, 'fail' );

        // Act.
        $scoring = new BotVisibility_Scoring( $results );
        $level   = $scoring->get_current_level();

        // Assert.
        $this->assertGreaterThanOrEqual( 1, $level );
    }
}
```

## Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- Always sanitize user input with `sanitize_text_field()`, `sanitize_url()`, `wp_kses_post()`, etc.
- All write operations must include capability checks (`current_user_can()`).
- Run PHPCS locally before pushing: `vendor/bin/phpcs --standard=WordPress`.

## Pull Requests

1. Fork the repository and create a branch from `main`.
2. Make your changes. All existing tests must pass and new behaviour must be covered by new tests.
3. Push your branch and open a pull request against `main`.
4. Describe what changed and why in the PR description.

Pull requests that break the test suite will not be merged.
