<!-- ======== NAVBAR ======== -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid d-flex align-items-center gap-3">

    <a class="navbar-brand" href="#">
      <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
      <span class="brand-text">Student<span>Portal</span></span>
    </a>

    <form class="d-flex align-items-center gap-2" id="navbarSearchForm" style="flex:1; max-width:360px;">
      <div class="search-wrap flex-grow-1">
        <i class="fas fa-search search-icon"></i>
        <label for="navbarSearch" class="visually-hidden">Roll number or name</label>
        <input class="search-input w-100" id="navbarSearch" placeholder="Search roll no. or name…" autocomplete="off" aria-label="Search by roll number or student name">
      </div>
      <button type="submit" class="search-btn">Search</button>
    </form>

    <div class="d-flex align-items-center gap-2 ms-auto">

      <span class="uk-tag" onclick="showSkillsModal()">✦ Unnikiran</span>

      <div class="nav-badge">
        <i class="fas fa-eye" style="font-size:10px;"></i>
        <span style="font-family:var(--font-mono); font-size:11px; font-weight:600;">
          <?php echo number_format($viewCount); ?>
        </span>
      </div>

      <a class="nav-link-custom" href="#" onclick="showLogin()">
        <i class="fas fa-user" style="font-size:11px;"></i> Login
      </a>

      <a class="nav-link-custom" href="#" onclick="showPrivacyPolicy()">
        <i class="fas fa-shield-alt" style="font-size:11px;"></i> Privacy
      </a>

    </div>

  </div>
</nav>