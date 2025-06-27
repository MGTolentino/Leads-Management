/**
 * DEBUG SCRIPT FOR MONTH/YEAR FILTER ISSUES
 * This script helps debug why month/year filters are not working correctly
 */

// Debug configuration
const DEBUG_ENABLED = true;
const DEBUG_PREFIX = '[MONTH/YEAR DEBUG]';

function debugLog(...args) {
    if (DEBUG_ENABLED) {
        console.log(DEBUG_PREFIX, ...args);
    }
}

function debugError(...args) {
    if (DEBUG_ENABLED) {
        console.error(DEBUG_PREFIX, ...args);
    }
}

function debugWarn(...args) {
    if (DEBUG_ENABLED) {
        console.warn(DEBUG_PREFIX, ...args);
    }
}

// Enhanced jQuery ready function
jQuery(function($) {
    debugLog('Starting month/year filter debugging...');
    
    // Check if we're on the correct page
    if (!$('#anio_evento').length && !$('#mes_evento').length && !$('#mes_evento_basic').length) {
        debugWarn('Month/year filter elements not found on this page');
        return;
    }
    
    // 1. VERIFY ELEMENT EXISTENCE
    function verifyElements() {
        debugLog('=== ELEMENT VERIFICATION ===');
        
        const elements = {
            '#anio_evento': $('#anio_evento'),
            '#mes_evento': $('#mes_evento'),
            '#mes_evento_basic': $('#mes_evento_basic'),
            '#fecha_evento_inicio': $('#fecha_evento_inicio'),
            '#fecha_evento_fin': $('#fecha_evento_fin'),
            '#daterange_picker': $('#daterange_picker'),
            '#aplicar_filtros': $('#aplicar_filtros')
        };
        
        Object.keys(elements).forEach(selector => {
            const element = elements[selector];
            debugLog(`${selector}: ${element.length ? 'FOUND' : 'NOT FOUND'}`);
            if (element.length) {
                debugLog(`  - Value: "${element.val()}"`);
                debugLog(`  - Visible: ${element.is(':visible')}`);
            }
        });
    }
    
    // 2. MONITOR SELECTOR CHANGES
    function monitorSelectorChanges() {
        debugLog('=== SETTING UP CHANGE MONITORS ===');
        
        // Year selector
        $('#anio_evento').on('change', function() {
            const year = $(this).val();
            const month = $('#mes_evento_basic').val() || $('#mes_evento').val();
            
            debugLog('Year changed:', {
                year: year,
                month: month,
                timestamp: new Date().toISOString()
            });
            
            if (year) {
                debugProcessDateChange('year', year, month);
            }
        });
        
        // Month selectors (both basic and advanced)
        $('#mes_evento, #mes_evento_basic').on('change', function() {
            const month = $(this).val();
            const year = $('#anio_evento').val() || new Date().getFullYear();
            const selectorType = this.id;
            
            debugLog('Month changed:', {
                month: month,
                year: year,
                selectorType: selectorType,
                timestamp: new Date().toISOString()
            });
            
            if (month) {
                debugProcessDateChange('month', year, month);
            }
        });
    }
    
    // 3. PROCESS DATE CHANGES WITH DEBUGGING
    function debugProcessDateChange(triggerType, year, month) {
        debugLog(`=== PROCESSING ${triggerType.toUpperCase()} CHANGE ===`);
        debugLog('Input values:', { year, month });
        
        try {
            let startDate, endDate;
            
            if (month && year) {
                // Month range
                const lastDay = new Date(year, parseInt(month), 0).getDate();
                startDate = `${year}-${month.padStart(2, '0')}-01`;
                endDate = `${year}-${month.padStart(2, '0')}-${lastDay.toString().padStart(2, '0')}`;
                debugLog('Month range calculated:', { startDate, endDate, lastDay });
            } else if (year && !month) {
                // Year range
                startDate = `${year}-01-01`;
                endDate = `${year}-12-31`;
                debugLog('Year range calculated:', { startDate, endDate });
            } else {
                debugWarn('Invalid date combination:', { year, month });
                return;
            }
            
            // Set date inputs
            const startInput = $('#fecha_evento_inicio');
            const endInput = $('#fecha_evento_fin');
            
            debugLog('Before setting inputs:', {
                startInputValue: startInput.val(),
                endInputValue: endInput.val()
            });
            
            startInput.val(startDate);
            endInput.val(endDate);
            
            debugLog('After setting inputs:', {
                startInputValue: startInput.val(),
                endInputValue: endInput.val()
            });
            
            // Sync selectors if needed
            if (triggerType === 'month') {
                $('#mes_evento').val(month);
                $('#mes_evento_basic').val(month);
                if (!$('#anio_evento').val()) {
                    $('#anio_evento').val(year);
                }
            }
            
            // Check for daterangepicker
            checkDaterangepicker(startDate, endDate);
            
            // Update visual display
            updateDateDisplay();
            
        } catch (error) {
            debugError('Error processing date change:', error);
        }
    }
    
    // 4. CHECK DATERANGEPICKER INTEGRATION
    function checkDaterangepicker(startDate, endDate) {
        debugLog('=== DATERANGEPICKER CHECK ===');
        
        const daterangePicker = $('#daterange_picker');
        if (!daterangePicker.length) {
            debugWarn('Daterangepicker element not found');
            return;
        }
        
        debugLog('Daterangepicker element found');
        
        // Check if daterangepicker is initialized
        const drpInstance = daterangePicker.data('daterangepicker');
        if (!drpInstance) {
            debugWarn('Daterangepicker not initialized');
            return;
        }
        
        debugLog('Daterangepicker initialized');
        
        // Check moment.js availability
        if (typeof moment === 'undefined') {
            debugError('Moment.js not available');
            return;
        }
        
        debugLog('Moment.js available');
        
        try {
            // Create moment objects
            const startMoment = moment(startDate, 'YYYY-MM-DD');
            const endMoment = moment(endDate, 'YYYY-MM-DD');
            
            debugLog('Moment objects created:', {
                startValid: startMoment.isValid(),
                endValid: endMoment.isValid(),
                startFormatted: startMoment.format('DD/MM/YYYY'),
                endFormatted: endMoment.format('DD/MM/YYYY')
            });
            
            if (startMoment.isValid() && endMoment.isValid()) {
                // Set daterangepicker values
                drpInstance.setStartDate(startMoment);
                drpInstance.setEndDate(endMoment);
                
                const displayValue = startMoment.format('DD/MM/YYYY') + ' - ' + endMoment.format('DD/MM/YYYY');
                daterangePicker.val(displayValue);
                
                debugLog('Daterangepicker updated successfully:', {
                    displayValue: displayValue,
                    pickerValue: daterangePicker.val()
                });
            } else {
                debugError('Invalid moment objects');
            }
            
        } catch (error) {
            debugError('Error updating daterangepicker:', error);
        }
    }
    
    // 5. UPDATE VISUAL DISPLAY
    function updateDateDisplay() {
        debugLog('=== UPDATING VISUAL DISPLAY ===');
        
        try {
            const startDate = $('#fecha_evento_inicio').val();
            const endDate = $('#fecha_evento_fin').val();
            
            debugLog('Display inputs:', { startDate, endDate });
            
            let displayText = '';
            
            if (startDate && endDate) {
                if (startDate === endDate) {
                    displayText = formatDateDisplay(startDate);
                } else {
                    displayText = `Del ${formatDateDisplay(startDate)} al ${formatDateDisplay(endDate)}`;
                }
            } else if (startDate) {
                displayText = `Desde ${formatDateDisplay(startDate)}`;
            } else if (endDate) {
                displayText = `Hasta ${formatDateDisplay(endDate)}`;
            }
            
            debugLog('Display text generated:', displayText);
            
            const displayElement = $('#fecha_evento_display');
            if (displayElement.length) {
                displayElement.text(displayText);
                debugLog('Display element updated');
            } else {
                debugWarn('Display element not found');
            }
            
        } catch (error) {
            debugError('Error updating display:', error);
        }
    }
    
    // 6. FORMAT DATE FOR DISPLAY
    function formatDateDisplay(dateStr) {
        if (!dateStr) return '';
        
        const meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];
        
        // For YYYY-MM-DD format
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            const parts = dateStr.split('-');
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1;
            const day = parseInt(parts[2]);
            
            return `${day} de ${meses[month]} de ${year}`;
        }
        
        return dateStr;
    }
    
    // 7. MONITOR APPLY FILTERS FUNCTION
    function monitorApplyFilters() {
        debugLog('=== MONITORING APPLY FILTERS ===');
        
        const originalApplyFilters = $('#aplicar_filtros').data('events');
        debugLog('Original apply filters events:', originalApplyFilters);
        
        $('#aplicar_filtros').on('click.debug', function(e) {
            debugLog('Apply filters clicked');
            
            // Check current filter state
            const filterState = {
                fecha_evento_inicio: $('#fecha_evento_inicio').val(),
                fecha_evento_fin: $('#fecha_evento_fin').val(),
                anio_evento: $('#anio_evento').val(),
                mes_evento: $('#mes_evento').val(),
                mes_evento_basic: $('#mes_evento_basic').val(),
                daterange_picker: $('#daterange_picker').val()
            };
            
            debugLog('Filter state at apply:', filterState);
            
            // Check if dates are being sent to backend
            setTimeout(() => {
                debugLog('Checking AJAX request...');
                // This will be visible in network tab
            }, 100);
        });
    }
    
    // 8. CHECK BACKEND COMMUNICATION
    function monitorAjaxRequests() {
        debugLog('=== MONITORING AJAX REQUESTS ===');
        
        // Override jQuery AJAX to log filter requests
        const originalAjax = $.ajax;
        $.ajax = function(options) {
            if (options.data && options.data.action === 'filter_leads') {
                debugLog('AJAX filter request:', {
                    data: options.data,
                    timestamp: new Date().toISOString()
                });
            }
            
            return originalAjax.call(this, options);
        };
    }
    
    // 9. COMPREHENSIVE SYSTEM CHECK
    function performSystemCheck() {
        debugLog('=== COMPREHENSIVE SYSTEM CHECK ===');
        
        // Check jQuery
        debugLog('jQuery version:', $.fn.jquery);
        
        // Check jQuery UI
        if ($.ui) {
            debugLog('jQuery UI version:', $.ui.version);
            debugLog('Datepicker available:', typeof $.fn.datepicker !== 'undefined');
        } else {
            debugWarn('jQuery UI not loaded');
        }
        
        // Check moment.js
        if (typeof moment !== 'undefined') {
            debugLog('Moment.js version:', moment.version);
        } else {
            debugWarn('Moment.js not loaded');
        }
        
        // Check daterangepicker
        if (typeof $.fn.daterangepicker !== 'undefined') {
            debugLog('Daterangepicker available');
        } else {
            debugWarn('Daterangepicker not available');
        }
        
        // Check global variables
        debugLog('Current filters object:', window.currentFilters || 'Not found');
        
        // Check if multiple filter systems are running
        const scripts = $('script[src*="filter"], script[src*="pipeline"]');
        debugLog('Filter-related scripts loaded:', scripts.length);
        scripts.each(function() {
            debugLog('  -', this.src);
        });
    }
    
    // 10. MAIN EXECUTION
    function startDebugging() {
        debugLog('=== STARTING COMPREHENSIVE DEBUG ===');
        
        performSystemCheck();
        verifyElements();
        monitorSelectorChanges();
        monitorApplyFilters();
        monitorAjaxRequests();
        
        // Set up test functions
        window.debugMonthYear = {
            testYearChange: function(year) {
                debugLog('Testing year change:', year);
                $('#anio_evento').val(year).trigger('change');
            },
            
            testMonthChange: function(month) {
                debugLog('Testing month change:', month);
                $('#mes_evento_basic').val(month).trigger('change');
            },
            
            testFullDate: function(year, month) {
                debugLog('Testing full date:', year, month);
                $('#anio_evento').val(year);
                $('#mes_evento_basic').val(month).trigger('change');
            },
            
            getCurrentState: function() {
                return {
                    year: $('#anio_evento').val(),
                    month: $('#mes_evento_basic').val() || $('#mes_evento').val(),
                    startDate: $('#fecha_evento_inicio').val(),
                    endDate: $('#fecha_evento_fin').val(),
                    daterangePicker: $('#daterange_picker').val()
                };
            },
            
            forceApplyFilters: function() {
                debugLog('Forcing apply filters...');
                $('#aplicar_filtros').trigger('click');
            }
        };
        
        debugLog('Debug functions available at: window.debugMonthYear');
        debugLog('Example usage: debugMonthYear.testFullDate(2024, "06")');
    }
    
    // Start debugging after a short delay to ensure all scripts are loaded
    setTimeout(startDebugging, 1000);
});