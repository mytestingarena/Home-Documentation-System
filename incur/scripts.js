// scripts.js — tab navigation + mobile menu positioning

function isMobileMenu() {
  return window.matchMedia("(max-width: 768px)").matches;
}

function positionMobileMenu() {
  var header = document.getElementById("pageHeader");
  if (!header) return;

  if (isMobileMenu()) {
    var bottom = Math.round(header.getBoundingClientRect().bottom);
    document.documentElement.style.setProperty("--hds-menu-top", bottom + "px");
  } else {
    document.documentElement.style.removeProperty("--hds-menu-top");
    document.body.classList.remove("menu-open");
  }
}

function activateTab(tabName) {
  var i, tabcontent, tablinks;

  tabcontent = document.getElementsByClassName("tab");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
    var onclick = tablinks[i].getAttribute("onclick") || "";
    if (onclick.indexOf("'" + tabName + "'") !== -1) {
      tablinks[i].className += " active";
    }
  }

  var panel = document.getElementById(tabName);
  if (panel) {
    panel.style.display = "block";
  }
}

function openTab(evt, tabName) {
  activateTab(tabName);

  var houseId = window.HDS_HOUSE_ID || new URLSearchParams(window.location.search).get("id");
  if (houseId) {
    history.replaceState(null, "", "house.php?id=" + houseId + "&tab=" + encodeURIComponent(tabName));
  } else {
    history.replaceState(null, "", "#" + tabName);
  }

  var menu = document.getElementById("tabMenu");
  if (menu) {
    menu.classList.remove("active");
    document.body.classList.remove("menu-open");
  }
}

function toggleMenu() {
  var menu = document.getElementById("tabMenu");
  if (!menu) return;

  positionMobileMenu();
  menu.classList.toggle("active");
  document.body.classList.toggle("menu-open", menu.classList.contains("active"));
}

function getInitialTab() {
  var params = new URLSearchParams(window.location.search);
  var tab = params.get("tab");
  if (tab && document.getElementById(tab)) {
    return tab;
  }
  if (window.location.hash) {
    var hash = window.location.hash.substring(1);
    if (document.getElementById(hash)) {
      return hash;
    }
  }
  return "permanent";
}

document.addEventListener("DOMContentLoaded", function() {
  positionMobileMenu();
  activateTab(getInitialTab());
  scrollToOpenSection();
  initMediaLightbox();
  initMediaRenameModal();
  initViewEdit();
});

window.addEventListener("resize", positionMobileMenu);
window.addEventListener("orientationchange", positionMobileMenu);

function collapsibleExpandAll(selector, expand) {
  var cards = document.querySelectorAll(selector);
  for (var i = 0; i < cards.length; i++) {
    cards[i].open = expand;
  }
}

function maintenanceExpandAll(expand) {
  collapsibleExpandAll(".maintenance-list .collapsible-section", expand);
}

function scrollToOpenSection() {
  var params = new URLSearchParams(window.location.search);
  var scrollKeys = [
    ["open_equipment", "maintenance-eq-"],
    ["open_tool", "tool-"],
    ["open_panel", "panel-"],
    ["open_utility", "utility-"],
    ["open_media", "media-"],
    ["open_permanent", "permanent-"]
  ];

  for (var i = 0; i < scrollKeys.length; i++) {
    var val = params.get(scrollKeys[i][0]);
    if (!val) continue;

    var el = document.getElementById(scrollKeys[i][1] + val);
    if (el) {
      var parent = el.parentElement;
      while (parent) {
        if (parent.tagName === "DETAILS") {
          parent.open = true;
        }
        parent = parent.parentElement;
      }
      if (el.tagName === "DETAILS") {
        el.open = true;
      }
      el.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }
  }
}

function initMediaLightbox() {
  var lightbox = document.getElementById("mediaLightbox");
  if (!lightbox) return;

  if (lightbox.parentElement && lightbox.parentElement !== document.body) {
    document.body.appendChild(lightbox);
  }

  var imageEl = lightbox.querySelector(".media-lightbox-image");
  var captionEl = lightbox.querySelector(".media-lightbox-caption");
  var counterEl = lightbox.querySelector(".media-lightbox-counter");
  var prevBtn = lightbox.querySelector(".media-lightbox-prev");
  var nextBtn = lightbox.querySelector(".media-lightbox-next");
  var currentGallery = [];
  var currentIndex = 0;

  function allLightboxItems() {
    var mediaTab = document.getElementById("media");
    if (!mediaTab) return [];
    return Array.prototype.slice.call(mediaTab.querySelectorAll(".media-lightbox-trigger"));
  }

  function renderSlide() {
    if (!currentGallery.length || !imageEl) return;
    var item = currentGallery[currentIndex];
    var src = item.getAttribute("data-src") || "";
    var caption = item.getAttribute("data-caption") || "";
    imageEl.src = src;
    imageEl.alt = caption;
    if (captionEl) captionEl.textContent = caption;
    if (counterEl) {
      counterEl.textContent = (currentIndex + 1) + " of " + currentGallery.length;
    }
    if (prevBtn) prevBtn.style.visibility = currentGallery.length > 1 ? "visible" : "hidden";
    if (nextBtn) nextBtn.style.visibility = currentGallery.length > 1 ? "visible" : "hidden";
  }

  function openLightbox(trigger) {
    currentGallery = allLightboxItems();
    currentIndex = currentGallery.indexOf(trigger);
    if (currentIndex < 0) currentIndex = 0;
    renderSlide();
    lightbox.hidden = false;
    lightbox.setAttribute("aria-hidden", "false");
    document.body.classList.add("media-lightbox-active");
  }

  function closeLightbox() {
    lightbox.hidden = true;
    lightbox.setAttribute("aria-hidden", "true");
    document.body.classList.remove("media-lightbox-active");
    if (imageEl) imageEl.src = "";
  }

  function showRelative(step) {
    if (currentGallery.length < 2) return;
    currentIndex = (currentIndex + step + currentGallery.length) % currentGallery.length;
    renderSlide();
  }

  document.addEventListener("click", function(evt) {
    if (evt.target.closest("[data-lightbox-close]")) {
      closeLightbox();
      return;
    }
    var openBtn = evt.target.closest(".media-lightbox-trigger");
    if (openBtn) {
      evt.preventDefault();
      openLightbox(openBtn);
    }
  });

  if (prevBtn) prevBtn.addEventListener("click", function(evt) {
    evt.stopPropagation();
    showRelative(-1);
  });

  if (nextBtn) nextBtn.addEventListener("click", function(evt) {
    evt.stopPropagation();
    showRelative(1);
  });

  document.addEventListener("keydown", function(evt) {
    if (lightbox.hidden) return;
    if (evt.key === "Escape") closeLightbox();
    if (evt.key === "ArrowLeft") showRelative(-1);
    if (evt.key === "ArrowRight") showRelative(1);
  });

  var dialog = lightbox.querySelector(".media-lightbox-dialog");
  var touchStartX = 0;
  var minSwipe = 50;

  if (dialog) {
    dialog.addEventListener("touchstart", function(evt) {
      if (evt.touches.length === 1) {
        touchStartX = evt.touches[0].clientX;
      }
    }, { passive: true });

    dialog.addEventListener("touchend", function(evt) {
      if (lightbox.hidden || !evt.changedTouches.length) return;
      var touchEndX = evt.changedTouches[0].clientX;
      var diff = touchEndX - touchStartX;
      if (Math.abs(diff) < minSwipe) return;
      if (diff < 0) showRelative(1);
      else showRelative(-1);
    }, { passive: true });
  }
}

