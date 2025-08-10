const { registerBlockType } = wp.blocks;
const { createElement: el, useEffect } = wp.element;
const { InspectorControls } = wp.blockEditor;
const {
  CheckboxControl,
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} = wp.components;

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

registerBlockType("mon-plugin/unity-webgl", {
  title: "Unity Embedder", // Nom affiché du bloc
  icon: iconUnity, // Icône personnalisée
  category: "embed", // Catégorie du bloc dans l’éditeur
  attributes: {
    selectedBuild: {
      type: "string",
      default: "", // Attribut qui stocke le build Unity sélectionné
    },
    showOptions: { type: "boolean", default: true },
    showOnMobile: { type: "boolean", default: false },
    showLogs: { type: "boolean", default: false },
    sizeMode: { type: "string", default: "aspect-ratio" },
    fixedHeight: { type: "number", default: 500 },
    aspectRatio: { type: "string", default: "16/9" },
  },

  // Fonction d’édition du bloc (affichage dans l’admin WordPress)
  edit: (props) => {
    const {
      attributes: {
        selectedBuild = "",
        showOptions = false,
        showOnMobile = false,
        showLogs = false,
        sizeMode = "aspect-ratio",
        fixedHeight = 500,
        aspectRatio = "16/9",
      },
      setAttributes,
    } = props;

    const builds = window.unityBuildsData?.builds || [];

    if (!builds.length) {
      return el(
        "div",
        null,
        el("p", null, "Aucun build Unity trouvé."),
        el(
          "a",
          {
            href: EnosiUnityData.urlAdmin + "?page=unity_webgl_admin", // adapte l'URL si besoin
            className: "button button-primary",
          },
          "Téléverser un build Unity"
        )
      );
    }

    const validSelectedBuild = builds.includes(selectedBuild)
      ? selectedBuild
      : "";

    useEffect(() => {
      if (validSelectedBuild !== selectedBuild) {
        setAttributes({ selectedBuild: "" });
      }
    }, [selectedBuild, validSelectedBuild]);

    const mainContent = el(
      "div",
      { style: { border: "1px solid grey", padding: "10px" } },
      el("label", { htmlFor: "select-build" }, WP_I18N.buildChoose),
      el(
        "select",
        {
          id: "select-build",
          value: validSelectedBuild,
          onChange: (e) => setAttributes({ selectedBuild: e.target.value }),
          "aria-label": WP_I18N.buildChoose,
        },
        el("option", { value: "" }, "-- Aucun --"),
        builds.map((build) => el("option", { key: build, value: build }, build))
      ),
      validSelectedBuild &&
        el(
          "div",
          { style: { marginTop: "10px" } },
          WP_I18N.buildSelectionne + `: ${validSelectedBuild}`
        )
    );

    const inspector = el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: "Options", initialOpen: true },
        el(CheckboxControl, {
          label: WP_I18N.showOptions,
          checked: showOptions,
          onChange: (value) => setAttributes({ showOptions: value }),
          __nextHasNoMarginBottom: true,
        }),
        el(CheckboxControl, {
          label: WP_I18N.showOnMobile,
          checked: showOnMobile,
          onChange: (value) => setAttributes({ showOnMobile: value }),
          __nextHasNoMarginBottom: true,
        }),
        el(CheckboxControl, {
          label: WP_I18N.showLogs,
          checked: showLogs,
          onChange: (value) => setAttributes({ showLogs: value }),
          __nextHasNoMarginBottom: true,
        }),
        el(SelectControl, {
          label: "Display Mode",
          value: sizeMode,
          options: [
            { label: "Aspect Ratio", value: "aspect-ratio" },
            { label: "Fixed Height", value: "fixed-height" },
          ],
          onChange: (value) => setAttributes({ sizeMode: value }),
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true,
        }),
        sizeMode === "aspect-ratio" &&
          el(TextControl, {
            label: "Aspect Ratio (ex: 16/9)",
            value: aspectRatio,
            onChange: (value) => {
              setAttributes({ aspectRatio: value });
            },
            help: !aspectRatioRegex.test(aspectRatio)
              ? WP_I18N.warnExpectedRatio
              : undefined,
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
          }),
        sizeMode === "fixed-height" &&
          el(TextControl, {
            label: "Hauteur fixe (px)",
            value: fixedHeight,
            onChange: (value) =>
              setAttributes({ fixedHeight: parseInt(value) || 0 }),
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
          })
      )
    );

    return [inspector, mainContent];
  },

  // Fonction qui sauvegarde la sortie HTML du bloc (affichage côté front)
  save: ({ attributes }) => {
    const {
      selectedBuild,
      showOptions,
      showOnMobile,
      showLogs,
      sizeMode,
      aspectRatio,
      fixedHeight,
    } = attributes;
    const shortcode = `[unity_webgl 
  build="${selectedBuild}" 
  showOptions="${showOptions ? "true" : "false"}" 
  showOnMobile="${showOnMobile ? "true" : "false"}" 
  showLogs="${showLogs ? "true" : "false"}"
  sizeMode="${sizeMode}" 
  aspectRatio="${aspectRatioRegex.test(aspectRatio) ? aspectRatio : "4/3"}"
  fixedHeight="${fixedHeight || 500}"]`;
    return el("div", null, shortcode);
  },
});
