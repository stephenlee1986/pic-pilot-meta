<?php
defined('ABSPATH') || exit;

use PicPilotMeta\Admin\DashboardController;
use PicPilotMeta\Admin\ScanController;

$stats = DashboardController::get_dashboard_stats();
$recent_scans = DashboardController::get_recent_scans();
$scanned_post_types = ScanController::get_scanned_post_types();
?>

<div class="wrap pic-pilot-dashboard">
    <h1><?php esc_html_e('Pic Pilot Meta Dashboard', 'pic-pilot-meta'); ?></h1>
    
    <!-- Quick Stats Header -->
    <div class="dashboard-header">
        <?php if ($stats['has_scan']): ?>
            <div class="stats-grid">
                <div class="stat-card stat-total">
                    <div class="stat-number"><?php echo number_format($stats['total_images']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Images', 'pic-pilot-meta'); ?></div>
                </div>
                
                <div class="stat-card stat-issues">
                    <div class="stat-number"><?php echo number_format($stats['total_issues']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Issues Found', 'pic-pilot-meta'); ?></div>
                </div>
                
                <div class="stat-card stat-progress">
                    <div class="stat-number"><?php echo absint($stats['completion_percentage']); ?>%</div>
                    <div class="stat-label"><?php esc_html_e('Complete', 'pic-pilot-meta'); ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo absint($stats['completion_percentage']); ?>%"></div>
                    </div>
                </div>
                
                <div class="stat-card stat-pages">
                    <div class="stat-number"><?php echo number_format($stats['pages_with_issues']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Pages Affected', 'pic-pilot-meta'); ?></div>
                </div>
            </div>
            
            <!-- Priority Breakdown -->
            <div class="priority-breakdown">
                <h3><?php esc_html_e('Issue Breakdown', 'pic-pilot-meta'); ?></h3>
                <div class="priority-grid">
                    <div class="priority-item critical">
                        <span class="priority-count"><?php echo absint($stats['critical_issues']); ?></span>
                        <span class="priority-label"><?php esc_html_e('Critical', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="priority-item high">
                        <span class="priority-count"><?php echo absint($stats['high_issues']); ?></span>
                        <span class="priority-label"><?php esc_html_e('High', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="priority-item medium">
                        <span class="priority-count"><?php echo absint($stats['medium_issues']); ?></span>
                        <span class="priority-label"><?php esc_html_e('Medium', 'pic-pilot-meta'); ?></span>
                    </div>
                </div>
                
                <!-- Priority Legend -->
                <div class="priority-explanations">
                    <div class="priority-explanation">
                        <span class="priority-badge priority-critical">Critical</span>
                        <span class="priority-description"><?php esc_html_e('Missing both attributes', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="priority-explanation">
                        <span class="priority-badge priority-high">High</span>
                        <span class="priority-description"><?php esc_html_e('Important content images', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="priority-explanation">
                        <span class="priority-badge priority-medium">Medium</span>
                        <span class="priority-description"><?php esc_html_e('Standard content images', 'pic-pilot-meta'); ?></span>
                    </div>
                </div>
                
                <!-- Scan Info -->
                <div class="scan-notes">
                    <ul>
                        <li><strong><?php esc_html_e('Missing Images', 'pic-pilot-meta'); ?></strong></li>
                        <li><strong><?php esc_html_e('External Images', 'pic-pilot-meta'); ?></strong></li>
                        <li><strong><?php esc_html_e('Page Builders', 'pic-pilot-meta'); ?></strong></li>
                    </ul>
                </div>
            </div>
            
            <!-- Missing Attributes Breakdown -->
            <div class="attributes-breakdown">
                <h3><?php esc_html_e('Missing Attributes', 'pic-pilot-meta'); ?></h3>
                <div class="attributes-grid">
                    <div class="attribute-item missing-both">
                        <span class="attribute-count"><?php echo absint($stats['missing_both']); ?></span>
                        <span class="attribute-label"><?php esc_html_e('Missing Both', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="attribute-item missing-alt">
                        <span class="attribute-count"><?php echo absint($stats['missing_alt']); ?></span>
                        <span class="attribute-label"><?php esc_html_e('Missing Alt Text', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="attribute-item missing-title">
                        <span class="attribute-count"><?php echo absint($stats['missing_title']); ?></span>
                        <span class="attribute-label"><?php esc_html_e('Missing Title', 'pic-pilot-meta'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Priority System Explanation Link -->
            <div class="priority-help-link">
                <a href="#priority-explanation" class="button button-link">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('Understanding Priority Levels', 'pic-pilot-meta'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="no-scan-message">
                <div class="no-scan-icon">📊</div>
                <h2><?php esc_html_e('Welcome to Pic Pilot Dashboard', 'pic-pilot-meta'); ?></h2>
                <p><?php echo esc_html($stats['message']); ?></p>

                <!-- Priority System Explanation Link for No Scan State -->
                <div class="priority-help-link">
                    <a href="#priority-explanation" class="button button-link">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e('Understanding Priority Levels', 'pic-pilot-meta'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="dashboard-actions">
        <button type="button" class="button button-primary button-hero" id="start-scan">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('Scan Now', 'pic-pilot-meta'); ?>
        </button>
        
        <?php if ($stats['has_scan']): ?>
            <button type="button" class="button button-secondary" id="view-issues">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('View Issues', 'pic-pilot-meta'); ?>
            </button>
            
            <button type="button" class="button button-secondary" id="export-report">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export Report', 'pic-pilot-meta'); ?>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Scan Progress (Hidden by default) -->
    <div class="scan-progress" id="scan-progress" style="display: none;">
        <div class="scan-status">
            <div class="scan-icon">
                <span class="dashicons dashicons-update spin"></span>
            </div>
            <div class="scan-text">
                <div class="scan-message"><?php esc_html_e('Preparing scan...', 'pic-pilot-meta'); ?></div>
                <div class="scan-details"></div>
            </div>
        </div>
        <div class="scan-progress-bar">
            <div class="scan-progress-fill" style="width: 0%"></div>
        </div>
        <button type="button" class="button" id="cancel-scan">
            <?php esc_html_e('Cancel', 'pic-pilot-meta'); ?>
        </button>
    </div>
    
    <!-- Issues Table (Hidden by default) -->
    <div class="issues-section" id="issues-section" style="display: none;">
        <div class="issues-header">
            <h2><?php esc_html_e('Accessibility Issues', 'pic-pilot-meta'); ?></h2>
            
            <!-- Filters -->
            <div class="issues-filters">
                <select id="filter-priority">
                    <option value=""><?php esc_html_e('All Priorities', 'pic-pilot-meta'); ?></option>
                    <option value="critical"><?php esc_html_e('Critical Only', 'pic-pilot-meta'); ?></option>
                    <option value="high"><?php esc_html_e('High Priority', 'pic-pilot-meta'); ?></option>
                    <option value="medium"><?php esc_html_e('Medium Priority', 'pic-pilot-meta'); ?></option>
                </select>
                
                <select id="filter-attribute">
                    <option value=""><?php esc_html_e('All Issues', 'pic-pilot-meta'); ?></option>
                    <option value="missing-alt"><?php esc_html_e('Missing Alt tag', 'pic-pilot-meta'); ?></option>
                    <option value="missing-title"><?php esc_html_e('Missing title', 'pic-pilot-meta'); ?></option>
                    <option value="missing-both"><?php esc_html_e('Missing alt tag and title', 'pic-pilot-meta'); ?></option>
                </select>
                
                <select id="filter-page-type">
                    <option value=""><?php esc_html_e('All Page Types', 'pic-pilot-meta'); ?></option>
                    <?php foreach ($scanned_post_types as $post_type => $label): ?>
                        <option value="<?php echo esc_attr($post_type); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <input type="search" id="search-issues" placeholder="<?php esc_html_e('Search pages or images...', 'pic-pilot-meta'); ?>">
                
                <button type="button" class="button" id="apply-filters">
                    <?php esc_html_e('Apply Filters', 'pic-pilot-meta'); ?>
                </button>
            </div>
        </div>
        
        <!-- Issues Table -->
        <div class="issues-table-container">
            <table class="wp-list-table widefat fixed striped" id="issues-table">
                <thead>
                    <tr>
                        <th class="column-page"><?php esc_html_e('Page', 'pic-pilot-meta'); ?></th>
                        <th class="column-image"><?php esc_html_e('Image', 'pic-pilot-meta'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'pic-pilot-meta'); ?></th>
                        <th class="column-context"><?php esc_html_e('Context', 'pic-pilot-meta'); ?></th>
                        <th class="column-priority"><?php esc_html_e('Priority', 'pic-pilot-meta'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'pic-pilot-meta'); ?></th>
                    </tr>
                </thead>
                <tbody id="issues-table-body">
                    <tr>
                        <td colspan="6" class="loading-row">
                            <?php esc_html_e('Loading issues...', 'pic-pilot-meta'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="issues-pagination" id="issues-pagination">
            <div class="pagination-info"></div>
            <div class="pagination-controls"></div>
        </div>
    </div>

    <!-- Priority System Explanation -->
    <div class="priority-explanation-section" id="priority-explanation">
        <h2><?php esc_html_e('Understanding Priority Levels', 'pic-pilot-meta'); ?></h2>

        <div class="explanation-content">
            <p><?php esc_html_e('The dashboard uses a priority scoring system (0-10) that categorizes accessibility issues to help you focus on the most important images first:', 'pic-pilot-meta'); ?></p>

            <div class="priority-definitions">
                <div class="priority-definition">
                    <div class="priority-header">
                        <span class="priority-badge priority-critical">Critical</span>
                        <span class="priority-range">(8-10 points)</span>
                    </div>
                    <p><?php esc_html_e('Missing both alt text and title attributes', 'pic-pilot-meta'); ?></p>
                </div>

                <div class="priority-definition">
                    <div class="priority-header">
                        <span class="priority-badge priority-high">High</span>
                        <span class="priority-range">(6-7 points)</span>
                    </div>
                    <p><?php esc_html_e('Important content images', 'pic-pilot-meta'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Featured images (homepage heroes, key visuals)', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('First/second images in content', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('Images missing alt text', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('Images on pages rather than posts', 'pic-pilot-meta'); ?></li>
                    </ul>
                </div>

                <div class="priority-definition">
                    <div class="priority-header">
                        <span class="priority-badge priority-medium">Medium</span>
                        <span class="priority-range">(4-5 points)</span>
                    </div>
                    <p><?php esc_html_e('Standard content images', 'pic-pilot-meta'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Supporting images later in articles', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('Decorative images', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('Secondary product shots', 'pic-pilot-meta'); ?></li>
                        <li><?php esc_html_e('Blog post inline images beyond the first two', 'pic-pilot-meta'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="scoring-factors">
                <h3><?php esc_html_e('How Priority Scores Are Calculated', 'pic-pilot-meta'); ?></h3>
                <div class="factors-grid">
                    <div class="factor">
                        <strong><?php esc_html_e('Base Score:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('5 points', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="factor">
                        <strong><?php esc_html_e('Featured Image:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('+3 points', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="factor">
                        <strong><?php esc_html_e('First/Second Image:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('+2 points', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="factor">
                        <strong><?php esc_html_e('Missing Both Attributes:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('+3 points', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="factor">
                        <strong><?php esc_html_e('Missing Alt Text:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('+2 points', 'pic-pilot-meta'); ?></span>
                    </div>
                    <div class="factor">
                        <strong><?php esc_html_e('Page vs Post:', 'pic-pilot-meta'); ?></strong>
                        <span><?php esc_html_e('+1 point', 'pic-pilot-meta'); ?></span>
                    </div>
                </div>
            </div>

            <div class="recommendation">
                <p><strong><?php esc_html_e('Recommendation:', 'pic-pilot-meta'); ?></strong> <?php esc_html_e('Start with Critical and High priority images to maximize accessibility impact with minimal effort.', 'pic-pilot-meta'); ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Scans -->
    <?php if (!empty($recent_scans)): ?>
        <div class="recent-scans">
            <h3><?php esc_html_e('Recent Scans', 'pic-pilot-meta'); ?></h3>
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'pic-pilot-meta'); ?></th>
                        <th><?php esc_html_e('Type', 'pic-pilot-meta'); ?></th>
                        <th><?php esc_html_e('Pages', 'pic-pilot-meta'); ?></th>
                        <th><?php esc_html_e('Issues', 'pic-pilot-meta'); ?></th>
                        <th><?php esc_html_e('Status', 'pic-pilot-meta'); ?></th>
                        <th><?php esc_html_e('Actions', 'pic-pilot-meta'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_scans as $scan): ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date('M j, Y g:i A', $scan['started_at'])); ?></td>
                            <td><?php echo esc_html(ucfirst($scan['scan_type'])); ?></td>
                            <td><?php echo esc_html($scan['pages_scanned']); ?></td>
                            <td><?php echo esc_html($scan['issues_found']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($scan['status']); ?>">
                                    <?php echo esc_html(ucfirst($scan['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small remove-scan" data-scan-id="<?php echo esc_attr($scan['scan_id']); ?>" title="<?php esc_html_e('Remove this scan', 'pic-pilot-meta'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Fix Issue Modal (Hidden by default) -->
<div class="pic-pilot-modal" id="fix-issue-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php esc_html_e('Fix Accessibility Issue', 'pic-pilot-meta'); ?></h3>
            <button type="button" class="modal-close" id="close-fix-modal">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <div class="fix-issue-content"></div>
        </div>
    </div>
</div>