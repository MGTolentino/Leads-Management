# Month/Year Filter Debug Report

## Issues Identified

### 1. **Conflicting Filter Systems**
- **Problem**: Two separate JavaScript implementations running simultaneously
  - `filters.js` - Uses jQuery UI datepickers with month/year selectors
  - `pipeline-simple.js` - Uses daterangepicker with moment.js
- **Impact**: Events triggering on both systems, causing conflicts and inconsistent behavior

### 2. **Timing Issues**
- **Problem**: Month/year selectors triggering before daterangepicker initialization
- **Symptoms**: `daterangepicker.setStartDate()` calls failing silently
- **Root Cause**: Insufficient initialization delays and missing error handling

### 3. **Missing Synchronization**
- **Problem**: Values not properly synced between different UI elements:
  - Hidden date inputs (`#fecha_evento_inicio`, `#fecha_evento_fin`)
  - Month/year selectors (`#anio_evento`, `#mes_evento_basic`)
  - DateRangePicker display
- **Impact**: User selections in one UI element not reflected in others

### 4. **Backend Compatibility**
- **Problem**: Date formats not consistently handled between frontend and backend
- **Symptoms**: Filters appearing to work in UI but not actually filtering data

### 5. **Error Handling**
- **Problem**: Silent failures when daterangepicker methods fail
- **Impact**: No user feedback when month/year selections don't work

## Fixes Implemented

### 1. **Enhanced filters.js**

#### Added Synchronization Prevention
```javascript
let isUpdatingDateSelectors = false;
```
- Prevents infinite loops between selector events

#### Improved Year Selector Handler
```javascript
$('#anio_evento').on('change', function() {
    if (isUpdatingDateSelectors) return;
    
    const anio = $(this).val();
    const mes = $('#mes_evento_basic').val() || $('#mes_evento').val();
    
    console.log('[FILTER DEBUG] Year changed:', { anio, mes });
    
    if (anio) {
        isUpdatingDateSelectors = true;
        
        try {
            // ... date calculation logic ...
            
            // Actualizar daterangepicker si existe
            updateDaterangepicker();
            
            // Actualizar visualización
            actualizarVisualizacionFechaEvento();
            
        } catch (error) {
            console.error('[FILTER DEBUG] Error in year change:', error);
        } finally {
            isUpdatingDateSelectors = false;
        }
    }
});
```

#### New DateRangePicker Update Function
```javascript
function updateDaterangepicker() {
    const startDate = $('#fecha_evento_inicio').val();
    const endDate = $('#fecha_evento_fin').val();
    
    console.log('[FILTER DEBUG] Updating daterangepicker:', { startDate, endDate });
    
    // Check if daterangepicker exists and is initialized
    const drpInstance = daterangePicker.data('daterangepicker');
    if (!drpInstance) {
        // Retry after delay if not initialized
        setTimeout(function() {
            updateDaterangepicker();
        }, 500);
        return;
    }
    
    // Update with moment.js validation
    if (startDate && endDate && typeof moment !== 'undefined') {
        try {
            const startMoment = moment(startDate, 'YYYY-MM-DD');
            const endMoment = moment(endDate, 'YYYY-MM-DD');
            
            if (startMoment.isValid() && endMoment.isValid()) {
                drpInstance.setStartDate(startMoment);
                drpInstance.setEndDate(endMoment);
                // Update display value
                const displayValue = startMoment.format('DD/MM/YYYY') + ' - ' + endMoment.format('DD/MM/YYYY');
                daterangePicker.val(displayValue);
            }
        } catch (error) {
            console.error('[FILTER DEBUG] Error updating daterangepicker:', error);
        }
    }
}
```

### 2. **Enhanced pipeline-simple.js**

#### Unified Selector Handling
```javascript
$('#month_selector, #year_selector, #anio_evento, #mes_evento, #mes_evento_basic').on('change.pipeline', function() {
    console.log('[PIPELINE DEBUG] Date selector changed:', this.id, this.value);
    
    // Determine which selectors to use
    let year, month;
    
    // For pipeline simple
    if (this.id === 'year_selector' || this.id === 'month_selector') {
        year = $('#year_selector').val();
        month = $('#month_selector').val();
    }
    // For main filters
    else if (this.id === 'anio_evento' || this.id.includes('mes_evento')) {
        year = $('#anio_evento').val();
        month = $('#mes_evento_basic').val() || $('#mes_evento').val();
        
        // Sync with pipeline selectors if they exist
        if ($('#year_selector').length && $('#month_selector').length) {
            $('#year_selector').val(year);
            $('#month_selector').val(month);
        }
    }
    
    // ... rest of processing logic ...
});
```

#### Improved DateRangePicker Integration
```javascript
// DateRangePicker events with two-way sync
$('#daterange_picker').on('apply.daterangepicker', function(ev, picker) {
    // Update hidden fields for sync with main filters
    if (picker.startDate && picker.endDate) {
        $('#fecha_evento_inicio').val(picker.startDate.format('YYYY-MM-DD'));
        $('#fecha_evento_fin').val(picker.endDate.format('YYYY-MM-DD'));
        
        // Update selectors if same month
        if (picker.startDate.isSame(picker.endDate, 'month')) {
            const year = picker.startDate.format('YYYY');
            const month = picker.startDate.format('MM');
            
            $('#anio_evento').val(year);
            $('#mes_evento, #mes_evento_basic').val(month);
            $('#year_selector').val(year);
            $('#month_selector').val(month);
        }
    }
    
    // Trigger main filter system update if available
    if (typeof window.actualizarVisualizacionFechaEvento === 'function') {
        window.actualizarVisualizacionFechaEvento();
    }
    
    applyFilters();
});
```

