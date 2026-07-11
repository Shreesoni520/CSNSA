"use strict";

var base = {
  defaultFontFamily: "Overpass, sans-serif",
  primaryColor: "#1b68ff",
  secondaryColor: "#4f4f4f",
  successColor: "#3ad29f",
  warningColor: "#ffc107",
  infoColor: "#17a2b8",
  dangerColor: "#dc3545",
  darkColor: "#343a40",
  lightColor: "#f2f3f6"
};

var extend = {
  primaryColorLight: tinycolor(base.primaryColor).lighten(10).toString(),
  primaryColorLighter: tinycolor(base.primaryColor).lighten(30).toString(),
  primaryColorDark: tinycolor(base.primaryColor).darken(10).toString(),
  primaryColorDarker: tinycolor(base.primaryColor).darken(30).toString()
};

var chartColors = [base.primaryColor, base.successColor, "#6f42c1", extend.primaryColorLighter];

var lightColors = {
  bodyColor: "#6c757d",
  headingColor: "#495057",
  borderColor: "#e9ecef",
  backgroundColor: "#f8f9fa",
  mutedColor: "#adb5bd",
  chartTheme: "light"
};

var darkColor = {
  bodyColor: "#adb5bd",
  headingColor: "#e9ecef",
  borderColor: "#212529",
  backgroundColor: "#495057",
  mutedColor: "#adb5bd",
  chartTheme: "dark"
};

var colors = lightColors;

var darkStylesheet = document.querySelector("#darkTheme");
var lightStylesheet = document.querySelector("#lightTheme");
var switcher = document.querySelector("#modeSwitcher");

function applyTheme(mode) {
  var isDark = mode === "dark";
  if (darkStylesheet) darkStylesheet.disabled = !isDark;
  if (lightStylesheet) lightStylesheet.disabled = isDark;
  colors = isDark ? darkColor : lightColors;
  document.body.classList.remove("light", "dark");
  document.body.classList.add(isDark ? "dark" : "light");
  if (switcher) switcher.dataset.mode = mode;
  localStorage.setItem("mode", mode);
}

function modeSwitch() {
  var current = localStorage.getItem("mode");
  if (!current) {
    current = document.body.classList.contains("dark") ? "dark" : "light";
  }
  applyTheme(current === "dark" ? "light" : "dark");
}

var savedTheme = localStorage.getItem("mode");
if (savedTheme === "dark" || savedTheme === "light") {
  applyTheme(savedTheme);
} else if (document.body.classList.contains("dark")) {
  applyTheme("dark");
} else {
  applyTheme("light");
}
