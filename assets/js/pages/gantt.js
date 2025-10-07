/**
 * ===================================================================
 * SIMPLE GANTT TABLE JAVASCRIPT
 * ===================================================================
 * Simplified JavaScript for tabular Gantt chart
 * Handles tooltips, modals, and basic interactions
 * ================================================================= */

$(document).ready(function() {
    // ===============================================================
    // INITIALIZATION
    // ===============================================================
    
    console.log('Simple Gantt table initialized');
    
    // Check if data is available
    if (typeof window.projectGanttData === 'undefined') {
        console.error('Project Gantt data not found');
        return;
    }
    
    const ganttData = window.projectGanttData;
    
    // Initialize interactions
    initializeTooltips();
    initializeClickHandlers();
    initializeTableEnhancements();
    initializeGanttScrollers(); // Add this call
    
    console.log('Gantt table fully loaded');
    
    // ===============================================================
    // GANTT SCROLLERS
    // ===============================================================

    function initializeGanttScrollers() {
        const container = $('.gantt-table-container');
        const scrollLeftBtn = $('#gantt-scroll-left');
        const scrollRightBtn = $('#gantt-scroll-right');
        const scrollAmount = 400; // Amount to scroll on each click

        if (container.length === 0) {
            return;
        }

        function updateScrollButtons() {
            const scrollLeft = container.scrollLeft();
            const scrollWidth = container[0].scrollWidth;
            const containerWidth = container[0].clientWidth;

            // Show/hide left button
            if (scrollLeft > 0) {
                scrollLeftBtn.addClass('visible');
            } else {
                scrollLeftBtn.removeClass('visible');
            }

            // Show/hide right button
            if (scrollLeft < scrollWidth - containerWidth - 1) {
                scrollRightBtn.addClass('visible');
            } else {
                scrollRightBtn.removeClass('visible');
            }
        }

        // Initial check
        updateScrollButtons();

        // Update on scroll
        container.on('scroll', updateScrollButtons);

        // Handle clicks
        scrollLeftBtn.on('click', function() {
            container.animate({ scrollLeft: '-=' + scrollAmount }, 300);
        });

        scrollRightBtn.on('click', function() {
            container.animate({ scrollLeft: '+=' + scrollAmount }, 300);
        });
        
        console.log('Gantt scrollers initialized');
    }

    
    // ===============================================================
    // TOOLTIP INITIALIZATION
    // ===============================================================
    
    function initializeTooltips() {
        // Initialize tooltips for gantt bars and milestones
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body',
            html: true,
            placement: 'top',
            delay: { show: 200, hide: 100 },
            template: '<div class="tooltip gantt-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
        });
        
        console.log('Tooltips initialized');
    }
    
    // ===============================================================
    // CLICK HANDLERS
    // ===============================================================
    
    function initializeClickHandlers() {
        // Work package row click
        $('.gantt-wp-row').on('click', function(e) {
            // Don't handle click if milestone was clicked
            if ($(e.target).hasClass('milestone-marker')) {
                return;
            }
            e.preventDefault();
            const wpId = $(this).data('wp-id');
            if (wpId) {
                showWorkPackageModal(wpId);
            }
        });
        
        // Activity row click
        $('.gantt-activity-row').on('click', function(e) {
            // Don't handle click if milestone was clicked
            if ($(e.target).hasClass('milestone-marker')) {
                return;
            }
            e.preventDefault();
            const activityId = $(this).data('activity-id');
            if (activityId) {
                showActivityModal(activityId);
            }
        });
        
        // Gantt bar click
        $('.gantt-bar').on('click', function(e) {
            e.stopPropagation();
            const wpId = $(this).data('wp-id');
            const activityId = $(this).data('activity-id');
            
            if (activityId) {
                showActivityModal(activityId);
            } else if (wpId) {
                showWorkPackageModal(wpId);
            }
        });
        
        // Milestone click - FIXED: Use data attributes from PHP
        $(document).on('click', '.milestone-marker', function(e) {
            e.stopPropagation();
            
            const $marker = $(this);
            
            // Get milestone data from data attributes (set by PHP)
            const milestoneData = {
                id: $marker.data('milestone-id'),
                name: $marker.data('milestone-name'),
                date: $marker.data('milestone-date'),
                status: $marker.data('milestone-status'),
                description: $marker.data('milestone-description'),
                deliverable: $marker.data('milestone-deliverable'),
                wpName: $marker.data('wp-name')
            };
            
            showMilestoneModal(milestoneData);
        });
        
        console.log('Click handlers initialized');
    }
    
    // ===============================================================
    // TABLE ENHANCEMENTS
    // ===============================================================
    
    function initializeTableEnhancements() {
        // Add hover effects to table rows
        $('.gantt-table tbody tr').hover(
            function() {
                $(this).addClass('table-row-hover');
            },
            function() {
                $(this).removeClass('table-row-hover');
            }
        );
        
        // Smooth scrolling for table container
        $('.gantt-table-container').on('scroll', function() {
            // Add shadow effect when scrolled
            if ($(this).scrollLeft() > 0) {
                $(this).addClass('scrolled');
            } else {
                $(this).removeClass('scrolled');
            }
        });
        
        console.log('Table enhancements initialized');
    }
    
    // ===============================================================
    // MODAL FUNCTIONS
    // ===============================================================
    
    function showWorkPackageModal(wpId) {
        const wp = ganttData.workPackages.find(w => w.id == wpId);
        if (!wp) return;
        
        const activities = ganttData.activities[wpId] || [];
        const completedActivities = activities.filter(a => a.status === 'completed').length;
        
        const modalHtml = `
            <div class="modal fade" id="wpModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header" style="background-color: ${ganttData.wpColors[wp.wp_number] || '#6c757d'}; color: white;">
                            <h5 class="modal-title">
                                <i class="nc-icon nc-briefcase-24"></i>
                                WP${wp.wp_number}: ${escapeHtml(wp.name)}
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Work Package Details</h6>
                                    <p><strong>Description:</strong><br>${escapeHtml(wp.description || 'No description available')}</p>
                                    <p><strong>Lead Partner:</strong> ${escapeHtml(wp.lead_partner_name || 'Not assigned')}</p>
                                    <p><strong>Duration:</strong> ${formatDate(wp.start_date)} - ${formatDate(wp.end_date)}</p>
                                    <p><strong>Budget:</strong> €${numberFormat(wp.budget || 0)}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Progress Overview</h6>
                                    <p><strong>Activities:</strong> ${completedActivities}/${activities.length} completed</p>
                                    <p><strong>Status:</strong> <span class="badge badge-${getStatusBadgeColor(wp.status)}">${wp.status}</span></p>
                                </div>
                            </div>
                            
                            ${activities.length > 0 ? `
                                <hr>
                                <h6>Activities</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Responsible</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${activities.map(activity => `
                                                <tr>
                                                    <td>${escapeHtml(activity.name)}</td>
                                                    <td>${escapeHtml(activity.responsible_partner_name || 'Unassigned')}</td>
                                                    <td>${formatDate(activity.start_date)} - ${formatDate(activity.end_date)}</td>
                                                    <td><span class="badge badge-${getStatusBadgeColor(activity.status)}">${activity.status}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <a href="work-package-detail.php?id=${wp.id}" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#wpModal').remove();
        $('body').append(modalHtml);
        $('#wpModal').modal('show');
    }
    
    function showActivityModal(activityId) {
        // Find activity in all work packages
        let activity = null;
        let workPackage = null;
        
        for (let wpId in ganttData.activities) {
            const found = ganttData.activities[wpId].find(a => a.id == activityId);
            if (found) {
                activity = found;
                workPackage = ganttData.workPackages.find(wp => wp.id == wpId);
                break;
            }
        }
        
        if (!activity || !workPackage) return;
        
        const modalHtml = `
            <div class="modal fade" id="activityModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="nc-icon nc-paper"></i>
                                ${escapeHtml(activity.name)}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Work Package:</strong> WP${workPackage.wp_number}: ${escapeHtml(workPackage.name)}</p>
                            <p><strong>Description:</strong><br>${escapeHtml(activity.description || 'No description available')}</p>
                            <p><strong>Responsible Partner:</strong> ${escapeHtml(activity.responsible_partner_name || 'Not assigned')}</p>
                            <p><strong>Duration:</strong> ${formatDate(activity.start_date)} - ${formatDate(activity.end_date)}</p>
                            ${activity.end_date ? `<p><strong>Due Date:</strong> ${formatDate(activity.end_date)}</p>` : ''}
                            <p><strong>Status:</strong> <span class="badge badge-${getStatusBadgeColor(activity.status)}">${activity.status}</span></p>
                            ${activity.budget ? `<p><strong>Budget:</strong> €${numberFormat(activity.budget)}</p>` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <a href="activity-detail.php?id=${activity.id}" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#activityModal').remove();
        $('body').append(modalHtml);
        $('#activityModal').modal('show');
    }
    
    // FIXED: Updated milestone modal to use data from PHP data attributes
    function showMilestoneModal(milestoneData) {
        const modalHtml = `
            <div class="modal fade" id="milestoneModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="nc-icon nc-diamond"></i>
                                ${escapeHtml(milestoneData.name)}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Work Package:</strong>
                                    <p>${escapeHtml(milestoneData.wpName || 'N/A')}</p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Due Date:</strong>
                                    <p>${formatDate(milestoneData.date) || 'No due date'}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <p><span id="milestone-status" class="badge badge-${getStatusBadgeColor(milestoneData.status)}">${milestoneData.status || 'Unknown'}</span></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Deliverable:</strong>
                                    <p>${escapeHtml(milestoneData.deliverable || 'No deliverable specified')}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <strong>Description:</strong>
                                    <p>${escapeHtml(milestoneData.description || 'No description available')}</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#milestoneModal').remove();
        $('body').append(modalHtml);
        $('#milestoneModal').modal('show');
    }
    
    // ===============================================================
    // UTILITY FUNCTIONS
    // ===============================================================
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        } catch (error) {
            return dateString;
        }
    }
    
    function numberFormat(number) {
        if (!number) return '0';
        return new Intl.NumberFormat('en-US').format(number);
    }
    
    function getStatusBadgeColor(status) {
        if (!status) return 'secondary';
        
        switch (status.toLowerCase()) {
            case 'completed': return 'success';
            case 'in_progress': return 'primary';
            case 'not_started': return 'secondary';
            case 'overdue': return 'danger';
            case 'pending': return 'warning';
            default: return 'secondary';
        }
    }
    
    // ===============================================================
    // KEYBOARD SHORTCUTS
    // ===============================================================
    
    $(document).on('keydown', function(e) {
        // Only handle shortcuts when not in input fields
        if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea') {
            return;
        }
        
        switch(e.which) {
            case 27: // ESC key - close modals
                $('.modal').modal('hide');
                break;
        }
    });
    
    console.log('Simple Gantt table JavaScript initialization complete');
});