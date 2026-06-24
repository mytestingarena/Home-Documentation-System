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