### 3. **Added Global Function Exposure**
```javascript
// In filters.js
window.actualizarVisualizacionFechaEvento = actualizarVisualizacionFechaEvento;
window.currentFilters = currentFilters;
window.updateTable = updateTable;

// In pipeline-simple.js
window.updateDaterangepicker = function() {
    const startDate = $('#fecha_evento_inicio').val();
    const endDate = $('#fecha_evento_fin').val();
    
    if (startDate && endDate) {
        const startMoment = moment(startDate, 'YYYY-MM-DD');
        const endMoment = moment(endDate, 'YYYY-MM-DD');
        
        if (startMoment.isValid() && endMoment.isValid()) {
            const drpInstance = $('#daterange_picker').data('daterangepicker');
            if (drpInstance) {
                drpInstance.setStartDate(startMoment);
                drpInstance.setEndDate(endMoment);
                $('#daterange_picker').val(startMoment.format('DD/MM/YYYY') + ' - ' + endMoment.format('DD/MM/YYYY'));
            }
        }
    }
};
```

### 4. **Enhanced Debug Logging**

#### Comprehensive Logging System
- **Frontend**: Added detailed console logging for all filter operations
- **AJAX Requests**: Enhanced request/response logging
- **Error Handling**: Better error reporting with context
- **State Tracking**: Log current filter state at key points

#### Example Debug Output
```javascript
console.log('[FILTER DEBUG] Year changed:', { anio, mes });
console.log('[FILTER DEBUG] Month range set:', { startDate, endDate });
console.log('[FILTER DEBUG] Sending AJAX request with filters:', currentFilters);
console.log('[PIPELINE DEBUG] DateRangePicker updated successfully');
```

## Testing Tools Created

### 1. **Debug Script** (`debug-month-year-filters.js`)
- Comprehensive debugging functions
- Real-time monitoring of selector changes
- AJAX request interception
- System health checks
- Test functions accessible via `window.debugMonthYear`

### 2. **Test Page** (`month-year-filter-test.html`)
- Standalone test environment
- Visual debugging interface
- Test cases for common scenarios
- Real-time state monitoring
- Independent of WordPress environment

## Verification Steps

### 1. **Check Console Logs**
```javascript
// Open browser console and look for:
[FILTER DEBUG] Year changed: {anio: "2024", mes: "06"}
[FILTER DEBUG] Month range set: {startDate: "2024-06-01", endDate: "2024-06-30"}
[FILTER DEBUG] DateRangePicker updated successfully
[FILTER DEBUG] Sending AJAX request with filters: {fecha_evento_inicio: "2024-06-01", fecha_evento_fin: "2024-06-30"}
```

### 2. **Test Month/Year Selection**
1. Select a year (e.g., 2024)
2. Select a month (e.g., June)
3. Verify hidden date fields are populated
4. Verify daterangepicker shows correct range
5. Click "Apply Filters"
6. Check AJAX request contains correct dates

### 3. **Test DateRangePicker → Selectors**
1. Use daterangepicker to select a month range
2. Verify year/month selectors update automatically
3. Verify hidden date fields are correct

### 4. **Test Backend Processing**
1. Check browser Network tab for AJAX requests
2. Verify request contains `fecha_evento_inicio` and `fecha_evento_fin`
3. Verify backend response contains filtered results
4. Check that applied_filters in response shows date filters

## Common Issues & Solutions

### Issue: "DateRangePicker not initialized"
**Solution**: Increase initialization delay or check for existence before calling methods

### Issue: "Month/year selection not applying filters"
**Solution**: Verify AJAX request is being sent with correct date parameters

### Issue: "Selector values not syncing"
**Solution**: Check for infinite loop prevention and ensure both systems are updating hidden fields

### Issue: "Backend not filtering by dates"
**Solution**: Verify date format is YYYY-MM-DD and check backend query processing

## Files Modified

1. **assets/js/filters.js** - Enhanced month/year handling and daterangepicker sync
2. **assets/js/pipeline-simple.js** - Added cross-system synchronization
3. **debug-month-year-filters.js** - New debugging tool
4. **month-year-filter-test.html** - New test environment
5. **MONTH_YEAR_FILTER_DEBUG_REPORT.md** - This documentation

## Next Steps

1. **Test in production environment** with real data
2. **Monitor console logs** for any remaining issues
3. **Gather user feedback** on filter behavior
4. **Consider consolidating** filter systems in future refactor
5. **Add unit tests** for filter synchronization functions

## Performance Considerations

- Added timeout delays may slightly slow filter response
- Debug logging should be disabled in production
- Consider lazy loading of daterangepicker for better initial page load
- Monitor memory usage with event handlers on multiple selectors