<?php
// pages/partials/topbar.php
// Expects: $pageTitle variable
?>
<div class="topbar">
  <!-- 
    Updated: Removed d-none and display:none. 
    Added d-lg-none so it only appears on screens smaller than 992px.
    Added me-2 for spacing.
  -->
  <button id="sidebarToggle" class="btn btn-icon btn-outline d-lg-none me-2">
    <i class="fas fa-bars"></i>
  </button>
  
  <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>

  <!-- Note: The CSS we added earlier hides this on mobile to prevent clutter -->
  <div class="search-global">
    <i class="fas fa-search search-icon"></i>
    <input type="text" id="globalSearch" placeholder="Search students, courses, sessions...">
    <div class="search-results-dropdown" id="searchDropdown"></div>
  </div>

  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-icon btn-outline" title="Notifications">
      <i class="fas fa-bell"></i>
    </button>
  </div>
</div>