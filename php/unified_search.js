/**
 * Unified Search Library for Admin Interface
 * One library to rule them all - handles all search contexts consistently
 */

class UnifiedSearchLibrary {
    constructor(config) {
        this.config = {
            searchBoxId: config.searchBoxId,
            resultsContainerId: config.resultsContainerId,
            mode: config.mode || 'display', // 'display', 'selection', 'management'
            showVendor: config.showVendor !== false,
            showPrice: config.showPrice !== false, 
            showCategory: config.showCategory !== false,
            showUnit: config.showUnit !== false,
            showActions: config.showActions || false,
            allowPagination: config.allowPagination !== false,
            debounceDelay: config.debounceDelay || 300,
            minSearchLength: config.minSearchLength || 2,
            onItemSelect: config.onItemSelect,
            onItemEdit: config.onItemEdit,
            onItemDelete: config.onItemDelete,
            vendorFilterId: config.vendorFilterId,
            categoryFilterId: config.categoryFilterId,
            statsContainerId: config.statsContainerId,
            countDisplayId: config.countDisplayId,
            vendorBadgesId: config.vendorBadgesId,
            tableClass: config.tableClass || 'data-table',
            loadingMessage: config.loadingMessage || 'Searching...',
            noResultsMessage: config.noResultsMessage || 'No products found',
            emptyStateMessage: config.emptyStateMessage || 'Enter search terms to find products',
            ...config
        };
        
        this.currentPage = 1;
        this.totalPages = 1;
        this.lastQuery = '';
        this.debounceTimer = null;
        
        this.init();
    }
    
    init() {
        this.searchBox = document.getElementById(this.config.searchBoxId);
        this.resultsContainer = document.getElementById(this.config.resultsContainerId);
        this.vendorFilter = this.config.vendorFilterId ? document.getElementById(this.config.vendorFilterId) : null;
        this.categoryFilter = this.config.categoryFilterId ? document.getElementById(this.config.categoryFilterId) : null;
        this.statsContainer = this.config.statsContainerId ? document.getElementById(this.config.statsContainerId) : null;
        this.countDisplay = this.config.countDisplayId ? document.getElementById(this.config.countDisplayId) : null;
        this.vendorBadges = this.config.vendorBadgesId ? document.getElementById(this.config.vendorBadgesId) : null;
        
        if (!this.searchBox || !this.resultsContainer) {
            console.error('UnifiedSearchLibrary: Required elements not found');
            return;
        }
        
        this.attachEventListeners();
        this.showEmptyState();
        
        // Load vendors if filter is available
        if (this.vendorFilter) {
            this.loadVendors();
        }
    }
    
