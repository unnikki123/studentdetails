<!-- ======== MOBILE SEARCH SHEET ======== -->
<div class="mobile-search-sheet-overlay" id="mobileSearchOverlay" onclick="mobileCloseSearch()"></div>
<div class="mobile-search-sheet" id="mobileSearchSheet">
  <div class="sheet-handle"></div>
  <form id="mobileSearchForm">
    <label for="mobileSearchInput" class="visually-hidden">Roll number or name</label>
    <input type="text" id="mobileSearchInput" placeholder="Enter roll number or name…" autocomplete="off" autocapitalize="characters" inputmode="text">
    <div id="mobileRecentSearches" class="recent-chips"></div>
    <button type="submit">Search <i class="fas fa-arrow-right ms-1"></i></button>
  </form>
</div>