<?php
/**
 * Pipeline View Unified - Template with modern design system
 * 
 * @package LTB_Leads_Management
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ltb-leads-wrapper" data-theme="light">
    <div class="ltb-container">
        
        <!-- Page Header -->
        <div class="ltb-page-header" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 class="ltb-page-title" style="margin: 0; font-size: 1.875rem;">Gestión de Leads</h1>
                <button id="add_lead_btn" class="ltb-btn ltb-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 5V10M10 10V15M10 10H15M10 10H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Agregar Lead
                </button>
            </div>
        </div>
        
        <!-- Filters Section - Compact -->
        <div class="ltb-filters" style="padding: 1rem; margin-bottom: 1.5rem;">
            <div class="ltb-filters-header">
                <div class="ltb-filters-title">
                    <svg class="ltb-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 10H15M2.5 5H17.5M7.5 15H12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>Filtros</span>
                </div>
                <button id="toggle-dark-mode" class="ltb-btn ltb-btn-ghost ltb-btn-sm" style="margin-left: auto;">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 3V1M10 19V17M17 10H19M1 10H3M15.364 15.364L16.778 16.778M3.222 3.222L4.636 4.636M15.364 4.636L16.778 3.222M3.222 16.778L4.636 15.364M14 10C14 12.209 12.209 14 10 14C7.791 14 6 12.209 6 10C6 7.791 7.791 6 10 6C12.209 6 14 7.791 14 10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="ltb-btn-text">Tema</span>
                </button>
            </div>
            
            <div class="ltb-filters-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem;">
                <!-- Search -->
                <div class="ltb-filter-group" style="grid-column: span 2;">
                    <input type="text" id="search_filter" class="ltb-filter-input" placeholder="🔍 Buscar por nombre, email, teléfono..." style="width: 100%;">
                </div>
                
                <!-- Date Range -->
                <div class="ltb-filter-group">
                    <label class="ltb-filter-label" for="date_range">Rango de fechas</label>
                    <input type="text" id="date_range" class="ltb-filter-input" placeholder="Seleccionar fechas">
                </div>
                
                <!-- Event Type -->
                <div class="ltb-filter-group">
                    <label class="ltb-filter-label" for="event_type_filter">Tipo de evento</label>
                    <select id="event_type_filter" class="ltb-filter-select">
                        <option value="">Todos los tipos</option>
                    </select>
                </div>
                
                <!-- Ejecutivo -->
                <div class="ltb-filter-group">
                    <label class="ltb-filter-label" for="ejecutivo_filter">Ejecutivo</label>
                    <select id="ejecutivo_filter" class="ltb-filter-select">
                        <option value="">Todos</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div class="ltb-filter-group">
                    <label class="ltb-filter-label" for="status_filter">Estado</label>
                    <select id="status_filter" class="ltb-filter-select">
                        <option value="">Todos los estados</option>
                        <option value="nuevo">Nuevo</option>
                        <option value="contactado">Contactado</option>
                        <option value="visitado">Visitado</option>
                        <option value="cotizado">Cotizado</option>
                        <option value="contratado">Contratado</option>
                        <option value="perdido">Perdido</option>
                    </select>
                </div>
            </div>
            
            <div class="ltb-filter-actions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button id="apply_filters" class="ltb-btn ltb-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 17L5 13M9 17L13 13M9 17V10M18 7H2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Aplicar filtros
                </button>
                <button id="clear_filters" class="ltb-btn ltb-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 6L14 14M6 14L14 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Limpiar
                </button>
                <button id="export_leads" class="ltb-btn ltb-btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 16V14C4 13.448 4.448 13 5 13H15C15.552 13 16 13.448 16 14V16M8 7L10 5M10 5L12 7M10 5V13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Exportar
                </button>
            </div>
        </div>
        
        <!-- Pipeline Section -->
        <div class="ltb-pipeline">
            <div class="ltb-pipeline-header">
                <div class="ltb-pipeline-title">
                    <h2>Pipeline de Ventas</h2>
                </div>
                <div class="ltb-pipeline-stats">
                    <div class="ltb-stat-item">
                        <span class="ltb-stat-value" id="stat-total">0</span>
                        <span class="ltb-stat-label">Total Leads</span>
                    </div>
                    <div class="ltb-stat-item">
                        <span class="ltb-stat-value" id="stat-conversion">0%</span>
                        <span class="ltb-stat-label">Conversión</span>
                    </div>
                    <div class="ltb-stat-item">
                        <span class="ltb-stat-value" id="stat-value">$0</span>
                        <span class="ltb-stat-label">Valor Total</span>
                    </div>
                </div>
            </div>
            
            <!-- Loading State -->
            <div id="pipeline-loader" class="ltb-loading ltb-hidden">
                <div class="ltb-spinner"></div>
            </div>
            
            <!-- Pipeline Board -->
            <div id="pipeline-container" class="ltb-pipeline-board">
                <!-- Pipeline columns will be generated dynamically -->
                
                <!-- Example column structure -->
                <div class="ltb-pipeline-column ltb-column-nuevo" data-status="nuevo">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Nuevo</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body">
                        <!-- Lead cards will be inserted here -->
                    </div>
                </div>
                
                <div class="ltb-pipeline-column ltb-column-contactado" data-status="contactado">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Contactado</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body"></div>
                </div>
                
                <div class="ltb-pipeline-column ltb-column-visitado" data-status="visitado">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Visitado</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body"></div>
                </div>
                
                <div class="ltb-pipeline-column ltb-column-cotizado" data-status="cotizado">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Cotizado</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body"></div>
                </div>
                
                <div class="ltb-pipeline-column ltb-column-contratado" data-status="contratado">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Contratado</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body"></div>
                </div>
                
                <div class="ltb-pipeline-column ltb-column-perdido" data-status="perdido">
                    <div class="ltb-column-header">
                        <div class="ltb-column-title">
                            <span>Perdido</span>
                            <span class="ltb-column-badge">0</span>
                        </div>
                    </div>
                    <div class="ltb-column-body"></div>
                </div>
            </div>
            
            <!-- Messages container -->
            <div id="pipeline-messages"></div>
        </div>
        
    </div>
</div>

<!-- Lead Card Template (hidden, for JS cloning) -->
<template id="lead-card-template">
    <div class="ltb-lead-card" draggable="true">
        <div class="ltb-lead-header">
            <span class="ltb-lead-name"></span>
            <span class="ltb-lead-id"></span>
        </div>
        <div class="ltb-lead-details">
            <div class="ltb-lead-detail">
                <svg class="ltb-lead-detail-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="ltb-lead-email"></span>
            </div>
            <div class="ltb-lead-detail">
                <svg class="ltb-lead-detail-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span class="ltb-lead-phone"></span>
            </div>
            <div class="ltb-lead-detail">
                <svg class="ltb-lead-detail-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="ltb-lead-event"></span>
            </div>
        </div>
        <div class="ltb-lead-tags">
            <!-- Tags will be inserted here -->
        </div>
        <div class="ltb-lead-actions">
            <a href="#" class="ltb-lead-action ltb-lead-view">Ver</a>
            <button class="ltb-lead-action ltb-lead-edit">Editar</button>
            <button class="ltb-lead-action ltb-lead-delete">Eliminar</button>
        </div>
    </div>
</template>

<!-- Toast Template -->
<template id="toast-template">
    <div class="ltb-toast">
        <svg class="ltb-toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="ltb-toast-content">
            <div class="ltb-toast-title"></div>
            <div class="ltb-toast-message"></div>
        </div>
        <button class="ltb-toast-close">×</button>
    </div>
</template>

<script>
// Dark mode toggle
document.getElementById('toggle-dark-mode')?.addEventListener('click', () => {
    const wrapper = document.querySelector('.ltb-leads-wrapper');
    const currentTheme = wrapper.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    wrapper.setAttribute('data-theme', newTheme);
    localStorage.setItem('ltb-theme', newTheme);
});

// Load saved theme
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('ltb-theme') || 'light';
    document.querySelector('.ltb-leads-wrapper')?.setAttribute('data-theme', savedTheme);
});
</script>