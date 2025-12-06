// universal_search_ui.js
// Modular advanced search UI for products table
// Usage: call UniversalSearchUI.init({
//   container: HTMLElement,
//   apiUrl: string,
//   table: string (optional, default 'products')
// })

const UniversalSearchUI = (() => {
  let config = {};
  let currentPage = 1;
  let totalPages = 1;
  let isSearching = false;
  let debounceTimer;

  function init(opts) {
    config = opts;
    const container = opts.container;
    container.innerHTML = `
      <div class="search-section">
        <div class="search-container">
          <input type="text" id="searchBox" placeholder="Search by item code, description, or category...">
          <span class="search-icon">🔍</span>
        </div>
        <div class="controls-row">
          <div class="filter-controls">
            <label for="dcFilter">Filter by Vendor:</label>
            <select id="dcFilter"><option value="">All Vendors</option></select>
            <label for="sortBy">Sort by:</label>
            <select id="sortBy">
              <option value="description">Description</option>
              <option value="item_code">Item Code</option>
              <option value="price">Price</option>
              <option value="vendor">Vendor</option>
              <option value="category">Category</option>
            </select>
            <select id="sortOrder">
              <option value="ASC">A-Z / Low-High</option>
              <option value="DESC">Z-A / High-Low</option>
            </select>
          </div>
        </div>
        <div class="search-stats">
          <div id="resultCount">Ready to search products</div>
          <div class="vendor-badges"></div>
        </div>
      </div>
      <div id="loading" style="display:none;"><div class="spinner"></div>Searching products...</div>
      <div id="results"></div>
      <div id="pagination" style="display:none;">
        <div class="pagination-controls">
          <button id="prevPage" class="pagination-btn">← Previous</button>
          <span id="pageInfo">Page 1 of 1</span>
          <button id="nextPage" class="pagination-btn">Next →</button>
        </div>
      </div>
    `;
    bindEvents();
    loadVendors();
    showEmptyState();
  }

  function bindEvents() {
    const searchBox = document.getElementById('searchBox');
    const dcFilter = document.getElementById('dcFilter');
    const sortBy = document.getElementById('sortBy');
    const sortOrder = document.getElementById('sortOrder');
    searchBox.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      const query = this.value.trim();
      if (query.length === 0) { showEmptyState(); return; }
      if (query.length < 2) {
        document.getElementById('resultCount').textContent = 'Type at least 2 characters to search';
        return;
      }
      document.getElementById('resultCount').textContent = 'Searching...';
      debounceTimer = setTimeout(() => {
        const currentQuery = searchBox.value.trim();
        if (currentQuery.length >= 2) {
          currentPage = 1;
          performSearch(currentQuery, 1);
        }
      }, 300);
    });
    searchBox.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length >= 2) performSearch(query);
      }
    });
    dcFilter.addEventListener('change', function() {
      const query = searchBox.value.trim();
      if (query.length >= 2) performSearch(query);
    });
    sortBy.addEventListener('change', function() {
      const query = searchBox.value.trim();
      if (query.length >= 2) performSearch(query);
    });
    sortOrder.addEventListener('change', function() {
      const query = searchBox.value.trim();
      if (query.length >= 2) performSearch(query);
    });
  }

  async function loadVendors() {
    // Try to load vendors from API, fallback to static
    try {
      const response = await fetch(config.apiUrl + '?action=get_vendors');
      const data = await response.json();
      if (data && data.vendors && data.vendors.length > 0) {
        const dcFilter = document.getElementById('dcFilter');
        dcFilter.innerHTML = '<option value="">All Vendors</option>';
        data.vendors.forEach(vendor => {
          dcFilter.innerHTML += `<option value="${vendor}">${vendor}</option>`;
        });
      }
    } catch {}
  }

  async function performSearch(query, page = 1) {
    if (isSearching) return;
    isSearching = true;
    showLoading();
    try {
      const sortBy = document.getElementById('sortBy').value;
      const sortOrder = document.getElementById('sortOrder').value;
      const dcFilter = document.getElementById('dcFilter').value;
      const params = new URLSearchParams({
        table: config.table || 'products',
        action: 'list',
        q: query,
        page: page,
        sort_by: sortBy,
        sort_order: sortOrder
      });
      if (dcFilter) params.append('dc', dcFilter);
      const url = config.apiUrl + '?' + params.toString();
      const response = await fetch(url);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      hideLoading();
      showResults(data, query);
    } catch (error) {
      hideLoading();
      showError(error);
    } finally {
      isSearching = false;
    }
  }

  function showLoading() {
    document.getElementById('loading').style.display = 'block';
    document.getElementById('results').style.display = 'none';
  }
  function hideLoading() {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('results').style.display = 'block';
  }
  function showEmptyState() {
    document.getElementById('results').innerHTML = `<div class="empty-state"><h3>Welcome to Vendor Product Search!</h3><p>Start typing to search across all vendor catalogs</p></div>`;
  }
  function showResults(data, query) {
    if (!data.rows || data.rows.length === 0) {
      showNoResults(query);
      return;
    }
    // TODO: Pagination support if backend provides total/pages
    let html = '<table class="results-table"><thead><tr>';
    html += '<th>Item Code</th><th>Description</th><th>Price</th><th>Unit</th><th>Vendor</th><th>Category</th>';
    html += '</tr></thead><tbody>';
    data.rows.forEach(product => {
      let vendorClass = '';
      let vendorDisplay = product.vendor;
      if (product.vendor === 'Thumanns') vendorClass = 'thumanns';
      else if (product.vendor === 'M&V Provisions') { vendorClass = 'mandv'; vendorDisplay = 'M&V'; }
      else if (product.vendor === 'Westside Foods') { vendorClass = 'westside'; vendorDisplay = 'Westside'; }
      else if (product.vendor === 'Driscoll Foods') { vendorClass = 'driscoll'; vendorDisplay = 'Driscoll'; }
      // Use 'unit' (which is now just uom_id/raw value), fallback to 'EA'
      let unitDisplay = product.unit || 'EA';
      html += '<tr>';
      html += `<td><span class="item-code">${product.item_code}</span></td>`;
      html += `<td class="desc-ai" style="cursor:pointer;color:#0074d9;text-decoration:underline;" data-desc="${encodeURIComponent(product.description)}">${product.description}</td>`;
        // Add modal for AI info if not present
        if (!document.getElementById('aiModal')) {
          const modalHtml = `
            <div id="aiModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);">
              <div style="background:#fff;max-width:500px;margin:10vh auto;padding:20px;position:relative;border-radius:8px;box-shadow:0 2px 8px #0003;">
                <span id="aiModalClose" style="position:absolute;top:8px;right:16px;cursor:pointer;font-size:22px;">&times;</span>
                <h3 id="aiModalTitle"></h3>
                <img id="aiModalImg" src="" alt="Product image" style="max-width:100%;margin-bottom:10px;"/>
                <p id="aiModalSummary"></p>
              </div>
            </div>`;
          document.body.insertAdjacentHTML('beforeend', modalHtml);
          document.getElementById('aiModalClose').onclick = () => {
            document.getElementById('aiModal').style.display = 'none';
          };
          document.getElementById('aiModal').onclick = (e) => {
            if (e.target === document.getElementById('aiModal')) document.getElementById('aiModal').style.display = 'none';
          };
        }

        // Add click handler to all description cells
        setTimeout(() => {
          document.querySelectorAll('.desc-ai').forEach(cell => {
            cell.onclick = function() {
              const desc = decodeURIComponent(this.getAttribute('data-desc'));
              document.getElementById('aiModalTitle').innerText = 'Loading...';
              document.getElementById('aiModalSummary').innerText = '';
              document.getElementById('aiModalImg').src = '';
              document.getElementById('aiModal').style.display = 'block';
              fetch(`product_info_ai.php?desc=${encodeURIComponent(desc)}`)
                .then(r => r.json())
                .then(data => {
                  document.getElementById('aiModalTitle').innerText = data.title || desc;
                  document.getElementById('aiModalSummary').innerText = data.summary || '';
                  document.getElementById('aiModalImg').src = data.image || '';
                })
                .catch(() => {
                  document.getElementById('aiModalTitle').innerText = desc;
                  document.getElementById('aiModalSummary').innerText = 'Could not fetch info.';
                  document.getElementById('aiModalImg').src = '';
                });
            };
          });
        }, 0);
      html += `<td><span class="price">$${parseFloat(product.price).toFixed(2)}</span></td>`;
      html += `<td>${unitDisplay}</td>`;
      html += `<td><span class="vendor-cell ${vendorClass}">${vendorDisplay}</span></td>`;
      html += `<td><span class="category">${product.category}</span></td>`;
      html += '</tr>';
    });
    html += '</tbody></table>';
    document.getElementById('results').innerHTML = html;
    document.getElementById('resultCount').textContent = `Found ${data.rows.length} result${data.rows.length !== 1 ? 's' : ''} for "${query}"`;
  }
  function showNoResults(query) {
    document.getElementById('resultCount').textContent = `No results found for "${query}"`;
    document.getElementById('results').innerHTML = `<div class="empty-state"><h3>🔍 No Results Found</h3><p>We couldn't find any products matching "<strong>${query}</strong>"</p></div>`;
  }
  function showError(error) {
    document.getElementById('resultCount').textContent = 'Search error occurred';
    document.getElementById('results').innerHTML = `<div class="empty-state"><h3>⚠️ Search Error</h3><p>There was a problem searching the database.</p><p><small>Error: ${error.message}</small></p></div>`;
  }
  return { init };
})();
