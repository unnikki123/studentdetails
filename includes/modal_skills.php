<!-- ======== MODAL: Skills ======== -->
<div class="modal fade" id="skillsModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ink); color:#fff;">
        <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>UK &mdash; Skills &amp; Projects</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" id="skillsTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#skills-content" type="button"><i class="fas fa-code me-2"></i>Skills</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#projects-content" type="button"><i class="fas fa-project-diagram me-2"></i>Projects</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#papers-content" type="button"><i class="fas fa-file-alt me-2"></i>Papers</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#contact-content" type="button"><i class="fas fa-envelope me-2"></i>Contact</button></li>
        </ul>
        <div class="tab-content mt-3">

          <!-- Skills -->
          <div class="tab-pane fade show active" id="skills-content">
            <h6 class="fw-bold mb-3"><i class="fas fa-code me-2 text-primary"></i>Technical skills</h6>
            <ul class="list-unstyled">
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Java, Jetpack Compose, MVVM, SQLite, JSON</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Android application development</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Kotlin Android, Jetpack Compose</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Firebase (Realtime, Firestore, FCM)</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>AWS (EC2, S3, Lambda) &mdash; basic knowledge</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>PHP, MySQL, XAMPP (Apache)</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Bootstrap 5, responsive UI, HTML/CSS</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>JavaScript, jQuery, AJAX, JSON APIs</li>
              <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>PDF reports &amp; exports (mPDF)</li>
            </ul>
            <h6 class="fw-bold mb-3 mt-4"><i class="fas fa-project-diagram me-2 text-primary"></i>Tools &amp; technologies</h6>
            <div class="row">
              <div class="col-md-6">
                <h6 class="text-muted mb-2" style="font-size:12px;">Mobile</h6>
                <ul class="list-unstyled mb-3">
                  <li class="mb-1"><i class="fas fa-laptop-code text-info me-2"></i>Kotlin / Jetpack Compose</li>
                  <li class="mb-1"><i class="fas fa-laptop-code text-info me-2"></i>MVVM / Dagger Hilt / Room DB</li>
                  <li class="mb-1"><i class="fas fa-laptop-code text-info me-2"></i>Coroutines &amp; Flow</li>
                </ul>
              </div>
              <div class="col-md-6">
                <h6 class="text-muted mb-2" style="font-size:12px;">Backend &amp; Cloud</h6>
                <ul class="list-unstyled mb-3">
                  <li class="mb-1"><i class="fas fa-database text-warning me-2"></i>Firebase Auth, Firestore, Cloud Functions</li>
                  <li class="mb-1"><i class="fas fa-database text-warning me-2"></i>REST APIs / Retrofit / GraphQL</li>
                  <li class="mb-1"><i class="fas fa-database text-warning me-2"></i>Git, Postman, Gradle</li>
                  <li class="mb-1"><i class="fas fa-database text-warning me-2"></i>ngrok (tunneling / public URL for local server)</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Projects -->
          <div class="tab-pane fade" id="projects-content">
            <?php
            $projects = [
              ['color'=>'success','icon'=>'mobile-alt','title'=>'Quadratic Equations Comparison','status'=>'Published','desc'=>'Compares two quadratic equations side-by-side, displaying results in a ScrollView.','tech'=>'Jetpack Compose, MVVM, Room'],
              ['color'=>'warning','icon'=>'brain','title'=>'Remember Me','status'=>'Not published','desc'=>'Remembers places and people by taking pictures and naming them for easy retrieval.','tech'=>'Camera API, Room, Coroutines'],
              ['color'=>'success','icon'=>'university','title'=>'CRR COE LAB','status'=>'Published','desc'=>'Helps students access academic lab manuals according to their syllabus.','tech'=>'Firebase Firestore, MVVM, PDF Viewer','link'=>'https://play.google.com/store/apps/details?id=com.crr.crrlab'],
              ['color'=>'success','icon'=>'info-circle','title'=>'CRR COE INFO','status'=>'Published','desc'=>'Allows organisations to post info; students view attendance and marks by branch.','tech'=>'Firebase Auth, Firestore, JWT, RBAC','link'=>'https://play.google.com/store/apps/details?id=com.ukv.crrstudentinfo'],
              ['color'=>'warning','icon'=>'user-check','title'=>'CRR COE ATT','status'=>'Not published','desc'=>'Posts student attendance per section; sends messages based on attendance data.','tech'=>'Firebase Realtime DB, FCM, Admin Dashboard'],
              ['color'=>'success','icon'=>'chart-line','title'=>'D_ATTENDANCE','status'=>'Published','desc'=>'Posts attendance, sends messages, and allows students to view marks.','tech'=>'Firebase Auth, Realtime DB, Cloud Functions','link'=>'https://play.google.com/store/apps/details?id=com.crrcoe.crratt'],
              ['color'=>'success','icon'=>'clipboard-list','title'=>'Student Details &amp; Attendance Reports','status'=>'Not published','desc'=>'Student details portal with adaptive attendance analytics, subject-wise reports, results, and PDF exports.','tech'=>'PHP, MySQL, Bootstrap 5, jQuery/AJAX, mPDF, XAMPP'],
            ];
            foreach($projects as $p):
              $textColor = $p['color']==='warning' ? 'dark' : 'white';
            ?>
              <div class="card mb-3 border">
                <div class="card-header bg-<?= $p['color'] ?> text-<?= $textColor ?>">
                  <h6 class="mb-0" style="font-size:13px;"><i class="fas fa-<?= $p['icon'] ?> me-2"></i><?= $p['title'] ?></h6>
                </div>
                <div class="card-body small">
                  <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?= $p['color'] ?>"><?= $p['status'] ?></span>
                  <?php if(!empty($p['link'])): ?> &nbsp;<a href="<?= $p['link'] ?>" target="_blank" class="small">Play Store &nearr;</a><?php endif; ?></p>
                  <p class="mb-1"><?= $p['desc'] ?></p>
                  <p class="mb-0 text-muted"><strong>Tech:</strong> <?= $p['tech'] ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Papers -->
          <div class="tab-pane fade" id="papers-content">
            <?php
            $papers = [
              ['title'=>'Predicting Buy and Sell Signals for Stocks using Bollinger Bands and MACD with Help of Machine Learning','venue'=>'2023 International Conference on Sustainable Computing and Smart Systems (ICSCSS)'],
              ['title'=>'Security using Blowfish Cryptography with Distributed Keys for Data Sharing on Cloud','venue'=>'International Journal of Advanced Science and Technology 2020'],
              ['title'=>'A Comparison between Shortest Path Algorithms Using Runtime Analysis and Negative Edges in Computer Networks','venue'=>'2022 International Mobile and Embedded Technology Conference (MECON)'],
            ];
            foreach($papers as $p): ?>
              <div class="card mb-3 border">
                <div class="card-header" style="font-size:13px;"><strong><?= $p['title'] ?></strong></div>
                <div class="card-body small text-muted">Published in: <?= $p['venue'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Contact -->
          <div class="tab-pane fade" id="contact-content">
            <div class="row">
              <div class="col-md-6">
                <p class="mb-2"><i class="fas fa-user me-2 text-primary"></i><strong>Name:</strong> UK</p>
                <p class="mb-2"><i class="fas fa-envelope me-2 text-primary"></i><strong>Email:</strong> oceanflux43@gmail.com</p>
                <p class="mb-2"><i class="fas fa-phone me-2 text-primary"></i><strong>Phone:</strong> +91 90598 80899</p>
              </div>
              <div class="col-md-6">
                <p class="mb-2"><i class="fab fa-google-play me-2 text-primary"></i><a href="https://play.google.com/store/apps/dev?id=6203139133621648872" target="_blank">Google Play Profile</a></p>
                <p class="mb-2"><i class="fab fa-linkedin me-2 text-primary"></i><a href="https://www.linkedin.com/in/unnijalad" target="_blank">linkedin.com/in/unnijalad</a></p>
              </div>
            </div>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>