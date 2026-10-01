// Mobile sidebar toggle
$(document).ready(function () {
  var $sidebar  = $('#navbar-example3');
  var $overlay  = $('#sidebarOverlay');
  var $toggle   = $('#sidebarToggle');

  function openSidebar() {
    $sidebar.addClass('doc-sidebar-open');
    $overlay.addClass('active');
    $('body').addClass('doc-sidebar-active');
  }

  function closeSidebar() {
    $sidebar.removeClass('doc-sidebar-open');
    $overlay.removeClass('active');
    $('body').removeClass('doc-sidebar-active');
  }

  $toggle.on('click', function () {
    if ($sidebar.hasClass('doc-sidebar-open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  $overlay.on('click', function () {
    closeSidebar();
  });

  // Close sidebar on nav link click (mobile)
  $sidebar.find('.nav-link').on('click', function () {
    if ($(window).width() < 992) {
      closeSidebar();
    }
  });

  // Close on resize to desktop
  $(window).on('resize', function () {
    if ($(window).width() >= 992) {
      closeSidebar();
    }
  });
});
