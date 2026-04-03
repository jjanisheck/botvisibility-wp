<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$enabled_files = $options['enabled_files'] ?? array();

$files = array(
    'llms-txt'     => array( 'name' => 'llms.txt',               'path' => '/llms.txt',                         'type' => 'text/plain' ),
    'agent-card'   => array( 'name' => 'Agent Card',             'path' => '/.well-known/agent-card.json',      'type' => 'application/json' ),
    'ai-json'      => array( 'name' => 'AI Site Profile',        'path' => '/.well-known/ai.json',              'type' => 'application/json' ),
    'skill-md'     => array( 'name' => 'Skill File',             'path' => '/skill.md',                         'type' => 'text/markdown' ),
    'skills-index' => array( 'name' => 'Skills Index',           'path' => '/.well-known/skills/index.json',    'type' => 'application/json' ),
    'openapi'      => array( 'name' => 'OpenAPI Spec',           'path' => '/openapi.json',                     'type' => 'application/json' ),
    'mcp-json'     => array( 'name' => 'MCP Manifest',           'path' => '/.well-known/mcp.json',             'type' => 'application/json' ),
);
?>

<div class="botvis-file-manager">
    <div class="botvis-file-actions-bar">
        <button id="botvis-enable-all-btn" class="botvis-btn botvis-btn-secondary">Enable All</button>
        <button id="botvis-export-all-btn" class="botvis-btn botvis-btn-secondary">Export All to Static</button>
    </div>

    <table class="botvis-file-table">
        <thead>
            <tr>
                <th>File</th>
                <th>Path</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $files as $key => $file ) :
                $is_enabled  = ! empty( $enabled_files[ $key ] );
                $has_static  = file_exists( ABSPATH . ltrim( $file['path'], '/' ) );
                $has_custom  = ! empty( $options['custom_content'][ $key ] );

                if ( $has_static ) {
                    $status_label = 'Static';
                    $status_class = 'botvis-file-static';
                } elseif ( $is_enabled ) {
                    $status_label = 'Virtual';
                    $status_class = 'botvis-file-virtual';
                } else {
                    $status_label = 'Disabled';
                    $status_class = 'botvis-file-disabled';
                }
            ?>
                <tr class="<?php echo esc_attr( $status_class ); ?>" data-file-key="<?php echo esc_attr( $key ); ?>">
                    <td class="botvis-file-name"><?php echo esc_html( $file['name'] ); ?></td>
                    <td class="botvis-file-path"><code><?php echo esc_html( $file['path'] ); ?></code></td>
                    <td><span class="botvis-file-status-badge"><?php echo esc_html( $status_label ); ?></span></td>
                    <td class="botvis-file-actions">
                        <button type="button" class="botvis-btn-sm botvis-preview-btn" data-file-key="<?php echo esc_attr( $key ); ?>">Preview</button>
                        <button type="button" class="botvis-btn-sm botvis-edit-btn" data-file-key="<?php echo esc_attr( $key ); ?>">Edit</button>
                        <label class="botvis-toggle">
                            <input type="checkbox" class="botvis-toggle-file" data-file-key="<?php echo esc_attr( $key ); ?>" <?php checked( $is_enabled ); ?>>
                            <span class="botvis-toggle-slider"></span>
                        </label>
                        <button type="button" class="botvis-btn-sm botvis-export-btn" data-file-key="<?php echo esc_attr( $key ); ?>">Export</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="botvis-file-modal" class="botvis-modal" style="display:none">
    <div class="botvis-modal-content">
        <div class="botvis-modal-header">
            <h3 id="botvis-modal-title">Preview</h3>
            <button type="button" class="botvis-modal-close">&times;</button>
        </div>
        <div class="botvis-modal-body">
            <textarea id="botvis-modal-editor" rows="20"></textarea>
        </div>
        <div class="botvis-modal-footer">
            <button type="button" class="botvis-btn botvis-btn-secondary botvis-modal-close">Cancel</button>
            <button type="button" id="botvis-modal-save" class="botvis-btn botvis-btn-primary">Save</button>
        </div>
    </div>
</div>
