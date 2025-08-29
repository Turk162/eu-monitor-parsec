   
    // ===============================================================
    // PROJECT FILTER FUNCTIONS
    // ===============================================================
    
    function initializeProjectFilters() {
        // Auto-submit form when project selection changes
        $('#project_select').on('change', function() {
            // Add a small delay to allow multiple selections
            clearTimeout(window.projectFilterTimeout);
            window.projectFilterTimeout = setTimeout(function() {
                $('#projectFilterForm').submit();
            }, 500);
        });
        
        // Prevent form submission on individual option clicks
        $('#project_select').on('mousedown', function(e) {
            e.preventDefault();
            const option = e.target;
            if (option.tagName === 'OPTION') {
                option.selected = !option.selected;
                $(this).trigger('change');
                return false;
            }
        });
        
        // Style the multi-select
        styleMultiSelect();
    }
    
    function selectAllProjects() {
        $('#project_select option').prop('selected', true);
        $('#projectFilterForm').submit();
    }
    
    function clearAllProjects() {
        $('#project_select option').prop('selected', false);
        $('#projectFilterForm').submit();
    }
    
    function styleMultiSelect() {
        const $select = $('#project_select');
        
        // Add custom styling classes based on selection count
        const selectedCount = $select.find('option:selected').length;
        const totalCount = $select.find('option').length;
        
        $select.removeClass('select-all select-none select-partial');
        
        if (selectedCount === 0) {
            $select.addClass('select-none');
        } else if (selectedCount === totalCount) {
            $select.addClass('select-all');
        } else {
            $select.addClass('select-partial');
        }
        
        // Update the info text
$('.form-text.text-muted').html(`
    ${selectedCount} of ${totalCount} projects selected
`);    }
    
    function updateProjectFilterInfo(selectedCount, totalCount) {
        $('.selected-projects-info small').html(`
            <i class="nc-icon nc-briefcase-24"></i>
            Showing ${selectedCount} of ${totalCount} projects
        `);
    }
    
    // Global functions for buttons (called from PHP)
    window.selectAllProjects = selectAllProjects;
    window.clearAllProjects = clearAllProjects;/**
 * ===================================================================
 * CALENDAR PAGE JAVASCRIPT
 * ===================================================================
 * Page-specific JavaScript for calendar.php
 * Handles calendar interactions, event tooltips, and navigation
 * ================================================================= */

