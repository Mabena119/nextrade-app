
      </main>
      <footer class="aura-console-foot">NexTradeAI · © <?php echo date('Y'); ?></footer>
    </div>
  </div>

  <div class="aura-toast" id="auraToast" role="status" aria-live="polite">Copied!</div>

  <script src="vendors/js/vendor.bundle.base.js"></script>
  <?php if ( ! ( $GLOBALS['admin_light_assets'] ?? false ) ): ?>
  <script src="vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="js/dataTables.select.min.js"></script>
  <?php endif; ?>

<script>
(function () {
  var sidebar = document.getElementById('auraSidebar');
  var backdrop = document.getElementById('auraSidebarBackdrop');
  var menuBtn = document.getElementById('auraMenuBtn');
  var profileBtn = document.getElementById('auraProfileBtn');
  var profileMenu = document.getElementById('auraProfileMenu');
  var toast = document.getElementById('auraToast');
  var toastTimer;

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
  }
  function toggleSidebar() {
    if (!sidebar) return;
    sidebar.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('open');
  }
  if (menuBtn) menuBtn.addEventListener('click', toggleSidebar);
  if (backdrop) backdrop.addEventListener('click', closeSidebar);
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 960px)').matches) closeSidebar();
      });
    });
  }

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      profileMenu.classList.toggle('open');
    });
    document.addEventListener('click', function () {
      profileMenu.classList.remove('open');
    });
  }

  window.auraCopyText = function (text, btn) {
    if (!text) return;
    var done = function () {
      if (btn) {
        btn.classList.add('copied');
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-check"></i> Copied';
        setTimeout(function () {
          btn.classList.remove('copied');
          btn.innerHTML = old;
        }, 1600);
      }
      if (toast) {
        toast.textContent = 'Copied to clipboard';
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 1800);
      }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        window.prompt('Copy this text:', text);
      });
    } else {
      window.prompt('Copy this text:', text);
      done();
    }
  };

  document.querySelectorAll('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var text = el.getAttribute('data-copy') || '';
      auraCopyText(text, el);
    });
  });

  document.querySelectorAll('.aura-copy-btn').forEach(function (btn) {
    if (btn.hasAttribute('data-copy')) return;
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.aura-copy');
      var code = wrap ? wrap.querySelector('code') : null;
      auraCopyText(code ? code.textContent.trim() : '', btn);
    });
  });
})();
</script>
</body>
</html>
