<!-- ======== HERO ======== -->
<div class="hero" id="heroSection">
  <div class="hero-card">

    <!-- accent stripe rendered by ::before -->

    <div class="hero-top">
      <div class="hero-copy">
        <div class="hero-kicker"><i class="fas fa-shield-halved"></i> Student academic lookup</div>
        <div class="hero-icon"><i class="fas fa-graduation-cap"></i></div>
        <h1 class="hero-title">Student Details<br>Portal</h1>
        <p class="hero-subtitle">Search student records, attendance, marks, grades, and certificates — all in one clean dashboard.</p>
      </div>

      <div class="hero-panel">
        <form id="heroSearchForm">
          <div class="hero-search-box">
            <i class="fas fa-search" style="color:var(--ink-faint); font-size:14px; flex-shrink:0;"></i>
            <label for="heroSearchInput" class="visually-hidden">Roll number or name</label>
            <input id="heroSearchInput" placeholder="Enter roll number or student name" autocomplete="off" autocapitalize="characters" aria-label="Search by roll number or student name">
            <button type="submit"><i class="fas fa-arrow-right me-1"></i>Search</button>
          </div>
        </form>

        <div class="hero-metrics">
          <div class="hero-metric">
            <strong><i class="fas fa-bolt text-primary me-1"></i>Fast</strong>
            <span>Instant roll or name lookup</span>
          </div>
          <div class="hero-metric">
            <strong><i class="fas fa-chart-line text-success me-1"></i>Live</strong>
            <span>Attendance &amp; result views</span>
          </div>
          <div class="hero-metric">
            <strong><i class="fas fa-file-pdf text-danger me-1"></i>PDF</strong>
            <span>Downloadable reports</span>
          </div>
        </div>
      </div>
    </div>

    <div class="feature-pills">
      <div class="pill"><i class="fas fa-bolt"></i> Instant results</div>
      <div class="pill"><i class="fas fa-calendar-check"></i> Attendance</div>
      <div class="pill"><i class="fas fa-chart-bar"></i> Mid marks</div>
      <div class="pill"><i class="fas fa-graduation-cap"></i> Grades</div>
      <div class="pill"><i class="fas fa-filter"></i> Smart filters</div>
      <div class="pill"><i class="fas fa-file-pdf"></i> PDF export</div>
    </div>

    <!-- Today's Birthdays -->
    <div class="today-birthdays-section mt-4" id="todayBirthdaysSection" style="display:none;">
      <div class="card" style="border-radius: var(--radius); border: 1px solid var(--border); background: linear-gradient(135deg, #fff5f5 0%, #fff0f5 100%);">
        <div class="card-body py-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0" style="color: #e91e63;"><i class="fas fa-birthday-cake me-2"></i>Today's Birthdays</h6>
            <span class="badge bg-pink" style="background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);" id="birthdayDate"></span>
          </div>
          <div id="birthdaysList" class="d-flex flex-wrap gap-2"></div>
        </div>
      </div>
    </div>

    <div class="home-tabs-wrap mt-0">
      <ul class="nav nav-tabs" id="homeTabs" role="tablist">
        <li class="nav-item">
          <button class="nav-link active" id="tab-search" data-bs-toggle="tab" data-bs-target="#tabPaneSearch" type="button">Search</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="tab-filters" data-bs-toggle="tab" data-bs-target="#tabPaneFilters" type="button">Filters</button>
        </li>
      </ul>
      <div class="tab-content tab-surface">
        <div class="tab-pane fade show active" id="tabPaneSearch">
          <p class="small text-muted mb-0">Use the search bar above or the navbar search to look up a student by roll number or name.</p>
        </div>
        <div class="tab-pane fade" id="tabPaneFilters">
          <form id="filterForm" class="row g-3 filter-form">
            <div class="col-12 col-md-6">
              <label for="filterSemester">Semester info</label>
              <select class="form-select" id="filterSemester" required><option value="">Loading…</option></select>
            </div>
            <div class="col-12 col-md-3">
              <label for="filterDepartment">Department</label>
              <select class="form-select" id="filterDepartment" disabled><option value="">Select semester first</option></select>
            </div>
            <div class="col-6 col-md-3">
              <label for="filterCgpaMin">CGPA min</label>
              <input type="number" step="0.01" class="form-control" id="filterCgpaMin" placeholder="e.g. 7.0" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
              <label for="filterCgpaMax">CGPA max</label>
              <input type="number" step="0.01" class="form-control" id="filterCgpaMax" placeholder="e.g. 9.5" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
              <label for="filterSgpaMin">SGPA min</label>
              <input type="number" step="0.01" class="form-control" id="filterSgpaMin" placeholder="e.g. 7.0" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
              <label for="filterSgpaMax">SGPA max</label>
              <input type="number" step="0.01" class="form-control" id="filterSgpaMax" placeholder="e.g. 9.5" autocomplete="off">
            </div>
            <div class="col-12 col-md-3">
              <label for="filterFCount">F count</label>
              <select class="form-select" id="filterFCount">
                <option value="">Any</option>
                <option value="0">0</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3plus">3+</option>
              </select>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-end">
              <button type="submit" class="btn-apply-filter">Apply filters</button>
            </div>
          </form>
          <div id="filterError" class="alert alert-danger mt-3 d-none"></div>
          <div id="filterResults" class="mt-3" style="display:none;">
            <div id="filterCount" class="small text-muted mb-2"></div>
            <div class="table-responsive">
              <table class="table table-sm table-striped table-hover mb-0 table-minimal">
                <thead>
                  <tr>
                    <th>Roll no</th><th>Name</th><th>Dept</th><th>Semester</th>
                    <th class="text-end">SGPA</th><th class="text-end">CGPA</th>
                    <th class="text-end">F count</th><th></th>
                  </tr>
                </thead>
                <tbody id="filterResultsBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>