$(document).ready(function() {
    // ===============================================================
    // INITIALIZATION
    // ===============================================================
    
    console.log('Calendar page initialized');
    
    // Initialize tooltips for event dots
    initializeTooltips();
    
    // Initialize calendar interactions
    initializeCalendarEvents();
    
    // Initialize project filters
    initializeProjectFilters();
    
    // Initialize responsive behavior
    initializeResponsive();
    
    // Initialize keyboard navigation
    initializeKeyboardNavigation();
    
    // ===============================================================
    // TOOLTIP INITIALIZATION
    // ===============================================================
    
    function initializeTooltips() {
        // Initialize tooltips for event dots
        $('.event-dot').tooltip({
            placement: 'top',
            container: 'body',
            delay: { "show": 200, "hide": 100 },
            template: '<div class="tooltip event-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
        });
        
        // Initialize tooltips for calendar days with multiple events
        $('.calendar-day.has-events').each(function() {
            const $day = $(this);
            const dayNumber = $day.find('.day-number').text();
            const events = $day.find('.event-dot');
            
            if (events.length > 3) {
                $day.attr('data-toggle', 'tooltip');
                $day.attr('data-placement', 'top');
                $day.attr('title', `${events.length} events on ${dayNumber}`);
                $day.tooltip({
                    container: 'body',
                    delay: { "show": 300, "hide": 100 }
                });
            }
        });
    }
    
    // ===============================================================
    // CALENDAR EVENT HANDLERS
    // ===============================================================
    
    function initializeCalendarEvents() {
        // Calendar day click handler
        $('.calendar-day.current-month').on('click', function(e) {
            e.preventDefault();
            
            const $day = $(this);
            const dayNumber = $day.find('.day-number').text();
            const events = $day.find('.event-dot');
            
            if (events.length > 0) {
                showDayEventsModal(dayNumber, $day);
            }
        });
        
        // Event dot click handler (prevent day click when clicking on event)
        $('.event-dot').on('click', function(e) {
            e.stopPropagation();
            
            const eventTitle = $(this).attr('data-original-title');
            const eventType = $(this).hasClass('activity') ? 'activity' : 
                             $(this).hasClass('milestone') ? 'milestone' : 'project';
            
            showEventDetailsModal(eventTitle, eventType);
        });
        
        // Upcoming event click handler
        $('.upcoming-event').on('click', function() {
            const eventTitle = $(this).find('.event-title').text();
            const eventType = $(this).find('.badge').text().toLowerCase();
            
            showEventDetailsModal(eventTitle, eventType);
        });
        
        // Calendar navigation keyboard support
        $(document).on('keydown', function(e) {
            if (e.target.tagName.toLowerCase() !== 'input' && e.target.tagName.toLowerCase() !== 'textarea') {
                switch(e.which) {
                    case 37: // Left arrow - previous month
                        e.preventDefault();
                        $('.btn-group a:first').click();
                        break;
                    case 39: // Right arrow - next month
                        e.preventDefault();
                        $('.btn-group a:last').click();
                        break;
                }
            }
        });
    }
    
    // ===============================================================
    // MODAL FUNCTIONS
    // ===============================================================
    
    function showDayEventsModal(dayNumber, $dayElement) {
        const events = [];
        
        $dayElement.find('.event-dot').each(function() {
            const title = $(this).attr('data-original-title');
            const type = $(this).hasClass('activity') ? 'activity' : 
                        $(this).hasClass('milestone') ? 'milestone' : 'project';
            const status = $(this).hasClass('completed') ? 'completed' :
                          $(this).hasClass('in_progress') ? 'in_progress' :
                          $(this).hasClass('overdue') ? 'overdue' : 'not_started';
            
            events.push({ title, type, status });
        });
        
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="dayEventsModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="nc-icon nc-calendar-60"></i>
                                Events for Day ${dayNumber}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            ${events.map(event => `
                                <div class="event-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <span class="event-type-indicator ${event.type} ${event.status}"></span>
                                        <div class="ml-3">
                                            <h6 class="mb-1">${event.title}</h6>
                                            <small class="text-muted">
                                                <span class="badge badge-${getEventBadgeColor(event.type)}">${event.type}</span>
                                                <span class="badge badge-${getStatusBadgeColor(event.status)} ml-1">${event.status.replace('_', ' ')}</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal and add new one
        $('#dayEventsModal').remove();
        $('body').append(modalHtml);
        $('#dayEventsModal').modal('show');
    }
    
    function showEventDetailsModal(eventTitle, eventType) {
        // Create a simple alert for now - in a real implementation,
        // this would show detailed event information
        showNotification(`Event: ${eventTitle}`, `Type: ${eventType}`, 'info');
    }
    
    // ===============================================================
    // RESPONSIVE BEHAVIOR
    // ===============================================================
    
    function initializeResponsive() {
        // Adjust calendar on window resize
        $(window).on('resize', debounce(function() {
            adjustCalendarLayout();
        }, 250));
        
        // Initial adjustment
        adjustCalendarLayout();
    }
    
    function adjustCalendarLayout() {
        const windowWidth = $(window).width();
        
        if (windowWidth < 768) {
            // Mobile adjustments
            $('.calendar-legend').addClass('mobile-legend');
            $('.upcoming-event').addClass('mobile-event');
        } else {
            // Desktop adjustments
            $('.calendar-legend').removeClass('mobile-legend');
            $('.upcoming-event').removeClass('mobile-event');
        }
    }
    
    // ===============================================================
    // KEYBOARD NAVIGATION
    // ===============================================================
    
    function initializeKeyboardNavigation() {
        let focusedDay = null;
        
        // Make calendar days focusable
        $('.calendar-day.current-month').attr('tabindex', '0');
        
        // Handle keyboard navigation within calendar
        $('.calendar-day').on('keydown', function(e) {
            const $currentDay = $(this);
            let $targetDay = null;
            
            switch(e.which) {
                case 37: // Left
                    $targetDay = $currentDay.prev('.calendar-day.current-month');
                    break;
                case 39: // Right
                    $targetDay = $currentDay.next('.calendar-day.current-month');
                    break;
                case 38: // Up
                    $targetDay = $currentDay.parent().prev().find('.calendar-day').eq($currentDay.index());
                    break;
                case 40: // Down
                    $targetDay = $currentDay.parent().next().find('.calendar-day').eq($currentDay.index());
                    break;
                case 13: // Enter
                case 32: // Space
                    e.preventDefault();
                    $currentDay.click();
                    return;
            }
            
            if ($targetDay && $targetDay.length && $targetDay.hasClass('current-month')) {
                e.preventDefault();
                $targetDay.focus();
            }
        });
    }
    
    // ===============================================================
    // UTILITY FUNCTIONS
    // ===============================================================
    
    function getEventBadgeColor(eventType) {
        switch(eventType) {
            case 'activity':
                return 'primary';
            case 'milestone':
                return 'warning';
            case 'project':
                return 'danger';
            default:
                return 'secondary';
        }
    }
    
    function getStatusBadgeColor(status) {
        switch(status) {
            case 'completed':
                return 'success';
            case 'in_progress':
                return 'info';
            case 'overdue':
                return 'danger';
            default:
                return 'secondary';
        }
    }
    
    function showNotification(title, message, type = 'info') {
        // Simple notification function
        const alertClass = type === 'info' ? 'alert-info' : 
                          type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 'alert-danger';
        
        const notification = `
            <div class="alert ${alertClass} alert-dismissible fade show notification-popup" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <strong>${title}</strong><br>
                ${message}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        `;
        
        $('body').append(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('.notification-popup').fadeOut();
        }, 5000);
    }
    
    // Debounce function for performance
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    // ===============================================================
    // CALENDAR ANIMATION EFFECTS
    // ===============================================================
    
    // Add subtle animations to calendar interactions
    $('.calendar-day').hover(
        function() {
            $(this).addClass('day-hover');
        },
        function() {
            $(this).removeClass('day-hover');
        }
    );
    
    // Animate upcoming events on scroll
    function animateUpcomingEvents() {
        $('.upcoming-event').each(function(index) {
            $(this).delay(index * 100).fadeIn();
        });
    }
    
    // Trigger animations
    animateUpcomingEvents();
    
    // ===============================================================
    // CALENDAR DATA REFRESH (for real-time updates)
    // ===============================================================
    
    // Optional: Auto-refresh calendar data every 5 minutes
    // setInterval(function() {
    //     refreshCalendarData();
    // }, 300000); // 5 minutes
    
    function refreshCalendarData() {
        // In a real implementation, this would fetch updated calendar data
        // via AJAX and update the calendar without full page reload
        console.log('Refreshing calendar data...');
    }
    
    // ===============================================================
    // ACCESSIBILITY IMPROVEMENTS
    // ===============================================================
    
    // Improve screen reader support
    $('.calendar-day.has-events').attr('aria-label', function() {
        const dayNumber = $(this).find('.day-number').text();
        const eventCount = $(this).find('.event-dot').length;
        return `Day ${dayNumber}, ${eventCount} event${eventCount !== 1 ? 's' : ''}`;
    });
    
    // Add ARIA labels to navigation buttons
    $('.btn-group a:first').attr('aria-label', 'Previous month');
    $('.btn-group a:last').attr('aria-label', 'Next month');
    
    console.log('Calendar page fully loaded and initialized');
});