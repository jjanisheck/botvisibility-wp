<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BotVisibility_OpenAPI_Generator {

    /**
     * Generate OpenAPI 3.0 spec from WordPress REST API routes.
     *
     * @return array OpenAPI spec as associative array.
     */
    public static function generate() {
        $site_name   = get_bloginfo( 'name' );
        $description = get_bloginfo( 'description' );
        $options     = get_option( 'botvisibility_options', array() );

        $spec = array(
            'openapi' => '3.0.3',
            'info'    => array(
                'title'       => sprintf( '%s API', $site_name ),
                'description' => $description ?: sprintf( 'REST API for %s', $site_name ),
                'version'     => '1.0.0',
                'contact'     => array(
                    'url' => home_url(),
                ),
            ),
            'servers' => array(
                array( 'url' => rest_url() ),
            ),
            'paths'      => array(),
            'components' => array(
                'securitySchemes' => array(
                    'application_password' => array(
                        'type'   => 'http',
                        'scheme' => 'basic',
                        'description' => 'WordPress Application Passwords (username:app-password as Basic auth).',
                    ),
                ),
            ),
        );

        // Get registered REST routes.
        $server = rest_get_server();
        $routes = $server->get_routes();

        foreach ( $routes as $route => $handlers ) {
            // Skip internal/index routes.
            if ( '/' === $route || empty( $handlers ) ) {
                continue;
            }

            // Convert WP route pattern to OpenAPI path: (?P<id>[\d]+) → {id}
            $openapi_path = preg_replace( '/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $route );

            // Skip regex-heavy routes that don't convert cleanly.
            if ( preg_match( '/[()\\\\]/', $openapi_path ) ) {
                continue;
            }

            $path_item = array();

            foreach ( $handlers as $handler ) {
                if ( ! is_array( $handler ) || empty( $handler['methods'] ) ) {
                    continue;
                }

                $methods = array_keys( $handler['methods'] );
                $args    = $handler['args'] ?? array();

                foreach ( $methods as $method ) {
                    $method_lower = strtolower( $method );
                    if ( ! in_array( $method_lower, array( 'get', 'post', 'put', 'patch', 'delete' ), true ) ) {
                        continue;
                    }

                    $operation = array(
                        'summary'   => self::generate_summary( $route, $method_lower ),
                        'responses' => array(
                            '200' => array( 'description' => 'Successful response' ),
                            '400' => array( 'description' => 'Bad request' ),
                            '401' => array( 'description' => 'Unauthorized' ),
                            '404' => array( 'description' => 'Not found' ),
                        ),
                    );

                    // Add parameters from args.
                    $parameters = self::args_to_parameters( $args, $method_lower, $openapi_path );
                    if ( ! empty( $parameters['parameters'] ) ) {
                        $operation['parameters'] = $parameters['parameters'];
                    }
                    if ( ! empty( $parameters['requestBody'] ) ) {
                        $operation['requestBody'] = $parameters['requestBody'];
                    }

                    // Write methods need auth.
                    if ( in_array( $method_lower, array( 'post', 'put', 'patch', 'delete' ), true ) ) {
                        $operation['security'] = array(
                            array( 'application_password' => array() ),
                        );
                    }

                    $path_item[ $method_lower ] = $operation;
                }
            }

            if ( ! empty( $path_item ) ) {
                $spec['paths'][ $openapi_path ] = $path_item;
            }
        }

        /**
         * Filter the generated OpenAPI spec.
         *
         * @param array $spec The OpenAPI specification.
         */
        $spec = apply_filters( 'botvisibility_openapi_spec', $spec );

        // Inject consequence labels if the feature is active.
        $features = $options['agent_features'] ?? array();
        if ( ! empty( $features['consequence_labels'] ) && ! empty( $spec['paths'] ) ) {
            foreach ( $spec['paths'] as $path => &$methods ) {
                foreach ( $methods as $method => &$op ) {
                    $upper_method = strtoupper( $method );
                    if ( in_array( $upper_method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
                        if ( 'DELETE' === $upper_method ) {
                            $op['x-consequential'] = true;
                            $op['x-irreversible']  = true;
                        } else {
                            $op['x-consequential'] = true;
                            $op['x-irreversible']  = false;
                        }
                    }
                }
                unset( $op );
            }
            unset( $methods );
        }

        return $spec;
    }

    /**
     * Generate a human-readable summary from route and method.
     */
    private static function generate_summary( $route, $method ) {
        // Extract resource name from route.
        $parts    = explode( '/', trim( $route, '/' ) );
        $resource = end( $parts );

        // Clean up regex patterns.
        $resource = preg_replace( '/\(\?P<[^>]+>[^)]+\)/', '', $resource );
        $resource = trim( $resource, '/' );

        if ( empty( $resource ) ) {
            $resource = count( $parts ) > 1 ? $parts[ count( $parts ) - 2 ] : 'resource';
        }

        $has_id = preg_match( '/\{[^}]+\}/', preg_replace( '/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $route ) );

        $verbs = array(
            'get'    => $has_id ? 'Get' : 'List',
            'post'   => 'Create',
            'put'    => 'Update',
            'patch'  => 'Update',
            'delete' => 'Delete',
        );

        return sprintf( '%s %s', $verbs[ $method ] ?? ucfirst( $method ), $resource );
    }

    /**
     * Convert WordPress REST API args to OpenAPI parameters.
     */
    private static function args_to_parameters( $args, $method, $openapi_path ) {
        $parameters  = array();
        $body_props  = array();
        $required    = array();

        // Extract path parameters.
        preg_match_all( '/\{([^}]+)\}/', $openapi_path, $path_params );
        $path_param_names = $path_params[1] ?? array();

        foreach ( $args as $name => $config ) {
            if ( ! is_array( $config ) ) {
                continue;
            }

            $type_map = array(
                'integer' => 'integer',
                'number'  => 'number',
                'string'  => 'string',
                'boolean' => 'boolean',
                'array'   => 'array',
                'object'  => 'object',
            );

            $raw_type = $config['type'] ?? 'string';
            if ( is_array( $raw_type ) ) {
                $raw_type = $raw_type[0] ?? 'string';
            }
            $param_type = $type_map[ $raw_type ] ?? 'string';

            if ( in_array( $name, $path_param_names, true ) ) {
                $param = array(
                    'name'     => $name,
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => array( 'type' => $param_type ),
                );
                if ( ! empty( $config['description'] ) ) {
                    $param['description'] = $config['description'];
                }
                $parameters[] = $param;
            } elseif ( 'get' === $method ) {
                $param = array(
                    'name'   => $name,
                    'in'     => 'query',
                    'schema' => array( 'type' => $param_type ),
                );
                if ( ! empty( $config['description'] ) ) {
                    $param['description'] = $config['description'];
                }
                if ( ! empty( $config['required'] ) ) {
                    $param['required'] = true;
                }
                if ( isset( $config['default'] ) ) {
                    $param['schema']['default'] = $config['default'];
                }
                if ( ! empty( $config['enum'] ) ) {
                    $param['schema']['enum'] = $config['enum'];
                }
                $parameters[] = $param;
            } else {
                // Body parameter for write methods.
                $prop = array( 'type' => $param_type );
                if ( ! empty( $config['description'] ) ) {
                    $prop['description'] = $config['description'];
                }
                if ( isset( $config['default'] ) ) {
                    $prop['default'] = $config['default'];
                }
                if ( ! empty( $config['enum'] ) ) {
                    $prop['enum'] = $config['enum'];
                }
                $body_props[ $name ] = $prop;

                if ( ! empty( $config['required'] ) ) {
                    $required[] = $name;
                }
            }
        }

        $result = array( 'parameters' => $parameters );

        if ( ! empty( $body_props ) ) {
            $schema = array(
                'type'       => 'object',
                'properties' => $body_props,
            );
            if ( ! empty( $required ) ) {
                $schema['required'] = $required;
            }

            $result['requestBody'] = array(
                'required' => true,
                'content'  => array(
                    'application/json' => array(
                        'schema' => $schema,
                    ),
                ),
            );
        }

        return $result;
    }
}
