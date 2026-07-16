<!-- ======== PODIUM SECTION ======== -->
<?php if(count($podiumStudents) >= 3): ?>
<div class="container-fluid py-3 px-2 px-md-3">
  <div class="podium-section">
    <div class="podium-header">
      <div>
        <div class="podium-title"><i class="fas fa-trophy me-2"></i>Top Performers</div>
        <div class="podium-subtitle">Based on CGPA from Student Results</div>
      </div>
      <a href="leaderboard.php" class="btn btn-sm btn-light">View Full Leaderboard <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="podium-container">
      <!-- Silver (2nd position) - shown on left -->
      <?php if(isset($podiumStudents[1])): ?>
      <div class="podium-item silver">
        <div class="podium-rank">2</div>
        <div class="podium-avatar">
          <?php echo strtoupper(substr($podiumStudents[1]['student_name'] ?? '?', 0, 1)); ?>
        </div>
        <div class="podium-name"><?php echo htmlspecialchars($podiumStudents[1]['student_name'] ?? 'N/A'); ?></div>
        <div class="podium-roll"><?php echo htmlspecialchars($podiumStudents[1]['roll_no'] ?? 'N/A'); ?></div>
        <div class="podium-cgpa"><?php echo number_format((float)($podiumStudents[1]['cgpa'] ?? 0), 2); ?></div>
        <div class="podium-platform">2nd</div>
      </div>
      <?php endif; ?>
      
      <!-- Gold (1st position) - shown in center and taller -->
      <?php if(isset($podiumStudents[0])): ?>
      <div class="podium-item gold">
        <div class="podium-rank">1</div>
        <div class="podium-avatar">
          <?php echo strtoupper(substr($podiumStudents[0]['student_name'] ?? '?', 0, 1)); ?>
        </div>
        <div class="podium-name"><?php echo htmlspecialchars($podiumStudents[0]['student_name'] ?? 'N/A'); ?></div>
        <div class="podium-roll"><?php echo htmlspecialchars($podiumStudents[0]['roll_no'] ?? 'N/A'); ?></div>
        <div class="podium-cgpa"><?php echo number_format((float)($podiumStudents[0]['cgpa'] ?? 0), 2); ?></div>
        <div class="podium-platform">1st</div>
      </div>
      <?php endif; ?>
      
      <!-- Bronze (3rd position) - shown on right -->
      <?php if(isset($podiumStudents[2])): ?>
      <div class="podium-item bronze">
        <div class="podium-rank">3</div>
        <div class="podium-avatar">
          <?php echo strtoupper(substr($podiumStudents[2]['student_name'] ?? '?', 0, 1)); ?>
        </div>
        <div class="podium-name"><?php echo htmlspecialchars($podiumStudents[2]['student_name'] ?? 'N/A'); ?></div>
        <div class="podium-roll"><?php echo htmlspecialchars($podiumStudents[2]['roll_no'] ?? 'N/A'); ?></div>
        <div class="podium-cgpa"><?php echo number_format((float)($podiumStudents[2]['cgpa'] ?? 0), 2); ?></div>
        <div class="podium-platform">3rd</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
