<!-- ======== LOGIN MODAL ======== -->
<div class="modal fade" id="loginModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i>Admin login</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="loginForm">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="email" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:var(--ink-muted);">Email</label>
            <input type="email" class="form-control" id="email" autocomplete="username" required placeholder="your@email.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="password" style="font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:var(--ink-muted);">Password</label>
            <input type="password" class="form-control" id="password" autocomplete="current-password" required placeholder="••••••••">
          </div>
          <div id="errorMessage" class="alert alert-danger d-none" style="font-size:13px;"></div>
          <button class="btn btn-primary w-100 fw-semibold" style="padding:10px; background:var(--accent); border-color:var(--accent); border-radius:8px;">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>