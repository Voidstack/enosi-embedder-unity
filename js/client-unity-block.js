import { UnityToolbar } from "./client-unity-toolbar.js";
import { UnityLoader } from "./client-unity-loader.js";

class UnityInstanceManager {
  constructor(uuid) {
    this.uuid = uuid;
    this.unityCanvas = document.getElementById(uuid + "-canvas");
    this.errorDiv = document.getElementById(uuid + "-error");
    this.unityContainer = document.getElementById(uuid + "-container");
    this.config = null;
    this.unityInstance = null;

    // Extract configuration values from the dataset of the canvas element
    this.canvasData = {
      buildUrl: this.unityCanvas.dataset.buildUrl,
      loaderName: this.unityCanvas.dataset.loaderName,
      showOptions: this.unityCanvas.dataset.showOptions === "true",
      showLogs: this.unityCanvas.dataset.showLogs === "true",
      fixedHeight: parseInt(this.unityCanvas.dataset.fixedHeight, 10) || 0,
      aspectRatio: this.unityCanvas.dataset.aspectRatio,
      currentUserIsAdmin: this.unityCanvas.dataset.currentUserIsAdmin === "true",
      admMessage: this.unityCanvas.dataset.admMessage,
    };

    this.loader = new UnityLoader(this.unityContainer);
  }

  /**
   * Displays a banner (error/warning) in the errorDiv element.
   */
  showBanner(msg, type) {
    const div = document.createElement("div");
    div.innerHTML = `${this.canvasData.admMessage} : ${msg}`;
    div.style.padding = "10px";

    // Helper to toggle error/canvas visibility
    const updateBannerVisibility = () => {
      const hasError = this.errorDiv.children.length > 0;
      this.errorDiv.style.display = hasError ? "block" : "none";
      this.unityCanvas.style.display = hasError ? "none" : "block";
      this.unityContainer.style.display = hasError ? "none" : "block";
    };

    // Style and logic based on banner type
    switch (type) {
      case "error":
        div.style.background = "darkred";
        break;
      case "warning":
        if (!this.canvasData.currentUserIsAdmin) return;
        div.style.background = "darkorange";
        setTimeout(() => {
          this.errorDiv.removeChild(div);
          updateBannerVisibility();
        }, 2000);
        break;
    }

    this.errorDiv.appendChild(div);
    updateBannerVisibility();
  }

  /**
   * Returns full paths to Unity build files based on build URL and loader name.
   */
  getUnityBuildFiles(buildUrl, loaderName) {
    return {
      dataUrl: `${buildUrl}${loaderName}.data`,
      frameworkUrl: `${buildUrl}${loaderName}.framework.js`,
      loaderUrl: `${buildUrl}${loaderName}.loader.js`,
      wasmUrl: `${buildUrl}${loaderName}.wasm`,
    };
  }

  /**
   * Reloads the Unity instance by quitting the current one (if any)
   * and then loading it again with the same build configuration.
   */
  async reload() {
    if (this.unityInstance) {
      try {
        await this.unityInstance.Quit();
        // Reload Unity with current build info
        await this.load(this.canvasData.buildUrl, this.canvasData.loaderName);
      } catch (e) {
        this.showBanner(`Reload failed: ${e.message}`, "error");
      }
    } else {
      // If no instance exists, just load normally
      await this.load(this.canvasData.buildUrl, this.canvasData.loaderName);
    }
  }

  /**
   * Loads the Unity WebGL build dynamically.
   */
  async load(buildUrl, loaderName, configOverrides = {}) {
    const files = this.getUnityBuildFiles(buildUrl, loaderName);

    const noop = () => {};
    this.config = {
      dataUrl: files.dataUrl,
      frameworkUrl: files.frameworkUrl,
      codeUrl: files.wasmUrl,
      streamingAssetsUrl: "StreamingAssets",
      webglContextAttributes: { preserveDrawingBuffer: true },
      companyName: "EnosiStudio",
      productName: "EnosiStudio",
      productVersion: "1.0",
      configOverrides,
      ...(!this.canvasData.showLogs && { print: noop, printErr: noop }),
    };

    // Load the Unity loader script
    await this.loadScript(files.loaderUrl);

    this.loader.show();

    try {
      // Create Unity instance
      this.unityInstance = await createUnityInstance(
        this.unityCanvas,
        this.config,
        (progress) => {
          this.loader.updateProgress(progress);
        }
      );

      // Apply size constraints
      const h = this.canvasData.fixedHeight;
      const ar = this.canvasData.aspectRatio;
      if (h > 0 && ar) {
        const [arW, arH] = ar.split("/").map(Number);
        this.unityContainer.style.height = h + "px";
        this.unityContainer.style.width = Math.round(h * (arW / arH)) + "px";
      } else if (h > 0) {
        this.unityContainer.style.height = h + "px";
      } else if (ar) {
        this.unityContainer.style.aspectRatio = ar;
      }

      // Show optional toolbar if enabled
      if (this.canvasData.showOptions) {
        new UnityToolbar(this.unityCanvas, this.uuid, {
          reload: () => this.reload(),
        });
      }
    } catch (e) {
      this.showBanner(e.message ?? String(e), "error");
    } finally {
      this.loader.hide();
    }
  }

  /**
   * Dynamically loads an external JS script, reusing it if already injected.
   */
  loadScript(src) {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = src;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`Failed to load script ${src}`));
      document.body.appendChild(script);
    });
  }
}

// Initialize UnityInstanceManager for all Unity canvas elements
document.querySelectorAll("canvas.unity-canvas").forEach((canvas) => {
  const uuid = canvas.id.replace("-canvas", "");
  const manager = new UnityInstanceManager(uuid);
  manager.load(manager.canvasData.buildUrl, manager.canvasData.loaderName);
});