    attachEventListeners() {
        // Search input with debouncing
        this.searchBox.addEventListener('input', () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.performSearch();
            }, this.config.debounceDelay);
        });
        
        // Enter key for immediate search
        this.searchBox.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                clearTimeout(this.debounceTimer);
                this.performSearch();
            }
        });
        
        // Vendor filter changes
        if (this.vendorFilter) {
            this.vendorFilter.addEventListener('change', () => {
                this.performSearch();
            });
        }
        
        if (this.categoryFilter) {
            this.categoryFilter.addEventListener('change', () => {
                this.performSearch();
            });
        }
    }
    
    async performSearch(page = 1) {
        const query = this.searchBox.value.trim();
        
        if (query.length === 0) {
            this.showEmptyState();
            return;
        }
        
        if (query.length < this.config.minSearchLength) {
            this.showMessage(`Type at least ${this.config.minSearchLength} characters to search`);
            return;
        }
        
        this.lastQuery = query;
        this.currentPage = page;
        this.showLoading();
        
        try {
            const params = new URLSearchParams({
                action: 'search_products',
                q: query,
                page: page
            });
            
            if (this.vendorFilter && this.vendorFilter.value) {
                params.append('dc', this.vendorFilter.value);
            }
            
            if (this.categoryFilter && this.categoryFilter.value) {
                params.append('category', this.categoryFilter.value);
            }
            
            const response = await fetch(`admin_api.php?${params}`);
            const data = await response.json();
            
            if (data.error) {
                this.showError(data.error);
                return;
            }
            
            if (!data.results || data.results.length === 0) {
                this.showNoResults(query);
                return;
            }
            
            this.totalPages = data.totalPages || 1;
            this.displayResults(data, query);
            
        } catch (error) {
            console.error('Search error:', error);
            this.showError(`Search failed: ${error.message}`);
        }
    }
    
    displayResults(data, query) {
        // Update search stats if container exists
        if (this.statsContainer) {
            this.updateSearchStats(data, query);
        }
        
        let html = '';
        
        // Result summary (only if no dedicated stats container)
        if (!this.statsContainer && this.config.mode !== 'selection') {
            html += `<div class="search-summary" style="padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ddd; margin-bottom: 10px;">
                <strong>Found ${data.total} products</strong> for "${query}"
                ${data.totalPages > 1 ? ` (page ${data.page} of ${data.totalPages})` : ''}
            </div>`;
        }
        
        // Results table
        html += this.buildResultsTable(data.results);
        
        // Pagination
        if (this.config.allowPagination && data.totalPages > 1) {
            html += this.buildPagination(data.page, data.totalPages);
        }
        
        this.resultsContainer.innerHTML = html;
    }
    
    updateSearchStats(data, query) {
        if (this.countDisplay) {
            this.countDisplay.textContent = `${data.total} products found${query ? ` for "${query}"` : ''}`;
        }
        
        // Show vendor badges
        if (this.vendorBadges && data.results) {
            const vendors = [...new Set(data.results.map(p => p.dc).filter(v => v))];
            let badgesHtml = '';
            vendors.forEach(vendor => {
                const className = vendor.toLowerCase().replace(/[^a-z]/g, '');
                badgesHtml += `<span class="vendor-badge ${className}">${vendor}</span>`;
            });
            this.vendorBadges.innerHTML = badgesHtml;
        }
        
        // Show stats container
        if (this.statsContainer) {
            this.statsContainer.style.display = data.total > 0 ? 'flex' : 'none';
        }
    }
    
    buildResultsTable(results) {
        let html = `<table class="${this.config.tableClass}">`;
        
        // Header
        html += '<thead><tr>';
        if (this.config.mode === 'selection') {
            html += '<th>Select</th>';
        }
        html += '<th>Item Code</th>';
        html += '<th>Description</th>';
        if (this.config.showPrice) html += '<th>Price</th>';
        if (this.config.showUnit) html += '<th>Unit</th>';
        if (this.config.showVendor) html += '<th>Vendor</th>';
        if (this.config.showCategory) html += '<th>Category</th>';
        if (this.config.showActions) html += '<th>Actions</th>';
        html += '</tr></thead>';
        
        // Body
        html += '<tbody>';
        results.forEach(product => {
            html += '<tr>';
            
            // Selection column
            if (this.config.mode === 'selection') {
                html += `<td><button class="btn btn-sm btn-primary" onclick="window.searchInstance_${this.config.searchBoxId}.selectItem('${product.item_code}', ${JSON.stringify(product).replace(/"/g, '&quot;')})">Select</button></td>`;
            }
            
            html += `<td><strong>${product.item_code}</strong></td>`;
            html += `<td>${product.description}</td>`;
            if (this.config.showPrice) html += `<td>$${product.price}</td>`;
            if (this.config.showUnit) html += `<td>${product.unit || 'EA'}</td>`;
            if (this.config.showVendor) html += `<td>${product.dc}</td>`;
            if (this.config.showCategory) html += `<td>${product.category}</td>`;
            
            // Actions column
            if (this.config.showActions) {
                html += `<td>
                    <button class="btn btn-sm btn-primary" onclick="window.searchInstance_${this.config.searchBoxId}.editItem('${product.item_code}', ${JSON.stringify(product).replace(/"/g, '&quot;')})">✏️ Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="window.searchInstance_${this.config.searchBoxId}.deleteItem('${product.item_code}', ${JSON.stringify(product).replace(/"/g, '&quot;')})">🗑️ Delete</button>
                </td>`;
            }
            
            html += '</tr>';
        });
        html += '</tbody></table>';
        
        return html;
    }
    
    buildPagination(currentPage, totalPages) {
        let html = '<div class="pagination" style="padding: 15px; text-align: center; background: #f8f9fa; margin-top: 10px; border-radius: 5px;">';
        html += `<p>Page ${currentPage} of ${totalPages}</p>`;
        html += '<div style="margin-top: 10px;">';
        
        if (currentPage > 1) {
            html += `<button class="btn btn-secondary" onclick="window.searchInstance_${this.config.searchBoxId}.goToPage(${currentPage - 1})" style="margin-right: 10px;">← Previous</button>`;
        }
        
        if (currentPage < totalPages) {
            html += `<button class="btn btn-secondary" onclick="window.searchInstance_${this.config.searchBoxId}.goToPage(${currentPage + 1})">Next →</button>`;
        }
        
        html += '</div></div>';
        return html;
    }
    
    goToPage(page) {
        this.performSearch(page);
    }
    
    selectItem(itemCode, product) {
        if (this.config.onItemSelect) {
            this.config.onItemSelect(product);
        }
    }
    
    editItem(itemCode, product) {
        if (this.config.onItemEdit) {
            this.config.onItemEdit(product);
        }
    }
    
    deleteItem(itemCode, product) {
        if (this.config.onItemDelete) {
            this.config.onItemDelete(product);
        }
    }
    
    showLoading() {
        this.resultsContainer.innerHTML = `<div class="loading" style="text-align: center; padding: 40px;">
            <div class="spinner" style="display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px;"></div>
            ${this.config.loadingMessage}
        </div>`;
    }
    
    showMessage(message, type = 'info') {
        const color = type === 'error' ? '#dc3545' : '#6c757d';
        this.resultsContainer.innerHTML = `<p style="text-align: center; color: ${color}; padding: 20px;">${message}</p>`;
    }
    
    showError(message) {
        this.showMessage(message, 'error');
    }
    
    showNoResults(query) {
        this.resultsContainer.innerHTML = `<div style="text-align: center; padding: 40px; color: #7f8c8d;">
            <h4>No Results Found</h4>
            <p>No products found matching "${query}"</p>
            <small>Try different search terms or check your spelling</small>
        </div>`;
    }
    
    showEmptyState() {
        this.resultsContainer.innerHTML = `<div style="text-align: center; padding: 40px; color: #7f8c8d;">
            ${this.config.emptyStateMessage}
        </div>`;
    }
    
    async loadVendors() {
        try {
            const response = await fetch('admin_api.php?action=get_vendors');
            const data = await response.json();
            
            if (data && data.vendors) {
                this.vendorFilter.innerHTML = '<option value="">All Vendors</option>';
                data.vendors.forEach(vendor => {
                    this.vendorFilter.innerHTML += `<option value="${vendor}">${vendor}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading vendors:', error);
        }
    }
    
    // Public methods
    clear() {
        this.searchBox.value = '';
        this.showEmptyState();
    }
    
    search(query) {
        this.searchBox.value = query;
        this.performSearch();
    }
    
    // Make instance globally accessible
    makeGlobal() {
        window[`searchInstance_${this.config.searchBoxId}`] = this;
    }
}

// Auto-initialize search instances when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // This will be called by individual configurations
});