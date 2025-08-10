export class UnityToolbar {
  constructor(canvas, uuid, options = {}) {
    
    
    // Store reference to the Unity canvas element
    this.canvas = canvas;
    
    // Create the toolbar container
    this.toolbar = document.createElement("div");
    this.toolbar.id = uuid + "-toolbar";
    this.toolbar.reload = options.reload;
    this.toolbar.className = "unity-toolbar";
    
    // Add toolbar buttons
    this._createFPSCounter();
    
    if(!this._isHardwareAccelerated()){
      this._createAlerteHardwareAcceleration();
    }
    
    this._addInfoButton();
    this._createScreenshotButton(canvas);
    this._createReloadButton();
    this._createFullscreenButton();
    
    // Attach toolbar to DOM
    this._attach();
  }
  
  // Creates and adds information about the canvas
  _addInfoButton() {
    const btn = document.createElement("span");
    btn.textContent = "ℹ️";
    btn.addEventListener("mouseenter", () => {
      const canvas = this.canvas;
      const gcd = (a, b) => (b === 0 ? a : gcd(b, a % b));
      const w = canvas.width;
      const h = canvas.height;
      const divisor = gcd(w, h);
      const ratio = `${w / divisor}:${h / divisor}`;
      
      btn.title =
      `Canvas: ${canvas.width}×${canvas.height}\n` +
      `Aspect Ratio: ${ratio}\n` +
      `Device Pixel Ratio: ${window.devicePixelRatio.toFixed(2)}`;
    });
    this.toolbar.appendChild(btn);
  }
  
  // Creates and adds a FPS counter to the toolbar
  _createFPSCounter() {
    const fpsDisplay = document.createElement("div");
    fpsDisplay.title = "Frames per second";
    this.toolbar.appendChild(fpsDisplay);
    
    let lastTime = performance.now();
    let frames = 0;
    
    const update = () => {
      const now = performance.now();
      frames++;
      if (now - lastTime >= 1000) {
        fpsDisplay.textContent = `FPS: ${frames}`;
        frames = 0;
        lastTime = now;
      }
      requestAnimationFrame(update);
    };
    
    requestAnimationFrame(update);
  }
  
  _createScreenshotButton(canvas) {
    const btn = document.createElement("button");
    btn.id = "screenshot-btn";
    btn.textContent = "📷";
    btn.title = "Take a screenshot";
    
    btn.addEventListener("click", () => {
      const dataURL = canvas.toDataURL("image/png");
      const a = document.createElement("a");
      a.href = dataURL;
      a.download = "unity-screenshot.png";
      a.click();
    });
    
    this.toolbar.appendChild(btn);
  }
  
  // Creates and adds a reload button to the toolbar
  _createReloadButton() {
    const btn = document.createElement("button");
    btn.id = "reload-btn";
    btn.textContent = "⟳"; // Unicode for reload symbol
    
    btn.title = "Reload the page";
    
    btn.addEventListener("click", () => {
      if(this.toolbar.reload) this.toolbar.reload();
      // location.reload();
    });
    
    this.toolbar.appendChild(btn);
  }
  
  // Check if hardware acceleration is enabled
  // Returns true if WebGL is supported and not using SwiftShader
  _isHardwareAccelerated() {
    try {
      const canvas = document.createElement("canvas");
      const gl = canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
      
      if (!gl) return false;
      
      const debugInfo = gl.getExtension("WEBGL_debug_renderer_info");
      const renderer = debugInfo
      ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || ""
      : "";
      
      console.log("Renderer:", renderer);
      
      return !/swiftshader|software/i.test(renderer.toLowerCase());
    } catch (e) {
      return false;
    }
  }
  
  // Creates and adds a warning button for hardware acceleration
  _createAlerteHardwareAcceleration() {
    const warning = document.createElement("span");
    warning.id = "hardware-warning";
    warning.textContent = "⚠️";
    warning.title =
    "⚠️ Hardware acceleration is disabled. Enable it in your browser settings for better performance.";
    warning.style.color = "orange";
    warning.style.cursor = "default";
    this.toolbar.appendChild(warning);
  }
  
  // Creates and adds a fullscreen toggle button to the toolbar
  _createFullscreenButton() {
    const btn = document.createElement("button");
    btn.id = "fullscreen-btn";
    btn.textContent = "⛶";
    btn.title = "Toggle Fullscreen";
    
    // Handle fullscreen toggle on click
    btn.addEventListener("click", () => {
      const req =
      this.canvas.requestFullscreen ||
      this.canvas.webkitRequestFullscreen ||
      this.canvas.msRequestFullscreen;
      if (req) req.call(this.canvas);
    });
    
    this.toolbar.appendChild(btn);
  }
  
  // Attach the toolbar to the canvas container
  _attach() {
    const container = this.canvas.parentElement;
    container.style.position = "relative";
    container.appendChild(this.toolbar);
  }
}