function initMediaRenameModal() {
  var modal = document.getElementById("mediaRenameModal");
  if (!modal) return;

  if (modal.parentElement && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  var currentEl = document.getElementById("mediaRenameCurrent");
  var photoIdEl = document.getElementById("mediaRenamePhotoId");
  var newNameEl = document.getElementById("mediaRenameNew");
  var extEl = document.getElementById("mediaRenameExt");
  var sectionEl = document.getElementById("mediaRenameSection");

  function splitFilename(filename) {
    var lastDot = filename.lastIndexOf(".");
    if (lastDot <= 0) {
      return { base: filename, ext: "" };
    }
    return {
      base: filename.substring(0, lastDot),
      ext: filename.substring(lastDot)
    };
  }

  function openRenameModal(button) {
    var filename = button.getAttribute("data-filename") || "";
    var photoId = button.getAttribute("data-photo-id") || "";
    var mediaSection = button.getAttribute("data-media-section") || "";
    var parts = splitFilename(filename);
    if (currentEl) currentEl.textContent = filename;
    if (photoIdEl) photoIdEl.value = photoId;
    if (sectionEl) sectionEl.value = mediaSection;
    if (extEl) extEl.textContent = parts.ext;
    if (newNameEl) {
      newNameEl.value = parts.base;
      setTimeout(function() {
        newNameEl.focus();
        newNameEl.select();
      }, 0);
    }
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("media-rename-active");
  }

  function closeRenameModal() {
    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("media-rename-active");
  }

  document.addEventListener("click", function(evt) {
    if (evt.target.closest("[data-rename-close]")) {
      closeRenameModal();
      return;
    }
    var openBtn = evt.target.closest(".media-rename-open");
    if (openBtn) {
      openRenameModal(openBtn);
    }
  });

  document.addEventListener("keydown", function(evt) {
    if (modal.hidden) return;
    if (evt.key === "Escape") closeRenameModal();
  });
}

function initViewEdit() {
  function showView(block) {
    var view = block.querySelector("[data-view-edit-view]");
    var edit = block.querySelector("[data-view-edit-form]");
    if (view) view.hidden = false;
    if (edit) edit.hidden = true;
  }

  function showEdit(block) {
    var view = block.querySelector("[data-view-edit-view]");
    var edit = block.querySelector("[data-view-edit-form]");
    if (view) view.hidden = true;
    if (edit) {
      edit.hidden = false;
      var firstInput = edit.querySelector("input:not([type='hidden']), textarea, select");
      if (firstInput) {
        setTimeout(function() {
          firstInput.focus();
        }, 0);
      }
    }
  }

  function closeAllExcept(block) {
    var blocks = document.querySelectorAll("[data-view-edit]");
    for (var i = 0; i < blocks.length; i++) {
      if (blocks[i] !== block) {
        showView(blocks[i]);
      }
    }
  }

  document.addEventListener("click", function(evt) {
    var openBtn = evt.target.closest("[data-view-edit-open]");
    if (openBtn) {
      var block = openBtn.closest("[data-view-edit]");
      if (!block) return;
      closeAllExcept(block);
      showEdit(block);
      return;
    }

    var cancelBtn = evt.target.closest("[data-view-edit-cancel]");
    if (cancelBtn) {
      var cancelBlock = cancelBtn.closest("[data-view-edit]");
      if (cancelBlock) showView(cancelBlock);
    }
  });
}

function toggleWifiPassword(button) {
  var row = button.closest(".wifi-password-row");
  if (!row) return;

  var field = row.querySelector(".wifi-password-field");
  if (!field) return;

  if (field.type === "password") {
    field.type = "text";
    button.textContent = "Hide";
  } else {
    field.type = "password";
    button.textContent = "Show";
  }
}