/**
 * ===================================================================
 * PROJECT GANTT CHART JAVASCRIPT
 * ===================================================================
 * Page-specific JavaScript for project-gantt.php
 * Handles timeline generation, bar rendering, and interactions
 * ================================================================= */

$(document).ready(function() {
    // ===============================================================
    // INITIALIZATION
    // ===============================================================
    
    console.log('Gantt chart page initialized');
    
    // Check if data is available
    if (typeof window.projectGanttData === 'undefined') {
        console.error('Project Gantt data not found');
        showError('Failed to load project data');
        return;
    }
    
    const ganttData = window.projectGanttData;
    let currentZoom = 'month';
    let showActivities = true;
    let showMilestones = true;
    let showProgress = true;
    
    // Initialize Gantt chart
    initializeGanttChart();
    
    // Initialize controls
    initializeControls();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Set progress circle
    updateProgressCircle(ganttData.project);
    
    console.log('Gantt chart fully loaded');
    
    // ===============================================================
    // GANTT CHART INITIALIZATION
    // ===============================================================
    
    function initializeGanttChart() {
        try {
            // Generate timeline
            generateTimelineHeader();
            
            // Generate Gantt body
            generateGanttBody();
            
            // Position today line
            positionTodayLine();
            
            // Position milestones
            if (showMilestones) {
                positionMilestones();
            }
            
            console.log('Gantt chart rendered successfully');
        } catch (error) {
            console.error('Error initializing Gantt chart:', error);
            showError('Failed to render Gantt chart');
        }
    }
    
    // ===============================================================
    // TIMELINE GENERATION
    // ===============================================================
    
    function generateTimelineHeader() {
        const timelineStart = new Date(ganttData.timelineStart);
        const timelineEnd = new Date(ganttData.timelineEnd);
        const headerContainer = $('#timeline-header');
        headerContainer.empty();
        
        const currentDate = new Date();
        const currentMonth = currentDate.getMonth();
        const currentYear = currentDate.getFullYear();
        
        if (currentZoom === 'month') {
            // Generate monthly headers
            const current = new Date(timelineStart);
            current.setDate(1); // Start from first day of month
            
            while (current <= timelineEnd) {
                const monthName = current.toLocaleDateString('en-US', { month: 'short' });
                const year = current.getFullYear();
                const isCurrentMonth = current.getMonth() === currentMonth && current.getFullYear() === currentYear;
                
                const headerCell = $(`
                    <div class="timeline-header-cell ${isCurrentMonth ? 'current-month' : ''}" 
                         data-date="${current.toISOString().split('T')[0]}">
                        <div>${monthName}</div>
                        <div style="font-size: 10px; color: #6c757d;">${year}</div>
                    </div>
                `);
                
                headerContainer.append(headerCell);
                
                // Move to next month
                current.setMonth(current.getMonth() + 1);
            }
        } else if (currentZoom === 'quarter') {
            // Generate quarterly headers
            const current = new Date(timelineStart);
            current.setMonth(Math.floor(current.getMonth() / 3) * 3); // Round to quarter
            current.setDate(1);
            
            while (current <= timelineEnd) {
                const quarter = Math.floor(current.getMonth() / 3) + 1;
                const year = current.getFullYear();
                const isCurrentQuarter = Math.floor(currentMonth / 3) === Math.floor(current.getMonth() / 3) && 
                                        current.getFullYear() === currentYear;
                
                const headerCell = $(`
                    <div class="timeline-header-cell ${isCurrentQuarter ? 'current-month' : ''}" 
                         data-date="${current.toISOString().split('T')[0]}">
                        <div>Q${quarter}</div>
                        <div style="font-size: 10px; color: #6c757d;">${year}</div>
                    </div>
                `);
                
                headerContainer.append(headerCell);
                
                // Move to next quarter
                current.setMonth(current.getMonth() + 3);
            }
        }
    }
    
    // ===============================================================
    // GANTT BODY GENERATION
    // ===============================================================
    
    function generateGanttBody() {
        const bodyContainer = $('#gantt-body');
        bodyContainer.empty();
        
        // Generate work packages
        ganttData.workPackages.forEach(wp => {
            // Work Package row
            const wpRow = generateWorkPackageRow(wp);
            bodyContainer.append(wpRow);
            
            // Activities rows (if enabled)
            if (showActivities && ganttData.activities[wp.id]) {
                ganttData.activities[wp.id].forEach(activity => {
                    const activityRow = generateActivityRow(activity, wp);
                    bodyContainer.append(activityRow);
                });
            }
        });
    }
    
    function generateWorkPackageRow(wp) {
        const wpColor = ganttData.wpColors[wp.wp_number] || '#6c757d';
        const startDate = wp.start_date || ganttData.timelineStart;
        const endDate = wp.end_date || ganttData.timelineEnd;
        const progress = wp.avg_progress || 0;
        
        const { left, width } = calculateBarPosition(startDate, endDate);
        const statusClass = getStatusClass(wp.status, wp.end_date);
        
        const progressOverlay = showProgress ? `
            <div class="progress-overlay" style="width: ${progress}%;">
                ${Math.round(progress)}%
            </div>
        ` : '';
        
        return $(`
            <div class="gantt-row wp-row" data-wp-id="${wp.id}">
                <div class="gantt-label wp-label">
                    <strong>WP${wp.wp_number}: ${escapeHtml(wp.name)}</strong>
                    <div style="font-size: 11px; color: #6c757d; font-weight: normal;">
                        ${wp.activity_count || 0} activities
                    </div>
                </div>
                <div class="gantt-timeline">
                    <div class="gantt-bar wp-bar ${statusClass}" 
                         style="left: ${left}%; width: ${width}%; background-color: ${wpColor};"
                         data-toggle="tooltip"
                         data-wp-id="${wp.id}"
                         title="WP${wp.wp_number}: ${escapeHtml(wp.name)}&#10;Duration: ${formatDate(startDate)} - ${formatDate(endDate)}&#10;Progress: ${Math.round(progress)}%&#10;Budget: €${numberFormat(wp.budget || 0)}">
                        ${progressOverlay}
                    </div>
                </div>
            </div>
        `);
    }
    
    function generateActivityRow(activity, wp) {
        const wpColor = ganttData.wpColors[wp.wp_number] || '#6c757d';
        const startDate = activity.start_date;
        const endDate = activity.end_date;
        const progress = activity.progress_percent || 0;
        
        if (!startDate || !endDate) {
            return $(''); // Skip activities without dates
        }
        
        const { left, width } = calculateBarPosition(startDate, endDate);
        const statusClass = getStatusClass(activity.status, activity.end_date);
        
        const progressOverlay = showProgress ? `
            <div class="progress-overlay" style="width: ${progress}%;">
                ${Math.round(progress)}%
            </div>
        ` : '';
        
        return $(`
            <div class="gantt-row activity-row" data-activity-id="${activity.id}">
                <div class="gantt-label activity-label">
                    ${escapeHtml(activity.name)}
                    <div style="font-size: 10px; color: #6c757d;">
                        ${activity.responsible_partner_name || 'Unassigned'}
                    </div>
                </div>
                <div class="gantt-timeline">
                    <div class="gantt-bar activity-bar ${statusClass}" 
                         style="left: ${left}%; width: ${width}%; background-color: ${wpColor}; opacity: 0.7;"
                         data-toggle="tooltip"
                         data-activity-id="${activity.id}"
                         title="${escapeHtml(activity.name)}&#10;Duration: ${formatDate(startDate)} - ${formatDate(endDate)}&#10;Status: ${activity.status}&#10;Progress: ${Math.round(progress)}%">
                        ${progressOverlay}
                    </div>
                </div>
            </div>
        `);
    }
    
    // ===============================================================
    // MILESTONE POSITIONING
    // ===============================================================
    
    function positionMilestones() {
        // Remove existing milestones
        $('.milestone-marker').remove();
        
        ganttData.milestones.forEach(milestone => {
            const milestoneDate = milestone.end_date;
            if (!milestoneDate) return;
            
            const position = calculateDatePosition(milestoneDate);
            const statusClass = getMilestoneStatusClass(milestone);
            
            const milestoneMarker = $(`
                <div class="milestone-marker ${statusClass}" 
                     style="left: ${position}%;"
                     data-toggle="tooltip"
                     data-milestone-id="${milestone.id}"
                     title="${escapeHtml(milestone.name)}&#10;Due: ${formatDate(milestoneDate)}&#10;Status: ${milestone.status}&#10;${milestone.wp_name ? 'WP: ' + milestone.wp_name : ''}">
                </div>
            `);
            
            $('#gantt-body').append(milestoneMarker);
        });
    }
    
    // ===============================================================
    // TODAY LINE POSITIONING
    // ===============================================================
    
    function positionTodayLine() {
        const today = new Date().toISOString().split('T')[0];
        const position = calculateDatePosition(today);
        
        const todayLine = $('#today-line');
        if (position >= 0 && position <= 100) {
            todayLine.css('left', `${position}%`).show();
        } else {
            todayLine.hide();
        }
    }
    
    // ===============================================================
    // CALCULATION HELPERS
    // ===============================================================
    
    function calculateBarPosition(startDate, endDate) {
        const timelineStart = new Date(ganttData.timelineStart);
        const timelineEnd = new Date(ganttData.timelineEnd);
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        const totalDuration = timelineEnd.getTime() - timelineStart.getTime();
        const startOffset = start.getTime() - timelineStart.getTime();
        const duration = end.getTime() - start.getTime();
        
        const left = Math.max(0, (startOffset / totalDuration) * 100);
        const width = Math.min(100 - left, (duration / totalDuration) * 100);
        
        return { left, width };
    }
    
    function calculateDatePosition(date) {
        const timelineStart = new Date(ganttData.timelineStart);
        const timelineEnd = new Date(ganttData.timelineEnd);
        const targetDate = new Date(date);
        
        const totalDuration = timelineEnd.getTime() - timelineStart.getTime();
        const offset = targetDate.getTime() - timelineStart.getTime();
        
        return (offset / totalDuration) * 100;
    }
    
    function getStatusClass(status, dueDate) {
        if (status === 'completed') return 'completed';
        if (status === 'in_progress') return 'in-progress';
        if (dueDate && new Date(dueDate) < new Date() && status !== 'completed') return 'overdue';
        return 'not-started';
    }
    
    function getMilestoneStatusClass(milestone) {
        if (milestone.status === 'completed') return 'completed';
        if (milestone.end_date && new Date(milestone.end_date) < new Date() && milestone.status !== 'completed') return 'overdue';
        return 'upcoming';
    }
    
    // ===============================================================
    // CONTROLS INITIALIZATION
    // ===============================================================
    
    function initializeControls() {
        // Zoom controls
        $('.zoom-btn').on('click', function() {
            $('.zoom-btn').removeClass('active');
            $(this).addClass('active');
            currentZoom = $(this).data('zoom');
            
            // Regenerate timeline with new zoom
            generateTimelineHeader();
            console.log('Zoom changed to:', currentZoom);
        });
        
        // Show/Hide controls
        $('#show-activities').on('change', function() {
            showActivities = $(this).is(':checked');
            generateGanttBody();
            if (showMilestones) positionMilestones();
            console.log('Activities visibility:', showActivities);
        });
        
        $('#show-milestones').on('change', function() {
            showMilestones = $(this).is(':checked');
            if (showMilestones) {
                positionMilestones();
            } else {
                $('.milestone-marker').remove();
            }
            console.log('Milestones visibility:', showMilestones);
        });
        
        $('#show-progress').on('change', function() {
            showProgress = $(this).is(':checked');
            generateGanttBody();
            if (showMilestones) positionMilestones();
            console.log('Progress visibility:', showProgress);
        });
        
        // Today button
        $('#today-btn').on('click', function() {
            scrollToToday();
        });
        
        // Gantt bar click handlers
        $(document).on('click', '.gantt-bar', function(e) {
            e.stopPropagation();
            
            const wpId = $(this).data('wp-id');
            const activityId = $(this).data('activity-id');
            
            if (activityId) {
                showActivityModal(activityId);
            } else if (wpId) {
                showWorkPackageModal(wpId);
            }
        });
        
        // Milestone click handlers
        $(document).on('click', '.milestone-marker', function(e) {
            e.stopPropagation();
            const milestoneId = $(this).data('milestone-id');
            showMilestoneModal(milestoneId);
        });
    }
    
    // ===============================================================
    // SCROLL FUNCTIONS
    // ===============================================================
    
    function scrollToToday() {
        const today = new Date().toISOString().split('T')[0];
        const position = calculateDatePosition(today);
        
        if (position >= 0 && position <= 100) {
            const ganttContainer = $('.gantt-container');
            const containerWidth = ganttContainer.width();
            const scrollLeft = (position / 100) * ganttContainer[0].scrollWidth - containerWidth / 2;
            
            ganttContainer.animate({
                scrollLeft: Math.max(0, scrollLeft)
            }, 500);
            
            // Highlight today line briefly
            $('#today-line').addClass('highlight');
            setTimeout(() => {
                $('#today-line').removeClass('highlight');
            }, 2000);
        } else {
            showNotification('Today is outside the project timeline', 'info');
        }
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
                        <div class="modal-header" style="background-color: ${ganttData.wpColors[wp.wp_number]}; color: white;">
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
                                    <div class="progress mb-2">
                                        <div class="progress-bar" style="width: ${wp.avg_progress || 0}%; background-color: ${ganttData.wpColors[wp.wp_number]};">
                                            ${Math.round(wp.avg_progress || 0)}%
                                        </div>
                                    </div>
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
                            <p><strong>Progress:</strong> ${Math.round(activity.progress_percent || 0)}%</p>
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
    
    function showMilestoneModal(milestoneId) {
        const milestone = ganttData.milestones.find(m => m.id == milestoneId);
        if (!milestone) return;
        
        const modalHtml = `
            <div class="modal fade" id="milestoneModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="nc-icon nc-diamond"></i>
                                ${escapeHtml(milestone.name)}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Description:</strong><br>${escapeHtml(milestone.description || 'No description available')}</p>
                            ${milestone.wp_name ? `<p><strong>Work Package:</strong> WP${milestone.wp_number}: ${escapeHtml(milestone.wp_name)}</p>` : ''}
                            <p><strong>Due Date:</strong> ${formatDate(milestone.end_date)}</p>
                            <p><strong>Status:</strong> <span class="badge badge-${getStatusBadgeColor(milestone.status)}">${milestone.status}</span></p>
                            ${milestone.completion_date ? `<p><strong>Completed:</strong> ${formatDate(milestone.completion_date)}</p>` : ''}
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
    // TOOLTIP INITIALIZATION
    // ===============================================================
    
    function initializeTooltips() {
        // Initialize tooltips with custom settings
        $(document).on('mouseenter', '[data-toggle="tooltip"]', function() {
            $(this).tooltip({
                container: 'body',
                html: true,
                placement: 'top',
                template: '<div class="tooltip gantt-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>',
                delay: { show: 200, hide: 100 }
            }).tooltip('show');
        });
        
        $(document).on('mouseleave', '[data-toggle="tooltip"]', function() {
            $(this).tooltip('hide');
        });
    }
    
    // ===============================================================
    // PROGRESS CIRCLE UPDATE
    // ===============================================================
    
    function updateProgressCircle(project) {
        // Calculate overall project progress
        let totalActivities = 0;
        let completedActivities = 0;
        
        for (let wpId in ganttData.activities) {
            ganttData.activities[wpId].forEach(activity => {
                totalActivities++;
                if (activity.status === 'completed') {
                    completedActivities++;
                }
            });
        }
        
        const overallProgress = totalActivities > 0 ? Math.round((completedActivities / totalActivities) * 100) : 0;
        
        // Update CSS custom property for progress circle
        $('.progress-circle-large').css('--progress', overallProgress);
        $('.progress-percentage').text(overallProgress + '%');
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
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function numberFormat(number) {
        return new Intl.NumberFormat('en-US').format(number);
    }
    
    function getStatusBadgeColor(status) {
        switch (status) {
            case 'completed': return 'success';
            case 'in_progress': return 'primary';
            case 'not_started': return 'secondary';
            case 'overdue': return 'danger';
            default: return 'secondary';
        }
    }
    
    function showNotification(message, type = 'info') {
        const alertClass = `alert-${type}`;
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show notification-popup" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut();
        }, 5000);
    }
    
    function showError(message) {
        showNotification(message, 'danger');
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
            case 84: // T key - Go to today
                e.preventDefault();
                scrollToToday();
                break;
            case 65: // A key - Toggle activities
                e.preventDefault();
                $('#show-activities').click();
                break;
            case 77: // M key - Toggle milestones
                e.preventDefault();
                $('#show-milestones').click();
                break;
        }
    });
    
    console.log('Gantt chart JavaScript initialization complete');
});