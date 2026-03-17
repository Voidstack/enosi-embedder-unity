const { registerBlockType } = wp.blocks;
const { createElement: el, useEffect } = wp.element;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl } = wp.components;

const aspectRatioRegex = /^[1-9]\d*\/[1-9]\d*$/;

// Définition de l'icône personnalisée SVG pour le bloc Unity
const iconUnity = {
  src: el(
    "svg",
    { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 64 64" },
    el("path", {
      d: "M63.22 25.42L56.387 0 30.87 6.814l-3.775 6.637-7.647-.055L.78 32.005l18.67 18.604 7.658-.057 3.78 6.637 25.5 6.81 6.832-25.418L59.34 32zm-16-15.9L36.036 28.86H13.644l14.094-14.34zM36.036 35.145l11.196 19.338-19.507-5.012L13.63 35.15h22.392zm5.468-3.14L52.7 12.665l5.413 19.34L52.7 51.34z",
    })
  ),
};

registerBlockType("wpunity/unity-webgl", {
  title: "Unity Embedder",
  icon: iconUnity,
  category: "embed",
  attributes: {
    selectedBuild: { type: "string", default: "" },
    showOptions:   { type: "boolean", default: true },
    showOnMobile:  { type: "boolean", default: false },
    showLogs:      { type: "boolean", default: false },
    fixedHeight:   { type: "number", default: 0 },
    aspectRatio:   { type: "string", default: "" },
  },

  edit: (props) => {
    const {
      attributes: {
        selectedBuild = "",
        showOptions = true,
        showOnMobile = false,
        showLogs = false,
        fixedHeight = 0,
        aspectRatio = "",
      },
      setAttributes,
    } = props;

    const builds = window.unityBuildsData?.builds || [];

    if (!builds.length) {
      return el(
        "div",
        null,
        el("p", null, enosiI18n.noBuildsFound),
        el(
          "a",
          { href: enosiI18n.urlAdmin + "?page=unity_webgl_admin", className: "button button-primary" },
          enosiI18n.uploadBuild
        )
      );
    }

    const validSelectedBuild = builds.includes(selectedBuild) ? selectedBuild : "";

    useEffect(() => {
      if (validSelectedBuild !== selectedBuild) {
        setAttributes({ selectedBuild: "" });
      }
    }, [selectedBuild, validSelectedBuild]);

    const mainContent = el(
      "div",
      { style: { border: "1px solid grey", padding: "10px" } },
      el("label", { htmlFor: "select-build" }, enosiI18n.buildChoose),
      el(
        "select",
        {
          id: "select-build",
          value: validSelectedBuild,
          onChange: (e) => setAttributes({ selectedBuild: e.target.value }),
          "aria-label": enosiI18n.buildChoose,
        },
        el("option", { value: "" }, "-- Aucun --"),
        builds.map((build) => el("option", { key: build, value: build }, build))
      ),
      validSelectedBuild &&
        el(
          "div",
          { style: { marginTop: "10px" } },
          enosiI18n.buildSelectionne + `: ${validSelectedBuild}`
        )
    );

    const inspector = el(
      InspectorControls,
      null,

      // --- Dimensions ---
      el(
        PanelBody,
        { title: enosiI18n.panelDimensions, initialOpen: true },
        el(TextControl, {
          label: "Aspect Ratio",
          value: aspectRatio,
          placeholder: "ex: 16/9",
          onChange: (value) => setAttributes({ aspectRatio: value }),
          help: aspectRatio && !aspectRatioRegex.test(aspectRatio)
            ? el("span", null, enosiI18n.warnExpectedRatio1, el("br"), enosiI18n.warnExpectedRatio2)
            : undefined,
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true,
        }),
        el(TextControl, {
          label: "Fixed Height (px)",
          value: fixedHeight || "",
          placeholder: "ex: 500",
          type: "number",
          min: "1",
          onChange: (value) => setAttributes({ fixedHeight: parseInt(value) || 0 }),
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true,
        }),
        el("p", { style: { color: "#757575", fontSize: "12px", marginTop: "8px", marginBottom: 0 } },
          enosiI18n.dimensionsHelp
        )
      ),

      // --- Options ---
      el(
        PanelBody,
        { title: enosiI18n.panelOptions, initialOpen: true },
        el(ToggleControl, {
          label: enosiI18n.showToolbar,
          checked: showOptions,
          onChange: (value) => setAttributes({ showOptions: value }),
          __nextHasNoMarginBottom: true,
        }),
        el(ToggleControl, {
          label: enosiI18n.showOnMobile,
          checked: showOnMobile,
          onChange: (value) => setAttributes({ showOnMobile: value }),
          __nextHasNoMarginBottom: true,
        })
      ),

      // --- Developer ---
      el(
        PanelBody,
        { title: enosiI18n.panelDeveloper, initialOpen: false },
        el(ToggleControl, {
          label: enosiI18n.showLogs,
          checked: showLogs,
          onChange: (value) => setAttributes({ showLogs: value }),
          __nextHasNoMarginBottom: true,
        })
      )
    );

    return [inspector, mainContent];
  },

  save: ({ attributes }) => {
    const { selectedBuild, showOptions, showOnMobile, showLogs, aspectRatio, fixedHeight } = attributes;
    const shortcode = `[unity_webgl
  build="${selectedBuild}"
  showOptions="${showOptions ? "true" : "false"}"
  showOnMobile="${showOnMobile ? "true" : "false"}"
  showLogs="${showLogs ? "true" : "false"}"
  aspectRatio="${aspectRatioRegex.test(aspectRatio) ? aspectRatio : ""}"
  fixedHeight="${fixedHeight || 0}"]`;
    return el("div", null, shortcode);
  },
});
