<!-- ======== MODAL: 65-75% ======== -->
<div class="modal fade" id="65to75ReportModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:#d97706; color:#fff;">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>65-75% Attendance Report (Condonation)</h5>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-dark" onclick="download65to75Pdf()"><i class="fas fa-file-pdf me-1"></i>PDF</button>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div class="small text-muted">Students with <b>overall attendance 65-75%</b> in the latest month</div>
          <button class="btn btn-sm btn-outline-warning" onclick="load65to75Report()"><i class="fas fa-rotate me-1"></i>Refresh</button>
        </div>
        <div id="65to75ReportContent" class="small text-muted">Loadingâ€¦</div>
        <div class="small text-muted mt-2">* This is an unofficial view for convenience only. Final authority is the original/official records. We do not guarantee accuracy or completeness.</div>
      </div>
    </div>
  </div>
</div>
